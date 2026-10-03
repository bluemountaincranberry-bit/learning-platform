<?php

namespace App\Modules\Ai\Infrastructure;

use App\Exceptions\AiClientException;
use App\Contracts\Ai\AiClientInterface;
use App\Contracts\Ai\AiJsonClient;
use Illuminate\Support\Facades\Http;

class OllamaClient implements AiClientInterface, AiJsonClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $model = 'llama3.2',
        private readonly int $timeout = 30
    ) {}

    public function complete(string $systemPrompt, string $userPrompt, ?string $model = null): string
    {
        $url = rtrim($this->baseUrl, '/').'/api/chat';

        $response = Http::retry(3, 150, throw: false)->timeout($this->timeout)
            ->post($url, [
                'model' => $model ?? $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ]);

        if ($response->failed()) {
            $message = $response->reason();
            throw new AiClientException("Ollama API error: {$message}", $response->status());
        }

        $content = $response->json('message.content');
        if (! is_string($content)) {
            throw new AiClientException('Ollama API returned invalid response.');
        }

        return $content;
    }

    public function completeJson(string $systemPrompt, string $userPrompt, array $schema = [], ?string $model = null): array
    {
        $url = rtrim($this->baseUrl, '/').'/api/chat';

        $response = Http::retry(3, 150, throw: false)->timeout($this->timeout)
            ->post($url, [
                'model' => $model ?? $this->model,
                'format' => 'json',
                'messages' => [
                    ['role' => 'system', 'content' => $this->withSchemaInstruction($systemPrompt, $schema)],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ]);

        if ($response->failed()) {
            $message = $response->reason();
            throw new AiClientException("Ollama API error: {$message}", $response->status());
        }

        $content = $response->json('message.content');
        if (! is_string($content)) {
            throw new AiClientException('Ollama API returned invalid response.');
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            throw new AiClientException('Ollama API returned invalid JSON.');
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function withSchemaInstruction(string $systemPrompt, array $schema): string
    {
        if ($schema === []) {
            return $systemPrompt;
        }

        return $systemPrompt."\n\nRespond with a single JSON object matching this shape: ".json_encode($schema);
    }
}
