<?php

namespace App\Modules\Content\Application\Contracts;

interface ContentLexemeReferenceReaderInterface
{
    public function canonicalLexemeId(int $contentLexemeId): ?int;
}
