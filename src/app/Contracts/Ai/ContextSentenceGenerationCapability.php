<?php

namespace App\Contracts\Ai;

use App\Modules\Ai\Application\Data\ContextSentenceGenerationResult;

interface ContextSentenceGenerationCapability
{
    public function generate(string $lexemeText, string $targetLanguage, string $nativeLanguage, ?string $topic = null): ContextSentenceGenerationResult;
}
