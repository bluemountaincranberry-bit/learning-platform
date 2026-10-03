<?php

namespace App\Modules\Srs\Application;

use App\Modules\Content\Application\Contracts\ContentReviewScheduleReaderInterface;
use App\Modules\Srs\Application\Contracts\ReviewScheduleReaderInterface;
use App\Modules\Srs\Domain\Models\SrsCard;

final class ReviewScheduleReader implements ContentReviewScheduleReaderInterface, ReviewScheduleReaderInterface
{
    public function itemKeys(int $userId): array
    {
        return SrsCard::query()->where('user_id', $userId)->pluck('item_key')->all();
    }

    public function dueContentIds(int $userId): array
    {
        return $this->overdueContentIds($userId);
    }

    public function wordsForQuiz(int $userId, int $limit): array
    {
        return SrsCard::query()
            ->where('user_id', $userId)
            ->orderByRaw('next_review_at IS NULL, next_review_at')
            ->limit($limit)
            ->pluck('item_key')
            ->map(fn (string $itemKey): string => (string) preg_replace('/^(word|phrase):/', '', $itemKey))
            ->all();
    }

    public function forUser(int $userId, int $limit): array
    {
        $now = now();
        $baseQuery = SrsCard::query()
            ->where('user_id', $userId)
            ->whereNotNull('next_review_at');

        $dueNowCount = (clone $baseQuery)->where('next_review_at', '<=', $now)->count();
        $upcoming = (clone $baseQuery)
            ->orderBy('next_review_at')
            ->limit($limit)
            ->get(['item_key', 'next_review_at', 'state']);

        return [
            'due_now_count' => $dueNowCount,
            'upcoming' => $upcoming->map(fn (SrsCard $card): array => [
                'item' => (string) preg_replace('/^(word|phrase):/', '', $card->item_key),
                'state' => $card->state,
                'next_review_at' => $card->next_review_at?->toIso8601String(),
                'is_due' => $card->next_review_at !== null && $card->next_review_at->lessThanOrEqualTo($now),
            ])->all(),
        ];
    }

    public function overdueContentIds(int $userId): array
    {
        return SrsCard::query()
            ->where('user_id', $userId)
            ->whereNotNull('next_review_at')
            ->where('next_review_at', '<=', now())
            ->pluck('content_id')
            ->unique()
            ->map(fn ($contentId): int => (int) $contentId)
            ->values()
            ->all();
    }

    public function dueCards(int $userId, ?int $contentId = null): array
    {
        $driver = SrsCard::query()->getConnection()->getDriverName();
        $itemKeyMatch = $driver === 'sqlite'
            ? "(content_lexemes.type || ':' || content_lexemes.text) = srs_cards.item_key"
            : "CONCAT(content_lexemes.type, ':', content_lexemes.text) = srs_cards.item_key";

        return SrsCard::query()
            ->from('srs_cards')
            ->leftJoin('content_lexemes', function ($join) use ($itemKeyMatch): void {
                $join->on('content_lexemes.content_id', '=', 'srs_cards.content_id')->whereRaw($itemKeyMatch);
            })
            ->where('srs_cards.user_id', $userId)
            ->whereNotNull('srs_cards.next_review_at')
            ->where('srs_cards.next_review_at', '<=', now())
            ->when($contentId !== null, fn ($query) => $query->where('srs_cards.content_id', $contentId))
            ->orderBy('srs_cards.next_review_at')
            ->select('srs_cards.*', 'content_lexemes.text as lexeme_display', 'content_lexemes.id as resolved_content_lexeme_id')
            ->get()
            ->map(fn (SrsCard $card): array => [
                'id' => (int) $card->id,
                'content_id' => (int) $card->content_id,
                'content_lexeme_id' => $card->resolved_content_lexeme_id === null ? null : (int) $card->resolved_content_lexeme_id,
                'item_key' => $card->item_key,
                'lexeme_display' => $card->lexeme_display ?: (str_contains($card->item_key, ':') ? explode(':', $card->item_key, 2)[1] : $card->item_key),
                'state' => $card->state,
                'next_review_at' => $card->next_review_at?->toIso8601String(),
            ])->all();
    }
}
