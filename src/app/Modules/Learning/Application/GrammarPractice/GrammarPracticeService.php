<?php

namespace App\Modules\Learning\Application\GrammarPractice;

use App\Modules\Content\Application\Contracts\GrammarAttemptLogInterface;
use App\Modules\Content\Application\Contracts\GrammarExerciseGenerationsInterface;
use App\Modules\Content\Application\Contracts\GrammarExercisePoolInterface;
use App\Modules\Content\Application\Data\GrammarPracticeAttempt;
use App\Modules\Content\Application\Data\GrammarPracticeExercise;
use App\Modules\Content\Application\Data\GrammarPracticeRule;
use App\Modules\Learning\Domain\Events\GrammarPracticeCompleted;
use App\Modules\Learning\Domain\Models\UserGrammarRule;
use App\Support\AiConfig;
use Illuminate\Validation\ValidationException;

/**
 * Practice one grammar rule: start card → round → result
 * (docs/product/grammar-exercises-block.md, VIK-31).
 *
 * Answers are checked here, not in the browser, so the hint → retry →
 * answer contract (VIK-32) holds: the answer leaves the server only after
 * a correct answer, a second wrong one, or "Show answer". The server keeps
 * each exercise's outcome (GrammarPracticeLedger) and saves that, not what
 * the client claims. A round is saved only when it is finished; closing it
 * early writes nothing.
 */
final class GrammarPracticeService
{
    public const COUNTS = [5, 10, 15];

    /** Score that offers "Mark as learned" on a Medium or Hard round. */
    public const LEARNED_THRESHOLD_PCT = 80.0;

    private const GENERIC_HINT = 'Not quite. Look at the rule again: which form fits here?';

    public function __construct(
        private readonly GrammarExercisePoolInterface $pool,
        private readonly GrammarAttemptLogInterface $attempts,
        private readonly GrammarExerciseGenerationsInterface $generations,
        private readonly GrammarRoundComposer $composer,
        private readonly GrammarPracticeLedger $ledger,
    ) {}

    public function rule(int $ruleId, int $userId): ?GrammarPracticeRule
    {
        return $this->pool->practiceRule($ruleId, $userId);
    }

    /** @return array<string, mixed> start card data */
    public function overview(int $userId, GrammarPracticeRule $rule): array
    {
        $available = $this->pool->available($rule->id, $userId);
        $history = $this->attempts->exerciseHistory($userId, $rule->id);
        $last = $this->attempts->lastPractice($userId, $rule->id);
        $progress = $this->progress($userId, $rule->id);

        return [
            'rule' => ['id' => $rule->id, 'title' => $rule->title],
            'available_count' => count($available),
            'unseen_count' => count(array_filter($available, fn (GrammarPracticeExercise $e) => ! isset($history[$e->id]))),
            'preparing' => $this->generations->hasActive($rule->id),
            'can_generate' => AiConfig::isEnabled(),
            'last_result' => $last === null ? null : [
                'score_pct' => $last->scorePct,
                'correct_count' => $last->correctCount,
                'scored_count' => $last->scoredCount,
                'level' => $last->level,
                'completed_at' => $last->completedAt->toIso8601String(),
            ],
            'in_my_list' => $progress !== null,
            'learned' => $progress?->status === UserGrammarRule::STATUS_LEARNED,
            'confidence_calculated' => $progress?->confidence_calculated,
        ];
    }

    /**
     * Builds a round, or queues AI generation when the pool can't fill it.
     *
     * @param  list<int>|null  $onlyExerciseIds  "Practice mistakes": exactly these exercises
     * @return array{status: 'ready'|'preparing'|'unavailable', exercises: list<array<string, mixed>>, reason?: string, available_count: int}
     */
    public function startRound(int $userId, GrammarPracticeRule $rule, GrammarPracticeLevel $level, int $count, ?array $onlyExerciseIds = null): array
    {
        $available = $this->pool->available($rule->id, $userId);

        if ($onlyExerciseIds !== null) {
            $chosen = array_values(array_filter($available, fn (GrammarPracticeExercise $e) => in_array($e->id, $onlyExerciseIds, true)));
            $round = $this->composer->compose($chosen, [], GrammarPracticeLevel::Medium, count($chosen));

            return $this->ready($userId, $round, $available);
        }

        $round = $this->composer->compose($available, $this->attempts->exerciseHistory($userId, $rule->id), $level, $count);
        if (count($round) >= $count) {
            return $this->ready($userId, $round, $available);
        }

        $size = $available === []
            ? (int) config('ai.exercises.practice.first_batch', 15)
            : (int) config('ai.exercises.practice.top_up_batch', 10);
        $request = $this->generations->request($rule->id, $userId, $size);

        if (count($round) >= min($count, (int) config('ai.exercises.practice.min_to_start', 5))) {
            return $this->ready($userId, $round, $available);
        }

        if ($request->isPending()) {
            return ['status' => 'preparing', 'exercises' => [], 'available_count' => count($available)];
        }

        // No new exercises are coming: practice whatever exists, even from the other level.
        if ($round === [] && $available !== []) {
            $round = $this->composer->compose($available, $this->attempts->exerciseHistory($userId, $rule->id), GrammarPracticeLevel::Medium, $count);
        }

        if ($round !== []) {
            return $this->ready($userId, $round, $available);
        }

        return ['status' => 'unavailable', 'reason' => $request->status, 'exercises' => [], 'available_count' => count($available)];
    }

