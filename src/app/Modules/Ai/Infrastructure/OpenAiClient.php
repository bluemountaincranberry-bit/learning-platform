<?php

namespace App\Modules\Ai\Infrastructure;

use App\Exceptions\AiClientException;
use App\Modules\Ai\Application\Agent\Data\AgentChatResponse;
use App\Modules\Ai\Application\Agent\Data\AgentToolCall;
use App\Modules\Ai\Application\Data\TokenUsage;
use App\Contracts\Ai\AiClientInterface;
use App\Contracts\Ai\AiJsonClient;
use App\Contracts\Ai\AiStreamingChatClient;
use App\Contracts\Ai\AiToolCallingClient;
use App\Contracts\Ai\AiUsageReportingClient;
use Illuminate\Support\Facades\Http;

class OpenAiClient implements AiClientInterface, AiJsonClient, AiToolCallingClient, AiStreamingChatClient, AiUsageReportingClient
{
    private ?TokenUsage $lastUsage = null;

    private ?string $lastModel = null;

    private const DEFAULT_MODEL = 'gpt-4o-mini';

    public function __construct(
        private readonly string $apiKey,
        private readonly int $timeout = 30
    ) {}

    public function lastUsage(): ?TokenUsage
    {
        return $this->lastUsage;
    }

    public function lastModel(): ?string
    {
        return $this->lastModel;
    }

