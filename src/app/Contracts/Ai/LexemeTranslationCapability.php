<?php

namespace App\Contracts\Ai;

interface LexemeTranslationCapability
{
    public function translate(string $lexemeText, string $targetLanguage, string $nativeLanguage): string;
}
