<?php

namespace App\Modules\Ai\Application\Agent;

use App\Exceptions\AgentToolException;
use App\Modules\Ai\Application\Agent\Contracts\AgentLoopObserver;
use App\Modules\Ai\Application\Agent\Contracts\AgentTool;
use App\Modules\Ai\Application\Agent\Data\AgentToolCall;
use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Tracing\SpanRecorder;
use App\Modules\Ai\Application\Agent\Tracing\TraceContext;
use App\Contracts\Ai\AiStreamingChatClient;
use App\Contracts\Ai\AiToolCallingClient;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pure tool-calling loop: "ask the model -> run any requested tools -> repeat
 * until a final text reply or the iteration limit". Deliberately knows
 * nothing about Eloquent, HTTP, or which agent/conversation is calling it —
 * that context is handed in (`AgentToolContext`) or reacted to via the
 * `AgentLoopObserver` passed by the caller. Every concrete agent
 * (`ContentAgentService`, `StudentTutorAgentService`, ...) is a thin wrapper
 * around this: it loads its own history, builds its own system prompt/tool
 * list, and supplies an observer that persists what happens.
 *
 * Extracted from `ContentAgentService::handleTurn()` (see
 * `docs/architecture/agent-framework-roadmap.md`, step 5.2) so the same
 * engine can be reused by a second agent without duplicating the loop.
 */
