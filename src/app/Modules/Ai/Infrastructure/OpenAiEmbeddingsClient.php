<?php

namespace App\Modules\Ai\Infrastructure;

use App\Exceptions\AiClientException;
use App\Contracts\Ai\EmbeddingsClientInterface;
use Illuminate\Support\Facades\Http;

class OpenAiEmbeddingsClient implements EmbeddingsClientInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly int $timeout = 30
    ) {
    }

    public function embed(string $text): array
    {
        if ($this->apiKey === '' || $this->apiKey === null) {
            throw new AiClientException('OpenAI API key is not configured.');
        }

        $response = Http::withToken($this->apiKey)
            ->timeout($this->timeout)
            ->post('https://api.openai.com/v1/embeddings', [
                'model' => $this->model,
                'input' => $text,
            ]);

        if ($response->failed()) {
            $body = $response->json();
            $message = $body['error']['message'] ?? $response->reason();
            throw new AiClientException("OpenAI embeddings API error: {$message}", $response->status());
        }

        $embedding = $response->json('data.0.embedding');
        if (! is_array($embedding)) {
            throw new AiClientException('OpenAI embeddings API returned invalid response.');
        }

        return array_map(fn ($v) => (float) $v, $embedding);
    }

    /**
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>>
     */
    public function embedBatch(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        if ($this->apiKey === '' || $this->apiKey === null) {
            throw new AiClientException('OpenAI API key is not configured.');
        }

        $response = Http::withToken($this->apiKey)
            ->timeout($this->timeout)
            ->post('https://api.openai.com/v1/embeddings', [
                'model' => $this->model,
                'input' => array_values($texts),
            ]);

        if ($response->failed()) {
            $body = $response->json();
            $message = $body['error']['message'] ?? $response->reason();
            throw new AiClientException("OpenAI embeddings API error: {$message}", $response->status());
        }

        $data = $response->json('data');
        if (! is_array($data) || count($data) !== count($texts)) {
            throw new AiClientException('OpenAI embeddings API returned an unexpected number of results.');
        }

        // The API returns items tagged with their input `index`, not
        // necessarily in request order — sort by it so the result lines up
        // positionally with $texts regardless.
        usort($data, fn ($a, $b) => ($a['index'] ?? 0) <=> ($b['index'] ?? 0));

        return array_map(
            fn ($item) => is_array($item['embedding'] ?? null)
                ? array_map(fn ($v) => (float) $v, $item['embedding'])
                : throw new AiClientException('OpenAI embeddings API returned invalid response.'),
            $data
        );
    }
}
