<?php

namespace App\Modules\Content\Application;

use App\Modules\Content\Application\Contracts\GrammarAttemptLogInterface;
use App\Modules\Content\Application\Data\GrammarExerciseHistory;
use App\Modules\Content\Application\Data\GrammarPracticeAttempt;
use App\Modules\Content\Application\Data\GrammarPracticeSummary;
use App\Modules\Content\Domain\Models\GrammarExamAttempt;
use App\Modules\Content\Domain\Models\GrammarRule;
use Carbon\CarbonImmutable;

final class GrammarAttemptLog implements GrammarAttemptLogInterface
{
    public function __construct(private readonly GrammarConfidenceService $confidence) {}

    public function recordPractice(GrammarPracticeAttempt $attempt): int
    {
        return GrammarExamAttempt::query()->create([
            'user_id' => $attempt->userId,
            'grammar_rule_id' => $attempt->ruleId,
            'content_id' => $attempt->contentId,
            'type' => GrammarExamAttempt::TYPE_PRACTICE,
            'level' => $attempt->level,
            'total_cards' => $attempt->scoredCount,
            'correct_count' => $attempt->correctCount,
            'score_pct' => $attempt->scorePct,
            'items' => $attempt->items,
            'completed_at' => now(),
        ])->id;
    }

    public function lastPractice(int $userId, int $ruleId): ?GrammarPracticeSummary
    {
        $attempt = $this->practiceAttempts($userId, $ruleId)->first();

        return $attempt === null ? null : new GrammarPracticeSummary(
            scorePct: (float) $attempt->score_pct,
            correctCount: (int) $attempt->correct_count,
            scoredCount: (int) $attempt->total_cards,
            level: $attempt->level,
            completedAt: CarbonImmutable::parse($attempt->completed_at),
        );
    }

    public function exerciseHistory(int $userId, int $ruleId): array
    {
        $history = [];

        // Newest first, so the first time an exercise shows up is its latest result.
        foreach ($this->practiceAttempts($userId, $ruleId)->cursor() as $attempt) {
            foreach ((array) $attempt->items as $item) {
                $exerciseId = (int) ($item['exercise_id'] ?? 0);
                if ($exerciseId === 0 || isset($history[$exerciseId])) {
                    continue;
                }
                $history[$exerciseId] = new GrammarExerciseHistory(
                    exerciseId: $exerciseId,
                    lastSeenAt: CarbonImmutable::parse($attempt->completed_at),
                    lastOutcome: (string) ($item['outcome'] ?? ''),
                );
            }
        }

        return $history;
    }

    public function recalculateConfidence(int $userId, int $ruleId): ?float
    {
        $rule = GrammarRule::query()->find($ruleId);

        return $rule === null ? null : $this->confidence->recalculate($userId, $rule);
    }

    /** @return \Illuminate\Database\Eloquent\Builder<GrammarExamAttempt> */
    private function practiceAttempts(int $userId, int $ruleId)
    {
        return GrammarExamAttempt::query()
            ->where('user_id', $userId)
            ->where('grammar_rule_id', $ruleId)
            ->where('type', GrammarExamAttempt::TYPE_PRACTICE)
            ->orderByDesc('completed_at')
            ->orderByDesc('id');
    }
}
