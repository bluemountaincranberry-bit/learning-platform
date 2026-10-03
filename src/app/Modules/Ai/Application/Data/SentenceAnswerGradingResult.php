<?php

namespace App\Modules\Ai\Application\Data;

final readonly class SentenceAnswerGradingResult
{
    public function __construct(
        public bool $correct,
        public string $feedback,
        public string $modelAnswer,
    ) {}

    /** @return array{correct: bool, feedback: string, model_answer: string} */
    public function toArray(): array
    {
        return [
            'correct' => $this->correct,
            'feedback' => $this->feedback,
            'model_answer' => $this->modelAnswer,
        ];
    }
}
