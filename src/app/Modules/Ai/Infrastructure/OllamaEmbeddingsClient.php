<?php

namespace App\Modules\Ai\Infrastructure;

use App\Exceptions\AiClientException;
use App\Contracts\Ai\EmbeddingsClientInterface;
use Illuminate\Support\Facades\Http;

class OllamaEmbeddingsClient implements EmbeddingsClientInterface
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $model,
        private readonly int $timeout = 30,
    ) {}

    public function embed(string $text): array
    {
        $embeddings = $this->embedBatch([$text]);

        return $embeddings[0] ?? throw new AiClientException('Ollama embeddings API returned no embedding.');
    }

    public function embedBatch(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        $response = Http::retry(3, 150, throw: false)->timeout($this->timeout)->post(rtrim($this->baseUrl, '/').'/api/embed', [
            'model' => $this->model,
            'input' => array_values($texts),
        ]);

        if ($response->failed()) {
            throw new AiClientException('Ollama embeddings API error: '.$response->reason(), $response->status());
        }

        $embeddings = $response->json('embeddings');
        if (! is_array($embeddings) || count($embeddings) !== count($texts)) {
            throw new AiClientException('Ollama embeddings API returned an unexpected number of results.');
        }

        return array_map(function ($embedding): array {
            if (! is_array($embedding)) {
                throw new AiClientException('Ollama embeddings API returned an invalid embedding.');
            }

            return array_map(fn ($value) => (float) $value, $embedding);
        }, $embeddings);
    }
}
