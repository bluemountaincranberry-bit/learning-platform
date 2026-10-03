<?php

namespace App\Contracts\Ai;

use App\Modules\Ai\Application\Data\LexemeMetadataSuggestionResult;

interface LexemeMetadataSuggestionCapability
{
    public function suggest(string $lexemeText): LexemeMetadataSuggestionResult;
}
