<?php

namespace App\Modules\Srs\Infrastructure\Persistence;

use App\Modules\Srs\Domain\Models\SrsCard;
use App\Modules\Srs\Domain\Models\SrsReview;
use App\Modules\Srs\Application\Contracts\SrsRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;

class EloquentSrsRepository implements SrsRepositoryInterface
{
    public function getInReviewLexemeIds(int $userId): BaseCollection
    {
        return SrsCard::query()->where('user_id', $userId)->whereNull('deactivated_at')->whereNotNull('lexeme_id')->pluck('lexeme_id')->flip();
    }

    public function getDueCards(int $userId): Collection
    {
        $driver = SrsCard::query()->getConnection()->getDriverName();
        $itemKeyMatch = $driver === 'sqlite'
            ? "(content_lexemes.type || ':' || content_lexemes.text) = srs_cards.item_key"
            : "CONCAT(content_lexemes.type, ':', content_lexemes.text) = srs_cards.item_key";

        return SrsCard::query()
            ->from('srs_cards')
            ->leftJoin('lexemes as canonical_lexemes', 'canonical_lexemes.id', '=', 'srs_cards.lexeme_id')
            ->leftJoin('content_lexemes', function ($join) use ($itemKeyMatch): void {
                $join->on('content_lexemes.content_id', '=', 'srs_cards.content_id')
                    ->whereRaw($itemKeyMatch);
            })
            ->where('srs_cards.user_id', $userId)
            ->whereNull('srs_cards.deactivated_at')
            ->whereNotNull('srs_cards.next_review_at')
            ->where('srs_cards.next_review_at', '<=', now())
            ->orderBy('srs_cards.next_review_at')
            ->select('srs_cards.*', 'canonical_lexemes.lemma as canonical_lemma', 'content_lexemes.text as source_lemma', 'content_lexemes.id as content_lexeme_id')
            ->get()
            ->map(function (SrsCard $card): SrsCard {
                $card->lexeme_display = $card->canonical_lemma ?: $card->source_lemma;
                if (! $card->lexeme_display) {
                    $itemKey = $card->item_key ?? '';
                    $card->lexeme_display = str_contains($itemKey, ':') ? explode(':', $itemKey, 2)[1] : $itemKey;
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

    public function deactivateCardsForLearning(int $userId, int $lexemeId): int
    {
        return SrsCard::query()->where('user_id', $userId)->where('lexeme_id', $lexemeId)->whereNull('deactivated_at')->update(['deactivated_at' => now()]);
    }
}