    /**
     * One answer to one exercise. The first wrong answer gets a hint; the
     * second wrong one (or "Show answer") settles it with the answer. Asking
     * again about a settled exercise returns the same result.
     *
     * @return array<string, mixed>|null null when the exercise is not practicable for this learner
     */
    public function check(int $userId, int $exerciseId, ?string $given, bool $showAnswer): ?array
    {
        $exercise = $this->pool->find($exerciseId, $userId);
        if ($exercise === null) {
            return null;
        }

        $state = $this->ledger->get($userId, $exerciseId);
        if ($state['outcome'] !== null) {
            return $this->settled($exercise, $state['outcome']);
        }

        $correct = ! $showAnswer && $given !== null && $given !== '' && $this->pool->isCorrect($exercise, $given);

        if ($correct) {
            $outcome = $state['wrong'] === 0 ? GrammarPracticeOutcome::FirstTry : GrammarPracticeOutcome::AfterHint;
            $this->ledger->settle($userId, $exerciseId, $outcome, $given);

            return $this->settled($exercise, $outcome);
        }

        if ($state['wrong'] === 0 && ! $showAnswer) {
            $this->ledger->recordWrong($userId, $exerciseId);

            return [
                'correct' => false,
                'hint' => $exercise->hint ?? self::GENERIC_HINT,
                'struck_option_index' => $exercise->type === 'multiple_choice' && is_numeric($given) ? (int) $given : null,
            ];
        }

        $this->ledger->settle($userId, $exerciseId, GrammarPracticeOutcome::AnswerShown, $given);

        return $this->settled($exercise, GrammarPracticeOutcome::AnswerShown);
    }

    /**
     * Hides a bad exercise for this learner and returns the one that takes
     * its place in the round, if any.
     *
     * @param  list<int>  $roundExerciseIds
     * @return array<string, mixed>|null the replacement exercise
     */
    public function reportAndReplace(int $userId, int $exerciseId, GrammarPracticeLevel $level, array $roundExerciseIds, ?string $reason): ?array
    {
        $exercise = $this->pool->find($exerciseId, $userId);
        if ($exercise === null) {
            return null;
        }

        $this->pool->report($userId, $exerciseId, $reason);

        $replacement = $this->composer->compose(
            $this->pool->available($exercise->ruleId, $userId),
            $this->attempts->exerciseHistory($userId, $exercise->ruleId),
            $level,
            1,
            [...$roundExerciseIds, $exerciseId],
        );

        return $replacement === [] ? null : $this->payload($replacement[0]);
    }

