<?php

namespace App\Contracts\Ai;

interface LexemeExplanationCapability
{
    public function explain(string $lexemeText, ?string $language = null, ?int $contentLexemeId = null, bool $refresh = false): string;
}
