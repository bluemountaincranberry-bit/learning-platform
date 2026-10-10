<?php

namespace App\Modules\Learning\Application;

use App\Modules\Content\Application\Contracts\TrainingLexemeCatalogInterface;
use App\Modules\Srs\Application\Contracts\ReviewScheduleReaderInterface;

final class TrainingSessionService
{
    public function __construct(
        private ReviewScheduleReaderInterface $reviewSchedule,
        private TrainingLexemeCatalogInterface $lexemeCatalog,
    ) {}

    /** @return list<array<string, mixed>> */
    public function getReviewQueue(int $userId, string $translationLanguage, ?int $contentId = null): array
    {
        $cards = $this->reviewSchedule->dueCards($userId, $contentId);
        $presentations = $this->lexemeCatalog->presentations(
            array_values(array_filter(array_column($cards, 'content_lexeme_id'))),
            $translationLanguage,
        );

        return array_map(function (array $card) use ($presentations): array {
            $presentation = $card['content_lexeme_id'] === null ? [] : ($presentations[$card['content_lexeme_id']] ?? []);

            return [
                'card_id' => $card['id'], 'lexeme_id' => $card['lexeme_id'], 'content_id' => $card['content_id'],
                'content_lexeme_id' => $card['content_lexeme_id'], 'item_key' => $card['item_key'],
                'lexeme_display' => $card['lexeme_display'], 'state' => $card['state'],
                'next_review_at' => $card['next_review_at'],
                'part_of_speech' => $presentation['part_of_speech'] ?? null,
                'level' => $presentation['level'] ?? null,
                'translation' => $presentation['translation'] ?? null,
                'example' => $presentation['example'] ?? null,
                'examples' => $presentation['examples'] ?? [],
                'associations' => $presentation['associations'] ?? [],
            ];
        }, $cards);
    }

    /** @param list<int> $contentLexemeIds @return list<array<string, mixed>> */
    public function getSelectedLexemes(string $translationLanguage, array $contentLexemeIds): array
    {
        $orderedIds = array_slice($contentLexemeIds, 0, 30);
        $presentations = $this->lexemeCatalog->presentations($orderedIds, $translationLanguage);

        return array_values(array_filter(array_map(
            fn (int $id): ?array => $presentations[$id] ?? null,
            $orderedIds,
        )));
    }

    /** @param list<int> $lexemeIds @return list<array<string, mixed>> */
    public function getSelectedCanonicalLexemes(int $userId, string $translationLanguage, array $lexemeIds): array
    {
        $orderedIds = array_slice(array_values(array_unique($lexemeIds)), 0, 30);
        $cards = $this->reviewSchedule->selectedCards($userId, $orderedIds);
        $presentations = $this->lexemeCatalog->canonicalPresentations(
            array_column($cards, 'lexeme_id'),
            $translationLanguage,
            $userId,
        );

        return array_values(array_filter(array_map(function (array $card) use ($presentations): ?array {
            $presentation = $presentations[$card['lexeme_id']] ?? null;
            if ($presentation === null) {
                return null;
            }

            return [
                'card_id' => $card['id'],
                'lexeme_id' => $card['lexeme_id'],
                'content_id' => null,
                'content_lexeme_id' => null,
                'item_key' => $card['item_key'],
                'lexeme_display' => $presentation['lexeme_display'] ?? $card['lexeme_display'],
                'language' => $presentation['language'] ?? null,
                'state' => $card['state'],
                'next_review_at' => $card['next_review_at'],
                'part_of_speech' => $presentation['part_of_speech'] ?? null,
                'level' => $presentation['level'] ?? null,
                'translation' => $presentation['translation'] ?? null,
                'example' => $presentation['example'] ?? null,
                'examples' => $presentation['examples'] ?? [],
                'associations' => $presentation['associations'] ?? [],
            ];
        }, $cards)));
    }
}
