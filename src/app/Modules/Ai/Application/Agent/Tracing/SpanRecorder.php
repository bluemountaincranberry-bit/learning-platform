<?php

namespace App\Modules\Ai\Application\Agent\Tracing;

/**
 * Tier 1 tracing (docs/architecture/agent-framework-roadmap.md, section 9):
 * records spans as an agent turn executes — one row per LLM call, tool
 * call, agent turn, or (later) handoff, forming a tree via
 * `parent_span_id` that can be reconstructed with
 * `WHERE trace_id = ? ORDER BY started_at`. `NullSpanRecorder` is the
 * default (no-op, same pattern as `NullKafkaProducer`); a persisting
 * implementation (`DatabaseSpanRecorder`, task 1.7) writes to
 * `agent_trace_spans`.
 */
interface SpanRecorder
{
    /**
     * Starts a span and returns its id. Pass that id to `endSpan()`, and
     * optionally use it as the `parentSpanId` (via
     * `TraceContext::withParentSpan()`) for spans nested inside this one.
     *
     * @param  string  $spanType  One of: llm_call | tool_call | agent_turn | handoff
     * @param  array<string, mixed>  $metadata
     */
    public function startSpan(TraceContext $context, string $spanType, string $name, array $metadata = []): string;

    /**
     * @param  string  $status  One of: ok | error
     * @param  array<string, mixed>  $metadata  Merged with whatever startSpan() recorded — e.g. token counts only known once the call finishes.
     */
    public function endSpan(string $spanId, string $status, array $metadata = []): void;
}
