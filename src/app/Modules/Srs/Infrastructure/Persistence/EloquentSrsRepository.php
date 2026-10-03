<?php

namespace App\Modules\Srs\Infrastructure\Persistence;

use App\Modules\Srs\Domain\Models\SrsCard;
use App\Modules\Srs\Domain\Models\SrsReview;
use App\Modules\Srs\Application\Contracts\SrsRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;

class EloquentSrsRepository implements SrsRepositoryInterface
{
    public function getInReviewItemKeys(int $userId): BaseCollection
    {
        return SrsCard::query()->where('user_id', $userId)->pluck('item_key')->flip();
    }

    public function getDueCards(int $userId): Collection
    {
        $driver = SrsCard::query()->getConnection()->getDriverName();
        $itemKeyMatch = $driver === 'sqlite'
            ? "(content_lexemes.type || ':' || content_lexemes.text) = srs_cards.item_key"
            : "CONCAT(content_lexemes.type, ':', content_lexemes.text) = srs_cards.item_key";

        return SrsCard::query()
            ->from('srs_cards')
            ->leftJoin('content_lexemes', function ($join) use ($itemKeyMatch): void {
                $join->on('content_lexemes.content_id', '=', 'srs_cards.content_id')
                    ->whereRaw($itemKeyMatch);
            })
            ->where('srs_cards.user_id', $userId)
            ->whereNotNull('srs_cards.next_review_at')
            ->where('srs_cards.next_review_at', '<=', now())
            ->orderBy('srs_cards.next_review_at')
            ->select('srs_cards.*', 'content_lexemes.text as lexeme_display', 'content_lexemes.id as content_lexeme_id')
            ->get()
            ->map(function (SrsCard $card): SrsCard {
                if (! $card->lexeme_display) {
                    $card->lexeme_display = str_contains($card->item_key, ':')
                        ? explode(':', $card->item_key, 2)[1]
                        : $card->item_key;
                    $card->content_lexeme_id = null;
                }

                return $card;
            });
    }

    public function findCardForUserOrFail(int $cardId, int $userId): SrsCard
    {
        return SrsCard::query()
            ->where('user_id', $userId)
            ->findOrFail($cardId);
    }

    public function updateCard(SrsCard $card, array $attributes): SrsCard
    {
        $card->update($attributes);

        return $card->fresh();
    }

    public function createReview(array $attributes): SrsReview
    {
        return SrsReview::query()->create($attributes);
    }

    public function firstOrCreateCard(array $attributes, array $values): SrsCard
    {
        return SrsCard::query()->firstOrCreate($attributes, $values);
    }

    public function deleteCardsForLearning(int $userId, string $itemKey): int
    {
        return SrsCard::query()->where('user_id', $userId)->where('item_key', $itemKey)->delete();
    }
}
