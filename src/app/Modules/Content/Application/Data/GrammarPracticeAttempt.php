<?php

namespace App\Modules\Content\Application\Data;

/**
 * One finished practice round, as stored in grammar_exam_attempts
 * (type = practice). `items` keeps exercise_id, type, level, outcome,
 * attempts, given and ms per exercise.
 */
final readonly class GrammarPracticeAttempt
{
    /** @param  list<array<string, mixed>>  $items */
    public function __construct(
        public int $userId,
        public int $ruleId,
        public ?int $contentId,
        public string $level,
        public int $scoredCount,
        public int $correctCount,
        public float $scorePct,
        public array $items,
    ) {}
}
