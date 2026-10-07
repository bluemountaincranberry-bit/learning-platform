<?php

namespace App\Modules\Content\Application\Contracts;

interface LearnedLexemeCatalogInterface
{
    /** @param list<int> $lexemeIds @param array{language?: string, content_id?: int} $filters @return list<int> */
    public function filterVisibleLexemeIds(int $userId, array $lexemeIds, array $filters): array;

    /** @param list<array{progress_id: int, lexeme_id: int, content_lexeme_id: ?int}> $entries @return array<int, array<string, mixed>> */
    public function presentations(array $entries, string $translationLanguage): array;
}
