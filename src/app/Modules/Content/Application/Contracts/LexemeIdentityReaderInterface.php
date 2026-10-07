<?php

namespace App\Modules\Content\Application\Contracts;

interface LexemeIdentityReaderInterface
{
    /** @param list<int> $lexemeIds @return array<int, string> */
    public function lemmasByIds(array $lexemeIds): array;
}
