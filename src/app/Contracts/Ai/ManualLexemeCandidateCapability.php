<?php

namespace App\Contracts\Ai;

interface ManualLexemeCandidateCapability
{
    public function analyzeAndApply(int $contentId, string $text, string $sentence, string $sourceLanguage, string $translationLanguage): int;
}
