<?php

namespace App\Modules\Ai\Application\Capabilities;

use App\Exceptions\AiClientException;
use App\Modules\Ai\Application\Agent\Tracing\TraceContext;
use App\Modules\Ai\Application\Agent\Tracing\TracedLlmCall;
use App\Contracts\Ai\AiClientInterface;
use App\Contracts\Ai\LexemeExplanationCapability;
use App\Contracts\Ai\PromptRegistryInterface;
use Illuminate\Support\Facades\Cache;

final class LexemeExplanationService implements LexemeExplanationCapability
{
    public function __construct(
        private readonly AiClientInterface $client,
        private readonly PromptRegistryInterface $promptRegistry,
        private readonly TracedLlmCall $tracedCall,
    ) {}

    /**
     * @throws AiClientException
     */
    public function explain(string $lexemeText, ?string $language = null, ?int $contentLexemeId = null): string
    {
        $cacheKey = $contentLexemeId !== null ? $this->cacheKey($contentLexemeId, $language) : null;
        if ($cacheKey !== null && config('ai.explain_cache_enabled', true)) {
            $cached = Cache::get($cacheKey);
            if (is_string($cached)) {
                return $cached;
            }
        }

        $rendered = $this->promptRegistry->resolve(
            'ai_explain_lexeme',
            ['lexeme' => $lexemeText, 'language' => $language ?? ''],
            fn () => [
                'system' => 'You are a language tutor. Give a short, clear explanation or one example sentence for the given word or phrase. Keep the answer concise.',
                'user' => $language
                    ? "Word/phrase: \"{$lexemeText}\". Target language: {$language}. Explain briefly."
                    : "Word/phrase: \"{$lexemeText}\". Explain briefly.",
            ]
        );

        $compute = function () use ($contentLexemeId, $rendered): string {
            $response = $this->tracedCall->complete(
                $this->client,
                TraceContext::newTrace(),
                'explain_lexeme.complete',
                ['feature' => 'explain_lexeme', 'content_lexeme_id' => $contentLexemeId],
                $rendered->system,
                $rendered->user,
                $rendered->model,
            );

            return trim($response);
        };

        if ($cacheKey !== null && config('ai.explain_cache_enabled', true)) {
            return Cache::remember(
                $cacheKey,
                (int) config('ai.explain_cache_ttl_seconds', 604800),
                $compute,
            );
        }

        return $compute();
    }

    private function cacheKey(int $contentLexemeId, ?string $language): string
    {
        return "ai:explain:{$contentLexemeId}:".($language ?? '');
    }
}
