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
        return SrsCard::query()
            ->from('srs_cards')
            ->leftJoin('lexemes as canonical_lexemes', 'canonical_lexemes.id', '=', 'srs_cards.lexeme_id')
            ->where('srs_cards.user_id', $userId)
            ->whereNotNull('srs_cards.lexeme_id')
            ->whereNull('srs_cards.deactivated_at')
            ->whereNotNull('srs_cards.next_review_at')
            ->where('srs_cards.next_review_at', '<=', now())
            ->orderBy('srs_cards.next_review_at')
            ->select('srs_cards.*', 'canonical_lexemes.lemma as lexeme_display')
            ->get();
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
