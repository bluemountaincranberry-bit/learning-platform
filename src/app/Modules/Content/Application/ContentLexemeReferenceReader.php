<?php

namespace App\Modules\Content\Application;

use App\Modules\Content\Application\Contracts\ContentLexemeReferenceReaderInterface;
use App\Modules\Content\Domain\Models\ContentLexeme;

class ContentLexemeReferenceReader implements ContentLexemeReferenceReaderInterface
{
    public function __construct(private readonly CanonicalLexemeSyncService $lexemes) {}

    public function canonicalLexemeId(int $contentLexemeId): ?int
    {
        return ContentLexeme::query()->whereKey($contentLexemeId)->value('lexeme_id');
    }

    public function resolveCanonicalLexemeId(int $contentLexemeId): int
    {
        $occurrence = ContentLexeme::query()->findOrFail($contentLexemeId);
        if ($occurrence->lexeme_id === null) {
            $this->lexemes->sync($occurrence);
            $occurrence->refresh();
        }

        return (int) $occurrence->lexeme_id;
    }
}
