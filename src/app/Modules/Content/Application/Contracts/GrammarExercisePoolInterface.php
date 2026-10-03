<?php

namespace App\Modules\Content\Application\Contracts;

use App\Modules\Content\Application\Data\GrammarPracticeExercise;
use App\Modules\Content\Application\Data\GrammarPracticeRule;

/**
 * The exercises a learner may practice for a grammar rule: published ones
 * plus AI-generated ones, minus archived, minus what this learner reported,
 * minus what enough learners reported.
 */
interface GrammarExercisePoolInterface
{
    /** The rule when a learner may practice it, otherwise null. */
    public function practiceRule(int $ruleId): ?GrammarPracticeRule;

    /** @return list<GrammarPracticeExercise> */
    public function available(int $ruleId, int $userId): array;

    public function find(int $exerciseId, int $userId): ?GrammarPracticeExercise;

    /** Choose the form takes the option index; every other type takes text. */
    public function isCorrect(GrammarPracticeExercise $exercise, string|int $given): bool;

    public function report(int $userId, int $exerciseId, ?string $reason): void;

    /**
     * Exercises of the rule this learner has reported.
     *
     * @return list<int>
     */
    public function reportedBy(int $userId, int $ruleId): array;
}