final class AgentLoop
{
    /**
     * @param  AiStreamingChatClient|null  $streamingClient  Resolved by the container when the configured provider supports streaming (task 3.6); null keeps `run()` fully usable without it. `runStreaming()` requires it.
     */
    public function __construct(
        private readonly AiToolCallingClient $client,
        private readonly ?AiStreamingChatClient $streamingClient = null,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $startingMessages  Prior conversation turns (user/assistant/tool), system prompt excluded — this method prepends it.
     * @param  array<int, AgentTool>  $tools
     * @param  array<string, mixed>  $turnMetadata  Extra metadata merged into the root agent_turn span (e.g. ['agent_type' => 'content_authoring']), for reporting — AgentLoop itself has no notion of "which agent" this is.
     */
    public function run(
        string $systemPrompt,
        array $startingMessages,
        array $tools,
        int $maxIterations,
        AgentToolContext $context,
        AgentLoopObserver $observer,
        TraceContext $trace,
        SpanRecorder $spanRecorder,
        array $turnMetadata = [],
    ): void {
        $turnSpanId = $spanRecorder->startSpan(
            $trace,
            'agent_turn',
            'agent_loop.run',
            [...$turnMetadata, 'max_iterations' => $maxIterations]
        );
        $childTrace = $trace->withParentSpan($turnSpanId);
        $turnStatus = 'error';

        try {
            $toolsByName = [];
            foreach ($tools as $tool) {
                $toolsByName[$tool->definition()->name] = $tool;
            }
            $toolDefinitions = array_map(fn (AgentTool $tool) => $tool->definition(), array_values($toolsByName));

            $liveMessages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ...$startingMessages,
            ];

            for ($iteration = 0; $iteration < $maxIterations; $iteration++) {
                $chatSpanId = $spanRecorder->startSpan($childTrace, 'llm_call', 'agent_loop.chat', ['iteration' => $iteration]);

                try {
                    $response = $this->client->chat($liveMessages, $toolDefinitions);
                } catch (Throwable $e) {
                    $spanRecorder->endSpan($chatSpanId, 'error', ['error' => $e->getMessage()]);
                    throw $e;
                }

                $spanRecorder->endSpan($chatSpanId, 'ok', [
                    'prompt_tokens' => $response->promptTokens,
                    'completion_tokens' => $response->completionTokens,
                ]);

                if (! $response->hasToolCalls()) {
                    $observer->onFinalResponse($response);
                    $turnStatus = 'ok';

                    return;
                }

                $liveMessages[] = [
                    'role' => 'assistant',
                    'content' => null,
                    'tool_calls' => array_map(fn (AgentToolCall $call) => [
                        'id' => $call->id,
                        'type' => 'function',
                        'function' => [
                            'name' => $call->name,
                            'arguments' => json_encode($call->arguments),
                        ],
                    ], $response->toolCalls),
                ];

                foreach ($response->toolCalls as $toolCall) {
                    $toolSpanId = $spanRecorder->startSpan($childTrace, 'tool_call', $toolCall->name);
                    $observer->onToolCallStarted($toolCall);

                    $startedAt = microtime(true);
                    $result = $this->runTool($toolCall, $toolsByName, $context);
                    $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

                    $spanRecorder->endSpan($toolSpanId, isset($result['error']) ? 'error' : 'ok', ['latency_ms' => $latencyMs]);
                    $observer->onToolCallCompleted($toolCall, $result, $latencyMs);

                    $liveMessages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $toolCall->id,
                        'content' => json_encode($result),
                    ];
                }
            }

            $observer->onIterationLimitReached();
        } finally {
            $spanRecorder->endSpan($turnSpanId, $turnStatus);
        }
    }

    /**
     * Same loop as `run()`, but each iteration's model call streams text
     * deltas to `$onDelta` as they arrive instead of returning only a final
     * `AgentChatResponse` (task 3.6). Tool-calling iterations naturally
     * produce no content deltas (the model isn't replying yet, it's asking
     * for a tool), so callers do not need to special-case them — `$onDelta`
     * is simply invoked zero or more times per iteration, whatever the model
     * actually streamed back. Kept as a separate method rather than a flag on
     * `run()` so the well-tested non-streaming path (`ContentAgentService`,
     * every existing `AgentLoop` test) is untouched.
     *
     * @param  array<int, array<string, mixed>>  $startingMessages
     * @param  array<int, AgentTool>  $tools
     * @param  array<string, mixed>  $turnMetadata
     * @param  callable(string): void  $onDelta
     */
    public function runStreaming(
        string $systemPrompt,
        array $startingMessages,
        array $tools,
        int $maxIterations,
        AgentToolContext $context,
        AgentLoopObserver $observer,
        TraceContext $trace,
        SpanRecorder $spanRecorder,
        callable $onDelta,
        array $turnMetadata = [],
    ): void {
        if ($this->streamingClient === null) {
            throw new \RuntimeException(
                'AgentLoop::runStreaming() requires a streaming-capable client (AiStreamingChatClient) — none was wired in.'
            );
        }

        $turnSpanId = $spanRecorder->startSpan(
            $trace,
            'agent_turn',
            'agent_loop.run_streaming',
            [...$turnMetadata, 'max_iterations' => $maxIterations]
        );
        $childTrace = $trace->withParentSpan($turnSpanId);
        $turnStatus = 'error';

        try {
            $toolsByName = [];
            foreach ($tools as $tool) {
                $toolsByName[$tool->definition()->name] = $tool;
            }
            $toolDefinitions = array_map(fn (AgentTool $tool) => $tool->definition(), array_values($toolsByName));

            $liveMessages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ...$startingMessages,
            ];

            for ($iteration = 0; $iteration < $maxIterations; $iteration++) {
                $chatSpanId = $spanRecorder->startSpan($childTrace, 'llm_call', 'agent_loop.chat_stream', ['iteration' => $iteration]);

                try {
                    $response = $this->streamingClient->chatStream($liveMessages, $toolDefinitions, $onDelta);
                } catch (Throwable $e) {
                    $spanRecorder->endSpan($chatSpanId, 'error', ['error' => $e->getMessage()]);
                    throw $e;
                }

                $spanRecorder->endSpan($chatSpanId, 'ok', [
                    'prompt_tokens' => $response->promptTokens,
                    'completion_tokens' => $response->completionTokens,
                ]);

                if (! $response->hasToolCalls()) {
                    $observer->onFinalResponse($response);
                    $turnStatus = 'ok';

                    return;
                }

                $liveMessages[] = [
                    'role' => 'assistant',
                    'content' => null,
                    'tool_calls' => array_map(fn (AgentToolCall $call) => [
                        'id' => $call->id,
                        'type' => 'function',
                        'function' => [
                            'name' => $call->name,
                            'arguments' => json_encode($call->arguments),
                        ],
                    ], $response->toolCalls),
                ];

                foreach ($response->toolCalls as $toolCall) {
                    $toolSpanId = $spanRecorder->startSpan($childTrace, 'tool_call', $toolCall->name);
                    $observer->onToolCallStarted($toolCall);

                    $startedAt = microtime(true);
                    $result = $this->runTool($toolCall, $toolsByName, $context);
                    $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

                    $spanRecorder->endSpan($toolSpanId, isset($result['error']) ? 'error' : 'ok', ['latency_ms' => $latencyMs]);
                    $observer->onToolCallCompleted($toolCall, $result, $latencyMs);

                    $liveMessages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $toolCall->id,
                        'content' => json_encode($result),
                    ];
                }
            }

            $observer->onIterationLimitReached();
        } finally {
            $spanRecorder->endSpan($turnSpanId, $turnStatus);
        }
    }

    /**
     * @param  array<string, AgentTool>  $toolsByName
     * @return array<string, mixed>
     */
    private function runTool(AgentToolCall $call, array $toolsByName, AgentToolContext $context): array
    {
        $tool = $toolsByName[$call->name] ?? null;

        if (! $tool) {
            return ['error' => "Unknown tool: {$call->name}"];
        }

        try {
            return $tool->execute($call->arguments, $context);
        } catch (AgentToolException $e) {
            return ['error' => $e->getMessage()];
        } catch (Throwable $e) {
            Log::error('Agent tool execution failed', [
                'tool' => $call->name,
                'conversation_id' => $context->conversationId,
                'message' => $e->getMessage(),
            ]);

            return ['error' => 'Internal error running this tool.'];
        }
    }
}
