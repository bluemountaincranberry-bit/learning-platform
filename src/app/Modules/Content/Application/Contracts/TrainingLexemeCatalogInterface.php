<?php

namespace App\Modules\Content\Application\Contracts;

interface TrainingLexemeCatalogInterface
{
    /** @param list<int> $contentLexemeIds @return array<int, array<string, mixed>> keyed by content lexeme ID */
    public function presentations(array $contentLexemeIds, string $translationLanguage): array;
}
