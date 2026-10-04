<?php

namespace App\Contracts\Ai;

interface TextTranslationCapability
{
    /**
     * Translates a longer learner-facing text (e.g. a saved AI explanation)
     * into the target language. Returns the translation only.
     *
     * @throws \App\Exceptions\AiClientException
     */
    public function translate(string $text, string $targetLanguage): string;
}