    public function complete(string $systemPrompt, string $userPrompt, ?string $model = null): string
    {
        $key = $this->apiKey;
        if ($key === '' || $key === null) {
            throw new AiClientException('OpenAI API key is not configured.');
        }

        $resolvedModel = $model ?? self::DEFAULT_MODEL;

        $response = Http::withToken($key)
            ->retry(3, 150, throw: false)
            ->timeout($this->timeout)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $resolvedModel,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ]);

        if ($response->failed()) {
            $body = $response->json();
            $message = $body['error']['message'] ?? $response->reason();
            throw new AiClientException("OpenAI API error: {$message}", $response->status());
        }

        $this->captureUsage($response, $resolvedModel);

        $content = $response->json('choices.0.message.content');
        if (! is_string($content)) {
            throw new AiClientException('OpenAI API returned invalid response.');
        }

        return $content;
    }

    public function completeJson(string $systemPrompt, string $userPrompt, array $schema = [], ?string $model = null): array
    {
        $key = $this->apiKey;
        if ($key === '' || $key === null) {
            throw new AiClientException('OpenAI API key is not configured.');
        }

        $resolvedModel = $model ?? self::DEFAULT_MODEL;

        $response = Http::withToken($key)
            ->retry(3, 150, throw: false)
            ->timeout($this->timeout)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $resolvedModel,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $this->withSchemaInstruction($systemPrompt, $schema)],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ]);

        if ($response->failed()) {
            $body = $response->json();
            $message = $body['error']['message'] ?? $response->reason();
            throw new AiClientException("OpenAI API error: {$message}", $response->status());
        }

        $this->captureUsage($response, $resolvedModel);

        $content = $response->json('choices.0.message.content');
        if (! is_string($content)) {
            throw new AiClientException('OpenAI API returned invalid response.');
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            throw new AiClientException('OpenAI API returned invalid JSON.');
        }

        return $decoded;
    }

    /**
     * Reads `usage.prompt_tokens`/`usage.completion_tokens` from a
     * successful chat/completions response into `$lastUsage` — called by
     * both `complete()` and `completeJson()` right after the failure
     * check, before either parses `choices.0.message.content`, so
     * `lastUsage()` reflects the call even if the caller's own
     * content/JSON validation rejects the response afterwards.
     */
    private function captureUsage(\Illuminate\Http\Client\Response $response, string $resolvedModel): void
    {
        $promptTokens = $response->json('usage.prompt_tokens');
        $completionTokens = $response->json('usage.completion_tokens');

        $this->lastUsage = ($promptTokens === null && $completionTokens === null)
            ? null
            : new TokenUsage(
                is_int($promptTokens) ? $promptTokens : null,
                is_int($completionTokens) ? $completionTokens : null,
            );
        $this->lastModel = $resolvedModel;
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, \App\Modules\Ai\Application\Agent\Data\AgentToolDefinition>  $tools
     */
    public function chat(array $messages, array $tools): AgentChatResponse
    {
        $key = $this->apiKey;
        if ($key === '' || $key === null) {
            throw new AiClientException('OpenAI API key is not configured.');
        }

        $payload = [
            'model' => 'gpt-4o-mini',
            'messages' => $messages,
        ];

        if ($tools !== []) {
            $payload['tools'] = array_map(fn ($tool) => $tool->toOpenAiFormat(), $tools);
        }

        $response = Http::withToken($key)
            ->timeout($this->timeout)
            ->post('https://api.openai.com/v1/chat/completions', $payload);

        if ($response->failed()) {
            $body = $response->json();
            $message = $body['error']['message'] ?? $response->reason();
            throw new AiClientException("OpenAI API error: {$message}", $response->status());
        }

        $message = $response->json('choices.0.message');
        if (! is_array($message)) {
            throw new AiClientException('OpenAI API returned invalid response.');
        }

        $toolCalls = [];
        foreach ($message['tool_calls'] ?? [] as $rawCall) {
            if (! is_array($rawCall) || ! is_array($rawCall['function'] ?? null)) {
                continue;
            }

            $arguments = json_decode((string) ($rawCall['function']['arguments'] ?? ''), true);

            $toolCalls[] = new AgentToolCall(
                id: (string) ($rawCall['id'] ?? ''),
                name: (string) ($rawCall['function']['name'] ?? ''),
                arguments: is_array($arguments) ? $arguments : [],
            );
        }

        return new AgentChatResponse(
            content: is_string($message['content'] ?? null) ? $message['content'] : null,
            toolCalls: $toolCalls,
            promptTokens: $response->json('usage.prompt_tokens'),
            completionTokens: $response->json('usage.completion_tokens'),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, \App\Modules\Ai\Application\Agent\Data\AgentToolDefinition>  $tools
     * @param  callable(string): void  $onDelta
     */
    public function chatStream(array $messages, array $tools, callable $onDelta): AgentChatResponse
    {
        $key = $this->apiKey;
        if ($key === '' || $key === null) {
            throw new AiClientException('OpenAI API key is not configured.');
        }

        $payload = [
            'model' => 'gpt-4o-mini',
            'messages' => $messages,
            'stream' => true,
            // Usage is omitted from every delta chunk by default in
            // streaming mode; this opts a final usage-only chunk back in so
            // token accounting (AgentChatResponse::promptTokens/
            // completionTokens) still works the same as the non-streaming chat().
            'stream_options' => ['include_usage' => true],
        ];

        if ($tools !== []) {
            $payload['tools'] = array_map(fn ($tool) => $tool->toOpenAiFormat(), $tools);
        }

        $response = Http::withToken($key)
            ->withOptions(['stream' => true])
            ->timeout($this->timeout)
            ->post('https://api.openai.com/v1/chat/completions', $payload);

        if ($response->failed()) {
            $body = $response->json();
            $message = $body['error']['message'] ?? $response->reason();
            throw new AiClientException("OpenAI API error: {$message}", $response->status());
        }

        return $this->consumeSse($response->toPsrResponse()->getBody(), $onDelta);
    }

    /**
     * Parses an OpenAI chat-completions SSE body ("data: {...}\n\n" events,
     * terminated by "data: [DONE]\n\n"), forwarding assistant text deltas to
     * `$onDelta` as they arrive and accumulating tool-call deltas (OpenAI
     * streams each tool call's name/arguments in fragments, indexed by
     * position — see `delta.tool_calls[].index`) into complete `AgentToolCall`s.
     *
     * @param  callable(string): void  $onDelta
     */
    private function consumeSse(\Psr\Http\Message\StreamInterface $body, callable $onDelta): AgentChatResponse
    {
        $buffer = '';
        $content = '';
        $toolCallChunks = [];
        $promptTokens = null;
        $completionTokens = null;

        while (! $body->eof()) {
            $buffer .= $body->read(8192);

            while (($eventEnd = strpos($buffer, "\n\n")) !== false) {
                $event = substr($buffer, 0, $eventEnd);
                $buffer = substr($buffer, $eventEnd + 2);

                foreach (explode("\n", $event) as $line) {
                    if (! str_starts_with($line, 'data:')) {
                        continue;
                    }

                    $data = trim(substr($line, 5));
                    if ($data === '' || $data === '[DONE]') {
                        continue;
                    }

                    $chunk = json_decode($data, true);
                    if (! is_array($chunk)) {
                        continue;
                    }

                    if (isset($chunk['usage']['prompt_tokens'])) {
                        $promptTokens = (int) $chunk['usage']['prompt_tokens'];
                        $completionTokens = (int) ($chunk['usage']['completion_tokens'] ?? 0);
                    }

                    $delta = $chunk['choices'][0]['delta'] ?? null;
                    if (! is_array($delta)) {
                        continue;
                    }

                    if (is_string($delta['content'] ?? null) && $delta['content'] !== '') {
                        $content .= $delta['content'];
                        $onDelta($delta['content']);
                    }

                    foreach ($delta['tool_calls'] ?? [] as $toolCallDelta) {
                        if (! is_array($toolCallDelta)) {
                            continue;
                        }

                        $index = (int) ($toolCallDelta['index'] ?? 0);
                        $toolCallChunks[$index] ??= ['id' => null, 'name' => '', 'arguments' => ''];

                        if (isset($toolCallDelta['id'])) {
                            $toolCallChunks[$index]['id'] = (string) $toolCallDelta['id'];
                        }
                        if (isset($toolCallDelta['function']['name'])) {
                            $toolCallChunks[$index]['name'] .= (string) $toolCallDelta['function']['name'];
                        }
                        if (isset($toolCallDelta['function']['arguments'])) {
                            $toolCallChunks[$index]['arguments'] .= (string) $toolCallDelta['function']['arguments'];
                        }
                    }
                }
            }
        }

        ksort($toolCallChunks);
        $toolCalls = [];
        foreach ($toolCallChunks as $chunk) {
            $arguments = json_decode($chunk['arguments'], true);
            $toolCalls[] = new AgentToolCall(
                id: (string) ($chunk['id'] ?? ''),
                name: $chunk['name'],
                arguments: is_array($arguments) ? $arguments : [],
            );
        }

        return new AgentChatResponse(
            content: $content !== '' ? $content : null,
            toolCalls: $toolCalls,
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
        );
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
