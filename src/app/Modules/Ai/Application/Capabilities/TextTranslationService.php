<?php

namespace App\Modules\Ai\Application\Capabilities;

use App\Contracts\Ai\AiClientInterface;
use App\Contracts\Ai\PromptRegistryInterface;
use App\Contracts\Ai\TextTranslationCapability;
use App\Exceptions\AiClientException;
use App\Modules\Ai\Application\Agent\Tracing\TraceContext;
use App\Modules\Ai\Application\Agent\Tracing\TracedLlmCall;
use Illuminate\Support\Facades\Cache;

final class TextTranslationService implements TextTranslationCapability
{
    /** @throws AiClientException */
    public function translate(string $text, string $targetLanguage): string
    {
        $cacheKey = 'ai:translate:'.sha1($targetLanguage."\n".$text);
        if (config('ai.explain_cache_enabled', true)) {
            $cached = Cache::get($cacheKey);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }

        $rendered = $this->promptRegistry->resolve(
            'ai_translate_text',
            ['target_language' => $targetLanguage],
            fn () => [
                'system' => 'You are a language tutor. Translate the given text accurately, preserving tone and formatting. Reply with only the translation, no explanation.',
                'user' => "Translate to {$targetLanguage}:\n\n{$text}",
            ]
        );

        $result = trim($this->tracedCall->complete(
            $this->client,
            TraceContext::newTrace(),
            'text_translation.translate',
            ['feature' => 'text_translation'],
            $rendered->system,
            $rendered->user,
            $rendered->model,
        ));

        if ($result !== '' && config('ai.explain_cache_enabled', true)) {
            Cache::put($cacheKey, $result, (int) config('ai.explain_cache_ttl_seconds', 604800));
        }

        return $result;
    }

    public function __construct(
        private readonly AiClientInterface $client,
        private readonly PromptRegistryInterface $promptRegistry,
        private readonly TracedLlmCall $tracedCall,
    ) {}
}
