<?php

namespace App\Modules\Ai\Application\Capabilities;

use App\Exceptions\AiClientException;
use App\Modules\Ai\Application\Agent\Tracing\TraceContext;
use App\Modules\Ai\Application\Agent\Tracing\TracedLlmCall;
use App\Contracts\Ai\AiJsonClient;
use App\Contracts\Ai\LexemeTranslationCapability;
use App\Contracts\Ai\PromptRegistryInterface;

final class LexemeTranslationService implements LexemeTranslationCapability
{
    public function __construct(
        private readonly AiJsonClient $jsonClient,
        private readonly PromptRegistryInterface $promptRegistry,
        private readonly TracedLlmCall $tracedCall,
    ) {}

    /** @throws AiClientException */
    public function translate(string $lexemeText, string $targetLanguage, string $nativeLanguage): string
    {
        $rendered = $this->promptRegistry->resolve(
            'ai_translate_lexeme',
            ['lexeme' => $lexemeText, 'target_language' => $targetLanguage, 'native_language' => $nativeLanguage],
            fn () => [
                'system' => 'You are a language tutor. Translate the given word or phrase as concisely as possible, keeping the sense it has in context. Reply with only the translation, no explanation.',
                'user' => "Word/phrase in {$targetLanguage}: \"{$lexemeText}\". Translate to {$nativeLanguage}.",
            ]
        );

        $result = $this->tracedCall->completeJson(
            $this->jsonClient,
            TraceContext::newTrace(),
            'lexeme_translation.translate',
            ['feature' => 'lexeme_translation'],
            $rendered->system,
            $rendered->user,
            ['translation' => "string, the {$nativeLanguage} translation only, no extra words"],
            $rendered->model,
        );

        return is_string($result['translation'] ?? null) ? trim($result['translation']) : '';
    }
}
