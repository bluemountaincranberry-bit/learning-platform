<?php

namespace App\Modules\Ai\Application\Data;

final readonly class LexemeMetadataSuggestionResult
{
    public function __construct(
        public ?string $level,
        public ?string $partOfSpeech,
    ) {}

    /** @return array{level: string|null, part_of_speech: string|null} */
    public function toArray(): array
    {
        return ['level' => $this->level, 'part_of_speech' => $this->partOfSpeech];
    }
}
