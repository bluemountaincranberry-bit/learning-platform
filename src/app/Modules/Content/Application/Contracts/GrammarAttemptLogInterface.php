<?php

namespace App\Modules\Content\Application\Contracts;

use App\Modules\Content\Application\Data\GrammarExerciseHistory;
use App\Modules\Content\Application\Data\GrammarPracticeAttempt;
use App\Modules\Content\Application\Data\GrammarPracticeSummary;

/** Append-only log of grammar practice rounds (grammar_exam_attempts, type = practice). */
interface GrammarAttemptLogInterface
{
    /** @return int the attempt id */
    public function recordPractice(GrammarPracticeAttempt $attempt): int;

    public function lastPractice(int $userId, int $ruleId): ?GrammarPracticeSummary;

    /** @return array<int, GrammarExerciseHistory> keyed by exercise id */
    public function exerciseHistory(int $userId, int $ruleId): array;

    /** Recomputes confidence_calculated for the rule from all its signals. */
    public function recalculateConfidence(int $userId, int $ruleId): ?float;
}
