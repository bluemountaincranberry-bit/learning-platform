<?php

namespace App\Modules\Srs\Interfaces\Listeners;

use App\Modules\Content\Contracts\Events\LexemeLearningStarted;
use App\Modules\Srs\Application\Contracts\SrsRepositoryInterface;

class CreateSrsCardOnLearningStartedListener
{
    public function __construct(private readonly SrsRepositoryInterface $repository) {}

    public function handle(LexemeLearningStarted $event): void
    {
        $card = $this->repository->firstOrCreateCard(
            [
                'user_id' => $event->userId,
                'lexeme_id' => $event->lexemeId,
            ],
            [
                'lexeme_id' => $event->lexemeId,
                'content_id' => $event->contentId,
                'item_key' => $event->itemKey,
                'state' => 'new',
                'interval_days' => 1,
                'ease_factor' => 2.50,
                'next_review_at' => now(),
                'deactivated_at' => null,
            ]
        );

        if ($card->deactivated_at !== null) {
            $card->update(['deactivated_at' => null]);
        }
    }
}
