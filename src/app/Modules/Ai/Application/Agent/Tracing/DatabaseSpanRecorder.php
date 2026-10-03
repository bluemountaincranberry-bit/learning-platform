<?php

namespace App\Modules\Ai\Application\Agent\Tracing;

use App\Modules\Ai\Domain\Models\AgentTrace;
use App\Modules\Ai\Domain\Models\AgentTraceSpan;
use App\Modules\Ai\Domain\Models\ModelPricing;
use Illuminate\Support\Str;

/**
 * Persists spans to `agent_trace_spans` (task 1.7). Writes on both
 * `startSpan()` and `endSpan()` — a span that starts but never ends (job
 * crash, process killed) still shows up with `ended_at = null`, which is
 * itself useful signal, rather than being silently lost until the turn
 * finishes.
 *
 * Task 5.2: also computes `cost_usd` for `llm_call` spans (fixed at the
 * price in `config('ai.pricing')` when the call happened, not recomputed
 * later) and, when the span that just closed is a trace's root
 * `agent_turn` (`parent_span_id === null`), upserts the denormalized
 * `agent_traces` summary row for that whole trace — see
 * `upsertTraceSummary()`.
 */
final class DatabaseSpanRecorder implements SpanRecorder
{
    public function startSpan(TraceContext $context, string $spanType, string $name, array $metadata = []): string
    {
        $spanId = (string) Str::uuid();

        AgentTraceSpan::query()->create([
            'trace_id' => $context->traceId,
            'span_id' => $spanId,
            'parent_span_id' => $context->parentSpanId,
            'span_type' => $spanType,
            'name' => $name,
            'started_at' => now(),
            'metadata' => $metadata,
        ]);

        return $spanId;
    }

    public function endSpan(string $spanId, string $status, array $metadata = []): void
    {
        $span = AgentTraceSpan::query()->where('span_id', $spanId)->first();

        if (! $span) {
            // Tracing must never break the agent turn itself — if the span
            // row is somehow gone, just drop the update.
            return;
        }

        $endedAt = now();
        $extraMetadata = array_diff_key($metadata, array_flip(['prompt_tokens', 'completion_tokens', 'model']));
        $promptTokens = $metadata['prompt_tokens'] ?? $span->prompt_tokens;
        $completionTokens = $metadata['completion_tokens'] ?? $span->completion_tokens;
        $model = $metadata['model'] ?? $span->model;

        $span->update([
            'ended_at' => $endedAt,
            'duration_ms' => (int) $span->started_at->diffInMilliseconds($endedAt),
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'model' => $model,
            'cost_usd' => $span->span_type === AgentTraceSpan::SPAN_TYPE_LLM_CALL
                ? $this->calculateCostUsd($promptTokens, $completionTokens, $model)
                : $span->cost_usd,
            'status' => $status,
            'metadata' => [...($span->metadata ?? []), ...$extraMetadata],
        ]);

        if ($span->span_type === AgentTraceSpan::SPAN_TYPE_AGENT_TURN && $span->parent_span_id === null) {
            $this->upsertTraceSummary($span->fresh());
        }
    }

    /**
     * Per-model price from `model_pricing` when a row exists for $model,
     * else `config('ai.pricing')` — same DB-override/code-fallback shape
     * PromptRegistryService/GraphDefinitionResolver already use elsewhere
     * in this module. Closes the "assumes the single model currently in
     * use" gap this method's docblock used to flag: a span now carries
     * which model actually served it (`OpenAiClient::lastModel()`), so
     * mixing models no longer silently mis-prices anything that has a
     * `model_pricing` row.
     */
    private function calculateCostUsd(?int $promptTokens, ?int $completionTokens, ?string $model): ?float
    {
        if ($promptTokens === null && $completionTokens === null) {
            return null;
        }

        $pricing = $model !== null ? ModelPricing::query()->where('model', $model)->first() : null;

        $promptPrice = $pricing?->prompt_per_1k_usd ?? (float) config('ai.pricing.prompt_per_1k_usd', 0);
        $completionPrice = $pricing?->completion_per_1k_usd ?? (float) config('ai.pricing.completion_per_1k_usd', 0);

        return (($promptTokens ?? 0) / 1000 * $promptPrice) + (($completionTokens ?? 0) / 1000 * $completionPrice);
    }

    /**
     * Writes/updates the `agent_traces` row for the trace this root span
     * belongs to. Called only when the just-closed span is a trace's root
     * `agent_turn` (top-level `ContentAgentService`/`StudentTutorAgentService`
     * turn — a nested agent_turn opened by a handoff has a non-null
     * `parent_span_id` and is deliberately not treated as a root here, since
     * it shares the same `trace_id` as its parent turn and its cost is
     * already included in the sum below).
     *
     * Every span nested under this trace (llm_call, tool_call, handoff,
     * nested agent_turn) has already closed by the time the root closes —
     * `AgentLoop::run()`'s try/finally always ends inner spans before the
     * outer one — so summing `cost_usd` across the whole trace here is
     * complete, not a partial snapshot.
     */
    private function upsertTraceSummary(AgentTraceSpan $rootSpan): void
    {
        $totalCostUsd = AgentTraceSpan::query()
            ->where('trace_id', $rootSpan->trace_id)
            ->sum('cost_usd');

        AgentTrace::query()->updateOrCreate(
            ['trace_id' => $rootSpan->trace_id],
            [
                'entry_agent_type' => $rootSpan->metadata['agent_type'] ?? null,
                'started_at' => $rootSpan->started_at,
                'total_duration_ms' => $rootSpan->duration_ms,
                'total_cost_usd' => $totalCostUsd,
                'status' => $rootSpan->status,
            ]
        );
    }
}
