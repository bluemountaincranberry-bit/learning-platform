<?php

namespace App\Modules\Ai\Application\Data;

final readonly class GeneratedSentenceCard
{
    /** @param list<string> $hintWords */
    public function __construct(
        public string $promptSentence,
        public string $answerSentence,
        public string $promptLanguage,
        public string $answerLanguage,
        public array $hintWords,
    ) {}

    /** @return array{prompt_sentence: string, answer_sentence: string, prompt_language: string, answer_language: string, hint_words: list<string>} */
    public function toArray(): array
    {
        return ['prompt_sentence' => $this->promptSentence, 'answer_sentence' => $this->answerSentence, 'prompt_language' => $this->promptLanguage, 'answer_language' => $this->answerLanguage, 'hint_words' => $this->hintWords];
    }
}
