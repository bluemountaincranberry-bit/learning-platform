<?php

namespace App\Modules\Ai\Application\Data;

final readonly class SentenceAnswerGradingInput
{
    public function __construct(
        public string $promptSentence,
        public string $promptLanguage,
        public string $answerLanguage,
        public string $answer,
        public string $checkMode = 'flexible',
    ) {}
}
