<?php

namespace App\Modules\Learning\Application;

use App\Modules\Content\Application\Contracts\ContentTitleReaderInterface;
use App\Modules\Learning\Application\Data\StatsLearner;
use App\Modules\Learning\Domain\Models\LearningProgress;
use App\Modules\Learning\Domain\Models\UserLexemeProgress;
use App\Modules\Srs\Application\Contracts\ReviewGradePolicyInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ProgressStatsService
{
    private const RECOMMEND_REVIEW_ACCURACY_THRESHOLD = 70;

    private const BEST_TOP = 5;

    private const WEAK_BOTTOM = 5;

    private const WEAK_WORDS_LIMIT = 10;

    private const WEAK_TOPICS_LIMIT = 5;

    // Same "grade <= 2 is a failure" convention as SrsService::reviewCard()
    // (state -> relearning) and GetUserMistakesTool/GetWeakTopicsTool in the
    // Ai module — duplicated here rather than imported to keep Learning from
    // depending on Ai's agent-tool classes for a plain integer constant.
    public function __construct(
        private LearningStatsService $learningStatsService,
        private ReviewGradePolicyInterface $reviewGradePolicy,
        private ContentTitleReaderInterface $contentTitles,
    ) {}

    /**
     * @return array{total_learned: int, today_count: int, streak: int, daily_goal: int|null, week_count: int, month_count: int}
     */
    public function getOverview(StatsLearner $learner): array
    {
        $totalLearned = UserLexemeProgress::query()->where('user_id', $learner->userId)->count();
        $todayCount = $this->learningStatsService->getTodayLearnedCount($learner->userId, $learner->timezone);
        $streak = $this->learningStatsService->getStreakDays($learner->userId, $learner->timezone);
        $dailyGoal = $learner->dailyGoal;

        $tz = $learner->timezone ?? config('app.timezone', 'UTC');
        $now = Carbon::now($tz);
        $weekStart = $now->copy()->subDays(7)->startOfDay();
        $monthStart = $now->copy()->subDays(30)->startOfDay();

        $weekCount = UserLexemeProgress::query()
            ->where('user_id', $learner->userId)
            ->where('learned_at', '>=', $weekStart)
            ->count();
        $monthCount = UserLexemeProgress::query()
            ->where('user_id', $learner->userId)
            ->where('learned_at', '>=', $monthStart)
            ->count();

        return [
            'total_learned' => $totalLearned,
            'today_count' => $todayCount,
            'streak' => $streak,
            'daily_goal' => $dailyGoal,
            'week_count' => $weekCount,
            'month_count' => $monthCount,
        ];
    }

    /**
     * @return array{best: list<array{content_id: int, title: string, accuracy: float, total_answers: int, known_answers: int}>, weak: list<array{content_id: int, title: string, accuracy: float, total_answers: int, known_answers: int}>}
     */
    public function getByContent(StatsLearner $learner): array
    {
        $rows = LearningProgress::query()
            ->where('user_id', $learner->userId)
            ->join('contents', 'learning_progress.content_id', '=', 'contents.id')
            ->select([
                'learning_progress.content_id',
                'contents.title',
                'learning_progress.accuracy',
                'learning_progress.total_answers',
                'learning_progress.known_answers',
            ])
            ->orderByDesc('learning_progress.accuracy')
            ->get();

        $items = $rows->map(fn ($r) => [
            'content_id' => (int) $r->content_id,
            'title' => $r->title,
            'accuracy' => (float) $r->accuracy,
            'total_answers' => (int) $r->total_answers,
            'known_answers' => (int) $r->known_answers,
        ])->all();

        $best = array_slice($items, 0, self::BEST_TOP);
        $weak = array_slice(array_reverse($items), 0, self::WEAK_BOTTOM);

        return [
            'best' => array_values($best),
            'weak' => array_values($weak),
        ];
    }

    /**
     * @return list<array{language: string, level: string|null, total_answers: int, known_answers: int, accuracy: float}>
     */
    public function getByLanguageLevel(StatsLearner $learner): array
    {
        $rows = LearningProgress::query()
            ->where('user_id', $learner->userId)
            ->join('contents', 'learning_progress.content_id', '=', 'contents.id')
            ->select([
                'contents.language',
                'contents.level',
                DB::raw('SUM(learning_progress.total_answers) as total_answers'),
                DB::raw('SUM(learning_progress.known_answers) as known_answers'),
            ])
            ->groupBy('contents.language', 'contents.level')
            ->get();

        return $rows->map(function ($r) {
            $total = (int) $r->total_answers;
            $known = (int) $r->known_answers;
            $accuracy = $total > 0 ? round(($known / $total) * 100, 2) : 0.0;

            return [
                'language' => $r->language,
                'level' => $r->level,
                'total_answers' => $total,
                'known_answers' => $known,
                'accuracy' => $accuracy,
            ];
        })->values()->all();
    }

    /**
     * Most-missed words/phrases from `srs_reviews`, most-missed first — the
     * same source data `GetUserMistakesTool` grounds TutorAgent answers on,
     * surfaced here directly instead of requiring the learner to ask the
     * chatbot "what am I getting wrong?".
     *
     * @return list<array{lexeme_id: ?int, lexeme: string, content_id: ?int, hint: string}>
     */
    public function getWeakWords(StatsLearner $learner): array
    {
        $rows = DB::table('srs_reviews')
            ->join('srs_cards', 'srs_cards.id', '=', 'srs_reviews.srs_card_id')
            ->leftJoin('lexemes as canonical_lexemes', 'canonical_lexemes.id', '=', 'srs_cards.lexeme_id')
            ->where('srs_cards.user_id', $learner->userId)
            ->where('srs_reviews.grade', '<=', $this->reviewGradePolicy->failingThreshold())
            ->select('srs_cards.lexeme_id', 'canonical_lexemes.lemma', 'srs_cards.item_key', DB::raw('min(srs_cards.content_id) as content_id'), DB::raw('count(*) as mistake_count'))
            ->groupBy('srs_cards.lexeme_id', 'canonical_lexemes.lemma', 'srs_cards.item_key')
            ->orderByDesc('mistake_count')
            ->limit(self::WEAK_WORDS_LIMIT)
            ->get();

        return $rows->map(fn ($r) => [
            'lexeme_id' => $r->lexeme_id !== null ? (int) $r->lexeme_id : null,
            'lexeme' => $r->lemma ?: (string) preg_replace('/^(word|phrase):/', '', (string) $r->item_key),
            'content_id' => $r->content_id !== null ? (int) $r->content_id : null,
            'hint' => (int) $r->mistake_count === 1 ? '1 missed review' : $r->mistake_count.' missed reviews',
        ])->all();
    }

    /**
     * Failed reviews aggregated up to the grammar rule the missed word is
     * linked to (same join shape as `GetWeakTopicsTool`) — surfaces
     * recurring problem areas ("struggles with Present Perfect") instead of
     * a flat word list only.
     *
     * @return list<array{grammar_rule_id: int, title: string, mistake_count: int}>
     */
    public function getWeakGrammarTopics(StatsLearner $learner): array
    {
        $rows = DB::table('srs_reviews')
            ->join('srs_cards', 'srs_cards.id', '=', 'srs_reviews.srs_card_id')
            ->leftJoin('content_lexemes', function ($join): void {
                $driver = DB::connection()->getDriverName();
                $itemKeyMatch = $driver === 'sqlite'
                    ? "(content_lexemes.type || ':' || content_lexemes.text) = srs_cards.item_key"
                    : "CONCAT(content_lexemes.type, ':', content_lexemes.text) = srs_cards.item_key";
                $join->on('content_lexemes.content_id', '=', 'srs_cards.content_id')->whereRaw($itemKeyMatch);
            })
            ->join('grammar_rule_lexeme', function ($join): void {
                $join->whereRaw('grammar_rule_lexeme.lexeme_id = COALESCE(srs_cards.lexeme_id, content_lexemes.lexeme_id)');
            })
            ->join('grammar_rules', 'grammar_rules.id', '=', 'grammar_rule_lexeme.grammar_rule_id')
            ->where('srs_cards.user_id', $learner->userId)
            ->where('srs_reviews.grade', '<=', $this->reviewGradePolicy->failingThreshold())
            ->select('grammar_rules.id', 'grammar_rules.title', DB::raw('count(DISTINCT srs_reviews.id) as mistake_count'))
            ->groupBy('grammar_rules.id', 'grammar_rules.title')
            ->orderByDesc('mistake_count')
            ->limit(self::WEAK_TOPICS_LIMIT)
            ->get();

        return $rows->map(fn ($r) => [
            'grammar_rule_id' => (int) $r->id,
            'title' => $r->title,
            'mistake_count' => (int) $r->mistake_count,
        ])->all();
    }

    /**
     * @return list<array{type: string, content_id?: int, title?: string, hint?: string}>
     */
    public function getRecommendations(StatsLearner $learner): array
    {
        $recommendations = [];

        $toReview = LearningProgress::query()
            ->where('user_id', $learner->userId)
            ->where('accuracy', '<', self::RECOMMEND_REVIEW_ACCURACY_THRESHOLD)
            ->where('total_answers', '>=', 1)
            ->get();

        $titles = $this->contentTitles->titlesByIds($toReview->pluck('content_id')->map(fn ($id): int => (int) $id)->all());

        foreach ($toReview as $lp) {
            $recommendations[] = [
                'type' => 'review',
                'content_id' => $lp->content_id,
                'title' => $titles[(int) $lp->content_id] ?? null,
                'hint' => 'Accuracy below '.self::RECOMMEND_REVIEW_ACCURACY_THRESHOLD.'%',
            ];
        }

        foreach ($this->getSkillAccuracy($learner) as $skill) {
            if ($skill['attempts'] < 2 || $skill['accuracy'] >= 70) {
                continue;
            }
            $activity = match ($skill['skill']) {
                'listening' => 'dictation',
                'production' => 'cloze',
                'speaking' => 'shadowing',
                default => 'recall',
            };
            $recommendations[] = [
                'type' => 'activity',
                'activity' => $activity,
                'skill' => $skill['skill'],
                'hint' => "{$skill['skill']} accuracy is {$skill['accuracy']}%",
            ];
        }

        return $recommendations;
    }

    /**
     * @return array{overview: array, by_content: array, by_language_level: array, weak_words: array, weak_grammar_topics: array, recommendations: array, skill_accuracy: array, retention: array, points: array}
     */
    public function getStats(StatsLearner $learner): array
    {
        return [
            'overview' => $this->getOverview($learner),
            'by_content' => $this->getByContent($learner),
            'by_language_level' => $this->getByLanguageLevel($learner),
            'weak_words' => $this->getWeakWords($learner),
            'weak_grammar_topics' => $this->getWeakGrammarTopics($learner),
            'recommendations' => $this->getRecommendations($learner),
            'skill_accuracy' => $this->getSkillAccuracy($learner),
            'retention' => $this->getRetention($learner),
            'points' => $this->getPoints($learner),
        ];
    }

    /** @return array{total: int, today: int, week: int, by_activity: array<string, int>} */
    public function getPoints(StatsLearner $learner): array
    {
        $events = DB::table('learning_point_events')->where('user_id', $learner->userId)->get(['points', 'activity_type', 'created_at']);
        $today = Carbon::now($learner->timezone ?? config('app.timezone', 'UTC'))->startOfDay();
        $week = $today->copy()->subDays(6);

        return [
            'total' => (int) $events->sum('points'),
            'today' => (int) $events->filter(fn ($event) => Carbon::parse($event->created_at)->gte($today))->sum('points'),
            'week' => (int) $events->filter(fn ($event) => Carbon::parse($event->created_at)->gte($week))->sum('points'),
            'by_activity' => $events->groupBy('activity_type')->map(fn ($items) => (int) $items->sum('points'))->all(),
        ];
    }

    /** @return list<array{skill: string, attempts: int, correct: int, accuracy: float}> */
    public function getSkillAccuracy(StatsLearner $learner): array
    {
        $rows = DB::table('srs_reviews')
            ->join('srs_cards', 'srs_cards.id', '=', 'srs_reviews.srs_card_id')
            ->where('srs_cards.user_id', $learner->userId)
            ->select('srs_reviews.grade', 'srs_reviews.exercise_type')
            ->get();

        return $rows->groupBy(fn ($row) => $this->skillForExercise((string) ($row->exercise_type ?? 'review')))
            ->map(fn ($items, $skill) => [
                'skill' => $skill,
                'attempts' => $items->count(),
                'correct' => $items->where('grade', '>', $this->reviewGradePolicy->failingThreshold())->count(),
                'accuracy' => round($items->where('grade', '>', $this->reviewGradePolicy->failingThreshold())->count() / max(1, $items->count()) * 100, 1),
            ])->values()->all();
    }

    /** @return array<string, array{attempts: int, correct: int, accuracy: float}> */
    public function getRetention(StatsLearner $learner): array
    {
        $rows = DB::table('srs_reviews')
            ->join('srs_cards', 'srs_cards.id', '=', 'srs_reviews.srs_card_id')
            ->where('srs_cards.user_id', $learner->userId)
            ->where(function ($query): void {
                $query->whereNotNull('srs_cards.lexeme_id')->orWhereNotNull('srs_reviews.content_lexeme_id');
            })
            ->select('srs_cards.lexeme_id', 'srs_reviews.content_lexeme_id', 'srs_reviews.grade', 'srs_reviews.reviewed_at')
            ->orderBy('srs_reviews.reviewed_at')
            ->get();
        $identity = fn ($row): string => $row->lexeme_id !== null
            ? 'lexeme:'.$row->lexeme_id
            : 'occurrence:'.$row->content_lexeme_id;
        $first = $rows->groupBy($identity)->map(fn ($items) => Carbon::parse($items->first()->reviewed_at));
        $result = [];
        foreach ([1, 7, 30] as $days) {
            $eligible = $rows->filter(function ($row) use ($first, $days, $identity): bool {
                $start = $first->get($identity($row));

                return $start !== null && $start->diffInDays(Carbon::parse($row->reviewed_at)) >= $days;
            });
            $correct = $eligible->where('grade', '>', $this->reviewGradePolicy->failingThreshold())->count();
            $result[(string) $days] = ['attempts' => $eligible->count(), 'correct' => $correct, 'accuracy' => round($correct / max(1, $eligible->count()) * 100, 1)];
        }

        return $result;
    }

    private function skillForExercise(string $exercise): string
    {
        return match ($exercise) {
            'listening', 'dictation' => 'listening',
            'cloze', 'production' => 'production',
            'speaking', 'shadowing' => 'speaking',
            'listen-recognize' => 'recognition',
            default => 'recall',
        };
    }
}
