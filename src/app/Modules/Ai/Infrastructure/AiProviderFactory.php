<?php

namespace App\Modules\Ai\Infrastructure;

use App\Contracts\Ai\EmbeddingsClientInterface;

/**
 * Owns provider selection and construction for the AI infrastructure layer.
 */
final class AiProviderFactory
{
    public function __construct(
        private readonly string $provider,
        private readonly string $openAiApiKey,
        private readonly string $ollamaUrl,
        private readonly string $ollamaModel,
        private readonly int $timeout,
        private readonly string $embeddingsModel,
        private readonly string $embeddingsProvider = 'openai',
    ) {}

    public function makeChatClient(): OpenAiClient|OllamaClient
    {
        if ($this->provider === 'ollama') {
            return new OllamaClient($this->ollamaUrl, $this->ollamaModel, $this->timeout);
        }

        return new OpenAiClient($this->openAiApiKey, $this->timeout);
    }

    public function makeEmbeddingsClient(): EmbeddingsClientInterface
    {
        if ($this->embeddingsProvider === 'ollama') {
            return new OllamaEmbeddingsClient($this->ollamaUrl, $this->embeddingsModel, $this->timeout);
        }

        return new OpenAiEmbeddingsClient($this->openAiApiKey, $this->embeddingsModel, $this->timeout);
    }
}
