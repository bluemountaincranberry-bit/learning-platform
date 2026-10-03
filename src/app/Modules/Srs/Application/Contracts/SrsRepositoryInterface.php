<?php

namespace App\Modules\Srs\Application\Contracts;

use App\Modules\Srs\Domain\Models\SrsCard;
use App\Modules\Srs\Domain\Models\SrsReview;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;

interface SrsRepositoryInterface
{
    public function getInReviewItemKeys(int $userId): BaseCollection;

    /** @return Collection<int, SrsCard> */
    public function getDueCards(int $userId): Collection;

    public function findCardForUserOrFail(int $cardId, int $userId): SrsCard;

    /** @param array<string, mixed> $attributes */
    public function updateCard(SrsCard $card, array $attributes): SrsCard;

    /** @param array<string, mixed> $attributes */
    public function createReview(array $attributes): SrsReview;

    /** @param array<string, mixed> $attributes @param array<string, mixed> $values */
    public function firstOrCreateCard(array $attributes, array $values): SrsCard;

    public function deleteCardsForLearning(int $userId, string $itemKey): int;
}
