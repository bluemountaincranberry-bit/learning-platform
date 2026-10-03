<?php

namespace App\Modules\Srs\Application\Contracts;

interface ExerciseReviewSchedulerInterface
{
    /** @param array<string, mixed> $context */
    public function scheduleReview(
        int $userId,
        int $contentId,
        string $itemKey,
        int $grade,
        array $context = [],
    ): bool;
}
