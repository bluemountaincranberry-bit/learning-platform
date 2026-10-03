<?php

namespace App\Modules\Ai\Application\Data;

final readonly class ContextSentenceGenerationResult
{
    /** @param list<string> $distractors */
    public function __construct(
        public string $sentence,
        public string $targetForm,
        public string $translation,
        public array $distractors,
    ) {}

    /** @return array{sentence: string, target_form: string, translation: string, distractors: list<string>} */
    public function toArray(): array
    {
        return ['sentence' => $this->sentence, 'target_form' => $this->targetForm, 'translation' => $this->translation, 'distractors' => $this->distractors];
    }
}
