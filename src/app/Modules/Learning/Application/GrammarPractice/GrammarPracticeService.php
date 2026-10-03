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
 * a correct answer, a second wrong one, or "Show answer". A round is saved
 * only when it is finished; closing it early writes nothing.
 */
final class GrammarPracticeService
{
    public const OUTCOME_FIRST_TRY = 'first_try';

    public const OUTCOME_AFTER_HINT = 'after_hint';

    public const OUTCOME_ANSWER_SHOWN = 'answer_shown';

    public const OUTCOME_REPORTED = 'reported';

    public const OUTCOMES = [self::OUTCOME_FIRST_TRY, self::OUTCOME_AFTER_HINT, self::OUTCOME_ANSWER_SHOWN, self::OUTCOME_REPORTED];

    public const COUNTS = [5, 10, 15];

    /** Score that offers "Mark as learned" on a Medium or Hard round. */
    public const LEARNED_THRESHOLD_PCT = 80.0;

    private const GENERIC_HINT = 'Not quite. Look at the rule again: which form fits here?';

    public function __construct(
        private readonly GrammarExercisePoolInterface $pool,
        private readonly GrammarAttemptLogInterface $attempts,
        private readonly GrammarExerciseGenerationsInterface $generations,
        private readonly GrammarRoundComposer $composer,
    ) {}

    public function rule(int $ruleId): ?GrammarPracticeRule
    {
        return $this->pool->practiceRule($ruleId);
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

            return $this->ready($round, $available);
        }

        $round = $this->composer->compose($available, $this->attempts->exerciseHistory($userId, $rule->id), $level, $count);
        if (count($round) >= $count) {
            return $this->ready($round, $available);
        }

        $size = $available === []
            ? (int) config('ai.exercises.practice.first_batch', 15)
            : (int) config('ai.exercises.practice.top_up_batch', 10);
        $request = $this->generations->request($rule->id, $userId, $size);

        if (count($round) >= min($count, (int) config('ai.exercises.practice.min_to_start', 5))) {
            return $this->ready($round, $available);
        }

        if ($request->isPending()) {
            return ['status' => 'preparing', 'exercises' => [], 'available_count' => count($available)];
        }

        if ($round !== []) {
            return $this->ready($round, $available);
        }

