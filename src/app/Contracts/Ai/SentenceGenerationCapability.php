<?php

namespace App\Contracts\Ai;

use App\Modules\Ai\Application\Data\GeneratedSentenceCard;
use App\Modules\Ai\Application\Data\SentenceGenerationInput;

interface SentenceGenerationCapability
{
    /**
     * @param  array{native_language: string, target_language: ?string, words: list<string>, grammar_topics: list<string>}  $context
     * @return list<array{prompt_sentence: string, answer_sentence: string, prompt_language: string, answer_language: string, hint_words: list<string>}>
     */
    /** @return list<GeneratedSentenceCard> */
    public function generate(SentenceGenerationInput $input): array;
}