    /**
     * Saves a finished round as one attempt, fires GrammarPracticeCompleted
     * and returns the result screen. Outcomes come from the server's ledger;
     * the client only says which exercises were in the round, which it
     * reported, and how long each took. A "Practice mistakes" replay
     * (`$replay`) is scored for the screen but not saved: it would only
     * re-measure answers the learner has just been shown.
     *
     * @param  list<array{exercise_id: int, outcome?: string|null, ms?: int|null}>  $items
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function complete(int $userId, GrammarPracticeRule $rule, GrammarPracticeLevel $level, array $items, ?int $contentId, bool $replay = false): array
    {
        $available = [];
        foreach ($this->pool->available($rule->id, $userId) as $exercise) {
            $available[$exercise->id] = $exercise;
        }
        $reportedByLearner = $this->pool->reportedBy($userId, $rule->id);

        $logged = [];
        $review = [];
        $counts = array_fill_keys(array_map(fn (GrammarPracticeOutcome $o) => $o->value, GrammarPracticeOutcome::cases()), 0);
        $handled = [];

        foreach ($items as $item) {
            $exerciseId = (int) $item['exercise_id'];
            if (isset($handled[$exerciseId])) {
                continue;
            }
            $handled[$exerciseId] = true;

            $ms = isset($item['ms']) ? (int) $item['ms'] : null;

            if (($item['outcome'] ?? null) === GrammarPracticeOutcome::Reported->value) {
                if (in_array($exerciseId, $reportedByLearner, true)) {
                    $counts[GrammarPracticeOutcome::Reported->value]++;
                    $logged[] = ['exercise_id' => $exerciseId, 'type' => null, 'level' => null, 'outcome' => GrammarPracticeOutcome::Reported->value, 'attempts' => 0, 'given' => null, 'ms' => $ms];
                }

                continue;
            }

            $exercise = $available[$exerciseId] ?? null;
            $state = $this->ledger->get($userId, $exerciseId);
            if ($exercise === null || $state['outcome'] === null) {
                continue;
            }

            $outcome = $state['outcome'];
            $given = $state['given'];
            $givenText = $exercise->type === 'multiple_choice' && is_numeric($given)
                ? ($exercise->options[(int) $given] ?? $given)
                : $given;

            $counts[$outcome->value]++;
            $logged[] = [
                'exercise_id' => $exerciseId,
                'type' => $exercise->type,
                'level' => $exercise->level,
                'outcome' => $outcome->value,
                'attempts' => $state['wrong'] + ($outcome === GrammarPracticeOutcome::AnswerShown ? 0 : 1),
                'given' => $givenText,
                'ms' => $ms,
            ];

            if ($outcome->isMistake()) {
                $review[] = [
                    'exercise_id' => $exercise->id,
                    'type' => $exercise->type,
                    'instruction' => $exercise->instruction,
                    'prompt' => $exercise->prompt,
                    'given' => $givenText,
                    'answer' => $exercise->answer,
                    'outcome' => $outcome->value,
                ];
            }
        }

        $firstTry = $counts[GrammarPracticeOutcome::FirstTry->value];
        $afterHint = $counts[GrammarPracticeOutcome::AfterHint->value];
        $missed = $counts[GrammarPracticeOutcome::AnswerShown->value];
        $scored = $firstTry + $afterHint + $missed;
        if ($scored === 0) {
            throw ValidationException::withMessages(['items' => 'The round has no answered exercises of this rule.']);
        }

        $scorePct = round(($firstTry + 0.5 * $afterHint) / $scored * 100, 2);
        $confidenceBefore = $this->progress($userId, $rule->id)?->confidence_calculated;
        $attemptId = null;

        if (! $replay) {
            $attemptId = $this->attempts->recordPractice(new GrammarPracticeAttempt(
                userId: $userId,
                ruleId: $rule->id,
                contentId: $contentId,
                level: $level->value,
                scoredCount: $scored,
                correctCount: $firstTry + $afterHint,
                scorePct: $scorePct,
                items: $logged,
            ));

            GrammarPracticeCompleted::dispatch($userId, $rule->id, $attemptId, $level->value, $scorePct);
        }

        $this->ledger->forget($userId, array_keys($handled));

        $progress = $this->progress($userId, $rule->id);
        $learned = $progress?->status === UserGrammarRule::STATUS_LEARNED;

        return [
            'attempt_id' => $attemptId,
            'level' => $level->value,
            'score_pct' => $scorePct,
            'first_try' => $firstTry,
            'after_hint' => $afterHint,
            'missed' => $missed,
            'reported' => $counts[GrammarPracticeOutcome::Reported->value],
            'confidence_before' => $confidenceBefore,
            'confidence_after' => $progress?->confidence_calculated,
            'in_my_list' => $progress !== null,
            'learned' => $learned,
            'can_mark_learned' => ! $replay && ! $learned && $level->canProveLearned() && $scorePct >= self::LEARNED_THRESHOLD_PCT,
            'to_review' => $review,
        ];
    }

    /**
     * What the learner sees before answering: no answer, no hint, no
     * explanation. Build tiles are shuffled.
     *
     * @return array<string, mixed>
     */
    public function payload(GrammarPracticeExercise $exercise): array
    {
        return [
            'id' => $exercise->id,
            'rule_id' => $exercise->ruleId,
            'type' => $exercise->type,
            'level' => $exercise->level,
            'instruction' => $exercise->instruction,
            'prompt' => $exercise->prompt,
            'options' => $exercise->options,
            'tiles' => $exercise->tiles === null ? null : $this->shuffled($exercise->tiles),
            'origin' => $exercise->origin,
        ];
    }

    /**
     * A new round starts clean: answers from a round closed early don't carry over.
     *
     * @param  list<GrammarPracticeExercise>  $round
     * @param  list<GrammarPracticeExercise>  $available
     * @return array{status: 'ready', exercises: list<array<string, mixed>>, available_count: int}
     */
    private function ready(int $userId, array $round, array $available): array
    {
        $this->ledger->forget($userId, array_map(fn (GrammarPracticeExercise $e) => $e->id, $round));

        return [
            'status' => 'ready',
            'exercises' => array_map(fn (GrammarPracticeExercise $e) => $this->payload($e), $round),
            'available_count' => count($available),
        ];
    }

    /**
     * @param  list<string>  $tiles
     * @return list<string>
     */
    private function shuffled(array $tiles): array
    {
        if (count($tiles) < 2) {
            return $tiles;
        }

        // A shuffle that lands on the answer order would make the exercise trivial.
        for ($i = 0; $i < 5; $i++) {
            $shuffled = $tiles;
            shuffle($shuffled);
            if ($shuffled !== $tiles) {
                return $shuffled;
            }
        }

        return array_reverse($tiles);
    }

    /** @return array<string, mixed> */
    private function settled(GrammarPracticeExercise $exercise, GrammarPracticeOutcome $outcome): array
    {
        return [
            'correct' => $outcome !== GrammarPracticeOutcome::AnswerShown,
            'outcome' => $outcome->value,
            'answer' => $exercise->answer,
            'explanation' => $exercise->explanation,
        ];
    }

    private function progress(int $userId, int $ruleId): ?UserGrammarRule
    {
        return UserGrammarRule::query()->where('user_id', $userId)->where('grammar_rule_id', $ruleId)->first();
    }
}
