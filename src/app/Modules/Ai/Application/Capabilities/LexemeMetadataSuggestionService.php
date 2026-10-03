<?php

namespace App\Modules\Ai\Application\Capabilities;

use App\Exceptions\AiClientException;
use App\Modules\Ai\Application\Agent\Tracing\TraceContext;
use App\Modules\Ai\Application\Agent\Tracing\TracedLlmCall;
use App\Modules\Ai\Application\Data\LexemeMetadataSuggestionResult;
use App\Contracts\Ai\AiJsonClient;
use App\Contracts\Ai\LexemeMetadataSuggestionCapability;
use App\Contracts\Ai\PromptRegistryInterface;

final class LexemeMetadataSuggestionService implements LexemeMetadataSuggestionCapability
{
    public function __construct(
        private readonly AiJsonClient $jsonClient,
        private readonly PromptRegistryInterface $promptRegistry,
        private readonly TracedLlmCall $tracedCall,
    ) {}

    /**
     * @return array{level: string|null, part_of_speech: string|null}
     *
     * @throws AiClientException
     */
    public function suggest(string $lexemeText): LexemeMetadataSuggestionResult
    {
        $rendered = $this->promptRegistry->resolve(
            'ai_suggest_metadata',
            ['lexeme' => $lexemeText],
            fn () => [
                'system' => 'You are a language assessment expert. Given a word or phrase, suggest its CEFR level '
                    .'(one of: A1, A2, B1, B2, C1, C2) and its part of speech (one of: noun, verb, adjective, adverb, '
                    .'phrase, idiom, preposition, conjunction, pronoun, interjection, other).',
                'user' => "Word/phrase: \"{$lexemeText}\". Suggest CEFR level and part of speech.",
            ]
        );

        $result = $this->tracedCall->completeJson(
            $this->jsonClient,
            TraceContext::newTrace(),
            'suggest_metadata.complete',
            ['feature' => 'lexeme_metadata'],
            $rendered->system,
            $rendered->user,
            ['level' => 'A1|A2|B1|B2|C1|C2', 'part_of_speech' => 'noun|verb|adjective|adverb|phrase|idiom|preposition|conjunction|pronoun|interjection|other'],
            $rendered->model,
        );

        return new LexemeMetadataSuggestionResult(
            level: is_string($result['level'] ?? null) ? trim($result['level']) : null,
            partOfSpeech: is_string($result['part_of_speech'] ?? null) ? trim($result['part_of_speech']) : null,
        );
    }
}
