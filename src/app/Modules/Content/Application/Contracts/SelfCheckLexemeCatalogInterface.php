<?php

namespace App\Modules\Content\Application\Contracts;

interface SelfCheckLexemeCatalogInterface
{
    /**
     * @param  list<int>|null  $contentLexemeIds
     * @return array<int, array<string, mixed>> keyed by content lexeme ID
     */
    public function forContent(int $contentId, ?array $contentLexemeIds, string $translationLanguage): array;

    public function language(int $contentId): string;
}
