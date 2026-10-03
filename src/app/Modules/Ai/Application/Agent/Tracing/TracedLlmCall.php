<?php

namespace App\Modules\Ai\Application\Agent\Tracing;

use App\Exceptions\AiClientException;
use App\Contracts\Ai\AiClientInterface;
use App\Contracts\Ai\AiJsonClient;
use App\Contracts\Ai\AiUsageReportingClient;
use Closure;

/**
 * Shared `llm_call` span wrapping for the "classic" direct-call AI
 * services (AiContentAnalysisService pioneered this shape in
 * `completeJsonTraced()`; this class is the same logic pulled out so the
 * other six services don't each hand-roll their own copy of it). A
 * service still owns its own `AiClientInterface`/`AiJsonClient` instance
 * and prompt-building — this only wraps the *call*: start a span, run it,
 * capture usage/model via `AiUsageReportingClient` when the client
 * supports it, end the span `ok` or `error`.
 */
final class TracedLlmCall
{
    public function __construct(private readonly SpanRecorder $spanRecorder) {}

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     *
     * @throws AiClientException
     */
    public function completeJson(
        AiJsonClient $client,
        TraceContext $trace,
        string $spanName,
        array $metadata,
        string $systemPrompt,
        string $userPrompt,
        array $schema = [],
        ?string $model = null,
    ): array {
        return $this->run($client, $trace, $spanName, $metadata, fn () => $client->completeJson($systemPrompt, $userPrompt, $schema, $model));
    }

    /**
     * @param  array<string, mixed>  $metadata
     *
     * @throws AiClientException
     */
    public function complete(
        AiClientInterface $client,
        TraceContext $trace,
        string $spanName,
        array $metadata,
        string $systemPrompt,
        string $userPrompt,
        ?string $model = null,
    ): string {
        return $this->run($client, $trace, $spanName, $metadata, fn () => $client->complete($systemPrompt, $userPrompt, $model));
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  Closure(): (string|array<string, mixed>)  $call
     *
     * @throws AiClientException
     */
    private function run(AiClientInterface|AiJsonClient $client, TraceContext $trace, string $spanName, array $metadata, Closure $call): mixed
    {
        $spanId = $this->spanRecorder->startSpan($trace, 'llm_call', $spanName, $metadata);

        try {
            $result = $call();
        } catch (AiClientException $e) {
            $this->spanRecorder->endSpan($spanId, 'error', ['error' => $e->getMessage()]);

            throw $e;
        }

        $usage = $client instanceof AiUsageReportingClient ? $client->lastUsage() : null;
        $model = $client instanceof AiUsageReportingClient ? $client->lastModel() : null;

        $this->spanRecorder->endSpan($spanId, 'ok', [
            'prompt_tokens' => $usage?->promptTokens,
            'completion_tokens' => $usage?->completionTokens,
            'model' => $model,
        ]);

        return $result;
    }
}
