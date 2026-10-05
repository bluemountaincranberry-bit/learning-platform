<?php

namespace App\Modules\Srs\Application;

use App\Modules\Content\Application\Contracts\ContentReviewScheduleReaderInterface;
use App\Modules\Content\Application\Contracts\LexemeIdentityReaderInterface;
use App\Modules\Srs\Application\Contracts\ReviewScheduleReaderInterface;
use App\Modules\Srs\Domain\Models\SrsCard;
use Illuminate\Support\Facades\DB;

final class ReviewScheduleReader implements ContentReviewScheduleReaderInterface, ReviewScheduleReaderInterface
{
    public function __construct(private readonly LexemeIdentityReaderInterface $lexemes) {}

    public function lexemeIds(int $userId): array
    {
        return SrsCard::query()->where('user_id', $userId)->whereNull('deactivated_at')->whereNotNull('lexeme_id')->pluck('lexeme_id')->map(static fn ($id): int => (int) $id)->all();
    }

    public function dueContentIds(int $userId): array
    {
        return $this->overdueContentIds($userId);
    }

    public function wordsForQuiz(int $userId, int $limit): array
    {
        $cards = SrsCard::query()
            ->where('user_id', $userId)
            ->whereNull('deactivated_at')
            ->orderByRaw('next_review_at IS NULL, next_review_at')
            ->limit($limit)
            ->get(['lexeme_id', 'item_key'])
            ->all();
        $ids = array_values(array_unique(array_filter(array_map(
            static fn (SrsCard $card): ?int => $card->lexeme_id === null ? null : (int) $card->lexeme_id,
            $cards,
        ))));
        $lemmas = $this->lexemes->lemmasByIds($ids);

        return array_map(
            fn (SrsCard $card): string => $lemmas[(int) $card->lexeme_id] ?? (string) preg_replace('/^(word|phrase):/', '', $card->item_key ?? ''),
            $cards,
        );
    }

    public function forUser(int $userId, int $limit): array
    {
        $now = now();
        $baseQuery = SrsCard::query()
            ->where('user_id', $userId)
            ->whereNull('deactivated_at')
            ->whereNotNull('next_review_at');

        $dueNowCount = (clone $baseQuery)->where('next_review_at', '<=', $now)->count();
        $upcoming = (clone $baseQuery)
            ->orderBy('next_review_at')
            ->limit($limit)
            ->get(['lexeme_id', 'item_key', 'next_review_at', 'state']);
        $lemmas = $this->lexemes->lemmasByIds($upcoming->pluck('lexeme_id')->filter()->map(static fn ($id): int => (int) $id)->unique()->values()->all());

        return [
            'due_now_count' => $dueNowCount,
            'upcoming' => $upcoming->map(fn (SrsCard $card): array => [
                'lexeme_id' => $card->lexeme_id === null ? null : (int) $card->lexeme_id,
                'item' => $lemmas[(int) $card->lexeme_id] ?? (string) preg_replace('/^(word|phrase):/', '', $card->item_key ?? ''),
                'state' => $card->state,
                'next_review_at' => $card->next_review_at?->toIso8601String(),
                'is_due' => $card->next_review_at !== null && $card->next_review_at->lessThanOrEqualTo($now),
            ])->all(),
        ];
    }

    public function overdueContentIds(int $userId): array
    {
        $dueCards = DB::table('srs_cards')
            ->where('srs_cards.user_id', $userId)
            ->whereNull('srs_cards.deactivated_at')
            ->whereNotNull('srs_cards.next_review_at')
            ->where('srs_cards.next_review_at', '<=', now());

        $legacyContentIds = (clone $dueCards)
            ->whereNotNull('srs_cards.content_id')
            ->pluck('srs_cards.content_id');
        $sourceContentIds = (clone $dueCards)
            ->whereNotNull('srs_cards.lexeme_id')
            ->join('user_lexeme_sources', function ($join): void {
                $join->on('user_lexeme_sources.user_id', '=', 'srs_cards.user_id')
                    ->on('user_lexeme_sources.lexeme_id', '=', 'srs_cards.lexeme_id');
            })
            ->join('content_lexemes', 'content_lexemes.id', '=', 'user_lexeme_sources.content_lexeme_id')
            ->pluck('content_lexemes.content_id');

        return $legacyContentIds->merge($sourceContentIds)
            ->unique()
            ->map(static fn ($contentId): int => (int) $contentId)
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
            ->leftJoin('lexemes as canonical_lexemes', 'canonical_lexemes.id', '=', 'srs_cards.lexeme_id')
            ->leftJoin('content_lexemes', function ($join) use ($itemKeyMatch): void {
                $join->on('content_lexemes.content_id', '=', 'srs_cards.content_id')->whereRaw($itemKeyMatch);
            })
            ->where('srs_cards.user_id', $userId)
            ->whereNull('srs_cards.deactivated_at')
            ->whereNotNull('srs_cards.next_review_at')
            ->where('srs_cards.next_review_at', '<=', now())
            ->when($contentId !== null, fn ($query) => $query->where('srs_cards.content_id', $contentId))
            ->orderBy('srs_cards.next_review_at')
            ->select('srs_cards.*', 'canonical_lexemes.lemma as canonical_lemma', 'content_lexemes.text as source_lemma', 'content_lexemes.id as resolved_content_lexeme_id')
            ->get()
            ->map(fn (SrsCard $card): array => [
                'id' => (int) $card->id,
                'lexeme_id' => $card->lexeme_id === null ? null : (int) $card->lexeme_id,
                'content_id' => $card->content_id === null ? null : (int) $card->content_id,
                'content_lexeme_id' => $card->resolved_content_lexeme_id === null ? null : (int) $card->resolved_content_lexeme_id,
                'item_key' => $card->item_key,
                'lexeme_display' => $card->canonical_lemma ?: ($card->source_lemma ?: (str_contains((string) $card->item_key, ':') ? explode(':', (string) $card->item_key, 2)[1] : (string) $card->item_key)),
                'state' => $card->state,
                'next_review_at' => $card->next_review_at?->toIso8601String(),
            ])->all();
    }
}
