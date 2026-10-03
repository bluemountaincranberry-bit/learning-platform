<?php

namespace App\Modules\Content\Application;

use App\Modules\Content\Application\Contracts\ContentLexemeReferenceReaderInterface;
use App\Modules\Content\Domain\Models\ContentLexeme;

class ContentLexemeReferenceReader implements ContentLexemeReferenceReaderInterface
{
    public function canonicalLexemeId(int $contentLexemeId): ?int
    {
        return ContentLexeme::query()->whereKey($contentLexemeId)->value('lexeme_id');
    }
}
