<?php

namespace App\Modules\Srs\Interfaces\Listeners;

use App\Modules\Content\Contracts\Events\LexemeLearningStarted;
use App\Modules\Srs\Application\Contracts\SrsRepositoryInterface;

class CreateSrsCardOnLearningStartedListener
{
    public function __construct(private readonly SrsRepositoryInterface $repository) {}

    public function handle(LexemeLearningStarted $event): void
    {
        $this->repository->firstOrCreateCard(
            [
                'user_id' => $event->userId,
                'item_key' => $event->itemKey,
            ],
            [
                'content_id' => $event->contentId,
                'state' => 'new',
                'interval_days' => 1,
                'ease_factor' => 2.50,
                'next_review_at' => now(),
            ]
        );
    }
}
