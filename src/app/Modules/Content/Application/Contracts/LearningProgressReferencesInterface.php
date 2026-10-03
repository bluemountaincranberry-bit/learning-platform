<?php

namespace App\Modules\Content\Application\Contracts;

interface LearningProgressReferencesInterface
{
    /** @param list<int> $lexemeIds
     * @return list<int>
     */
    public function referencedLexemeIds(array $lexemeIds): array;

    public function hasContentLexeme(int $contentLexemeId): bool;
}
