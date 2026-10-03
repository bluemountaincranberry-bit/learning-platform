<?php

namespace App\Modules\Ai\Application\Data;

final readonly class SentenceGenerationInput
{
    /** @param list<string> $words @param list<string> $grammarTopics */
    public function __construct(
        public string $nativeLanguage,
        public ?string $targetLanguage,
        public array $words,
        public array $grammarTopics,
        public string $direction,
        public int $count,
    ) {}

    /** @return array{native_language: string, target_language: ?string, words: list<string>, grammar_topics: list<string>} */
    public function context(): array
    {
        return ['native_language' => $this->nativeLanguage, 'target_language' => $this->targetLanguage, 'words' => $this->words, 'grammar_topics' => $this->grammarTopics];
    }
}
