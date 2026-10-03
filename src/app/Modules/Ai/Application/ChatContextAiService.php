<?php

namespace App\Modules\Ai\Application;

use App\Exceptions\AiClientException;
use App\Modules\Ai\Application\Agent\Tracing\TraceContext;
use App\Modules\Ai\Application\Agent\Tracing\TracedLlmCall;
use App\Contracts\Ai\AiClientInterface;
use App\Contracts\Ai\ChatAiServiceInterface;
use App\Contracts\Ai\PromptRegistryInterface;

class ChatContextAiService implements ChatAiServiceInterface
{
    private const MAX_HISTORY_MESSAGES = 20;

    /**
     * Task 5.4: semantic cache scope for this service's first-turn
     * questions — see `reply()` for why only first-turn.
     */
    private const CACHE_SCOPE = 'chat_context_first_turn';

    public function __construct(
        private readonly AiClientInterface $client,
        private readonly SemanticCacheService $semanticCache,
        private readonly TracedLlmCall $tracedCall,
        private readonly PromptRegistryInterface $promptRegistry,
    ) {}

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     *
     * @throws AiClientException
     */
    public function reply(array $history, string $newUserMessage): string
    {
        $history = array_slice($history, -self::MAX_HISTORY_MESSAGES);

        $respond = function () use ($history, $newUserMessage): string {
            $rendered = $this->promptRegistry->resolve(
                'chat_context_system_prompt',
                [],
                fn () => [
                    'system' => 'You are a language learning assistant. Help the user with vocabulary, grammar, and practice. Keep replies concise and helpful.',
                    'user' => '',
                ]
            );

            $parts = [];
            foreach ($history as $msg) {
                $role = $msg['role'] === 'assistant' ? 'Assistant' : 'User';
                $parts[] = $role.': '.$msg['content'];
            }
            $parts[] = 'User: '.$newUserMessage;
            $parts[] = 'Assistant:';

            $userPrompt = implode("\n", $parts);

            return trim($this->tracedCall->complete(
                $this->client,
                TraceContext::newTrace(),
                'chat_context.complete',
                ['feature' => 'chat_context'],
                $rendered->system,
                $userPrompt,
                $rendered->model,
            ));
        };

        // Semantic cache (task 5.4) only ever applies to the first turn of a
        // conversation (no prior history): once there is history, the
        // "same" question can have a genuinely different correct answer
        // depending on what was already said, and this cache only knows the
        // embedding of the latest question — it has no way to also match on
        // conversation context. Caching anyway would risk silently ignoring
        // that context and returning a stale/wrong answer, which defeats
        // the point of a chat that is supposed to be conversational.
        if ($history === []) {
            return $this->semanticCache->remember(self::CACHE_SCOPE, $newUserMessage, $respond);
        }

        return $respond();
    }
}
