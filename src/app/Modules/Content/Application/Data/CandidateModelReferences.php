<?php

namespace App\Modules\Content\Application\Data;

use App\Modules\Content\Domain\Models\ContentGrammarCandidate;
use App\Modules\Content\Domain\Models\ContentLexemeCandidate;

final class CandidateModelReferences
{
    /** @return class-string<ContentLexemeCandidate> */
    public static function lexemeCandidate(): string
    {
        return ContentLexemeCandidate::class;
    }

    /** @return class-string<ContentGrammarCandidate> */
    public static function grammarCandidate(): string
    {
        return ContentGrammarCandidate::class;
    }
}
