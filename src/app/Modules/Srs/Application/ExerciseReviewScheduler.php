<?php

namespace App\Modules\Srs\Application;

use App\Modules\Srs\Application\Contracts\ExerciseReviewSchedulerInterface;
use App\Modules\Srs\Application\Contracts\SrsServiceInterface;
use App\Modules\Srs\Domain\IntervalCalculator;
use App\Modules\Srs\Domain\Models\SrsCard;

final class ExerciseReviewScheduler implements ExerciseReviewSchedulerInterface
{
    public function __construct(
        private readonly SrsServiceInterface $srs,
        private readonly IntervalCalculator $intervalCalculator,
    ) {}

    public function scheduleReview(
        int $userId,
        int $contentId,
        string $itemKey,
        int $grade,
        array $context = [],
    ): bool {
        $cardId = SrsCard::query()
            ->where('user_id', $userId)
            ->where('content_id', $contentId)
            ->where('item_key', $itemKey)
            ->value('id');

        if ($cardId === null) {
            return false;
        }

        $this->srs->reviewCard((int) $cardId, $grade, $userId, $this->intervalCalculator, $context);

        return true;
    }
}