        return ['status' => 'unavailable', 'reason' => $request->status, 'exercises' => [], 'available_count' => count($available)];
    }

    /**
     * One answer to one exercise. `attempt` is 1 for the first answer and 2
     * for the retry after the hint.
     *
     * @return array<string, mixed>|null null when the exercise is not practicable for this learner
     */
    public function check(int $userId, int $exerciseId, string|int|null $given, int $attempt, bool $showAnswer): ?array
    {
        $exercise = $this->pool->find($exerciseId, $userId);
        if ($exercise === null) {
            return null;
        }

        $correct = ! $showAnswer && $given !== null && $given !== '' && $this->pool->isCorrect($exercise, $given);

        if ($correct) {
            return [
                'correct' => true,
                'outcome' => $attempt <= 1 ? self::OUTCOME_FIRST_TRY : self::OUTCOME_AFTER_HINT,
                'answer' => $exercise->answer,
                'explanation' => $exercise->explanation,
            ];
        }

        if ($attempt <= 1 && ! $showAnswer) {
            return [
                'correct' => false,
                'hint' => $exercise->hint ?? self::GENERIC_HINT,
                'struck_option_index' => $exercise->type === 'multiple_choice' && is_numeric($given) ? (int) $given : null,
            ];
        }

        return [
            'correct' => false,
            'outcome' => self::OUTCOME_ANSWER_SHOWN,
            'answer' => $exercise->answer,
            'explanation' => $exercise->explanation,
        ];
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
     * and returns the result screen.
     *
     * @param  list<array{exercise_id: int, outcome: string, attempts?: int|null, given?: string|null, ms?: int|null}>  $items
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function complete(int $userId, GrammarPracticeRule $rule, GrammarPracticeLevel $level, array $items, ?int $contentId): array
    {
        $available = [];
        foreach ($this->pool->available($rule->id, $userId) as $exercise) {
            $available[$exercise->id] = $exercise;
        }

        $logged = [];
        $review = [];
        $counts = array_fill_keys(self::OUTCOMES, 0);

        foreach ($items as $item) {
            $outcome = $item['outcome'];
            $exercise = $available[$item['exercise_id']] ?? null;

            // A reported exercise is already hidden from the pool; it is logged but never scored.
            if ($outcome !== self::OUTCOME_REPORTED && $exercise === null) {
                continue;
            }

            $given = isset($item['given']) ? (string) $item['given'] : null;

            // Never trust a claimed success the answer doesn't support.
            if (in_array($outcome, [self::OUTCOME_FIRST_TRY, self::OUTCOME_AFTER_HINT], true)
                && ($given === null || ! $this->pool->isCorrect($exercise, $given))) {
                $outcome = self::OUTCOME_ANSWER_SHOWN;
            }

            $givenText = $exercise?->type === 'multiple_choice' && is_numeric($given)
                ? ($exercise->options[(int) $given] ?? $given)
                : $given;

            $counts[$outcome]++;
            $logged[] = [
                'exercise_id' => (int) $item['exercise_id'],
                'type' => $exercise?->type,
                'level' => $exercise?->level,
                'outcome' => $outcome,
                'attempts' => (int) ($item['attempts'] ?? 0),
                'given' => $givenText,
                'ms' => isset($item['ms']) ? (int) $item['ms'] : null,
            ];

            if (in_array($outcome, [self::OUTCOME_AFTER_HINT, self::OUTCOME_ANSWER_SHOWN], true)) {
                $review[] = [
                    'exercise_id' => $exercise->id,
                    'type' => $exercise->type,
                    'instruction' => $exercise->instruction,
                    'prompt' => $exercise->prompt,
                    'given' => $givenText,
                    'answer' => $exercise->answer,
                    'outcome' => $outcome,
                ];
            }
        }

        $scored = $counts[self::OUTCOME_FIRST_TRY] + $counts[self::OUTCOME_AFTER_HINT] + $counts[self::OUTCOME_ANSWER_SHOWN];
        if ($scored === 0) {
            throw ValidationException::withMessages(['items' => 'The round has no answered exercises of this rule.']);
        }

        $scorePct = round(($counts[self::OUTCOME_FIRST_TRY] + 0.5 * $counts[self::OUTCOME_AFTER_HINT]) / $scored * 100, 2);
        $confidenceBefore = $this->progress($userId, $rule->id)?->confidence_calculated;

        $attemptId = $this->attempts->recordPractice(new GrammarPracticeAttempt(
            userId: $userId,
            ruleId: $rule->id,
            contentId: $contentId,
            level: $level->value,
            scoredCount: $scored,
            correctCount: $counts[self::OUTCOME_FIRST_TRY] + $counts[self::OUTCOME_AFTER_HINT],
            scorePct: $scorePct,
            items: $logged,
        ));

        GrammarPracticeCompleted::dispatch($userId, $rule->id, $attemptId, $level->value, $scorePct);

        $progress = $this->progress($userId, $rule->id);
        $learned = $progress?->status === UserGrammarRule::STATUS_LEARNED;

        return [
            'attempt_id' => $attemptId,
            'level' => $level->value,
            'score_pct' => $scorePct,
            'first_try' => $counts[self::OUTCOME_FIRST_TRY],
            'after_hint' => $counts[self::OUTCOME_AFTER_HINT],
            'missed' => $counts[self::OUTCOME_ANSWER_SHOWN],
            'reported' => $counts[self::OUTCOME_REPORTED],
            'confidence_before' => $confidenceBefore,
            'confidence_after' => $progress?->confidence_calculated,
            'in_my_list' => $progress !== null,
            'learned' => $learned,
            'can_mark_learned' => ! $learned && $level->canProveLearned() && $scorePct >= self::LEARNED_THRESHOLD_PCT,
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
     * @param  list<GrammarPracticeExercise>  $round
     * @param  list<GrammarPracticeExercise>  $available
     * @return array{status: 'ready', exercises: list<array<string, mixed>>, available_count: int}
     */
    private function ready(array $round, array $available): array
    {
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

    private function progress(int $userId, int $ruleId): ?UserGrammarRule
    {
        return UserGrammarRule::query()->where('user_id', $userId)->where('grammar_rule_id', $ruleId)->first();
    }
}
