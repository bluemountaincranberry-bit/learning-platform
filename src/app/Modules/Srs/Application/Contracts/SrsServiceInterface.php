<?php

namespace App\Modules\Srs\Application\Contracts;

use App\Modules\Srs\Domain\Models\SrsCard;
use App\Modules\Srs\Domain\IntervalCalculator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;

interface SrsServiceInterface
{
    public function getDueCards(int $userId): Collection;

    /** @param array<string, mixed> $context */
    public function reviewCard(int $cardId, int $grade, int $userId, IntervalCalculator $calculator, array $context = []): SrsCard;

    /**
     * Canonical lexeme IDs for active cards in this user's repetition queue.
     */
    public function getInReviewLexemeIds(int $userId): BaseCollection;
}
