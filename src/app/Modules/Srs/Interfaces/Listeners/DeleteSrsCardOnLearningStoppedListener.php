<?php

namespace App\Modules\Srs\Interfaces\Listeners;

use App\Modules\Content\Contracts\Events\LexemeLearningStopped;
use App\Modules\Srs\Application\Contracts\SrsRepositoryInterface;

class DeleteSrsCardOnLearningStoppedListener
{
    public function __construct(private readonly SrsRepositoryInterface $repository) {}

    public function handle(LexemeLearningStopped $event): void
    {
        $this->repository->deactivateCardsForLearning($event->userId, $event->lexemeId);
    }
}
