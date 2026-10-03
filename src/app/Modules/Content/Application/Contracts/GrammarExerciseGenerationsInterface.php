<?php

namespace App\Modules\Content\Application\Contracts;

use App\Modules\Content\Application\Data\GrammarExerciseGenerationRequest;

/**
 * Queued AI batches of grammar exercises. At most one active batch per rule;
 * a learner gets a limited number of batches per rule per day.
 */
interface GrammarExerciseGenerationsInterface
{
    public function request(int $ruleId, int $userId, int $size): GrammarExerciseGenerationRequest;

    public function hasActive(int $ruleId): bool;

    /**
     * Marks a queued batch as running.
     *
     * @return array{rule_id: int, size: int}|null null when the batch is gone or already finished
     */
    public function start(int $generationId): ?array;

    public function finish(int $generationId, int $createdCount): void;

    public function fail(int $generationId, string $error): void;
}
