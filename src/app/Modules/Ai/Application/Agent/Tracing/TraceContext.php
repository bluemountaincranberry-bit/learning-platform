<?php

namespace App\Modules\Ai\Application\Agent\Tracing;

use Illuminate\Support\Str;

/**
 * Which trace a unit of work belongs to, and which span (if any) is its
 * immediate parent. Mirrors how `AgentToolContext` is already handled:
 * never built by a tool/agent itself, created once at the outer boundary
 * of a turn and threaded explicitly through `AgentLoop::run()` (and, once
 * handoff/graph exist, through those too) — see
 * docs/architecture/agent-framework-roadmap.md, section 9 and step 5.12.
 */
final class TraceContext
{
    public function __construct(
        public readonly string $traceId,
        public readonly ?string $parentSpanId = null,
    ) {}

    /**
     * Starts a brand new trace with no parent span — call this once at the
     * outer boundary of a turn (currently the top of
     * ContentAgentService::handleTurn(), standing in for the "job/controller"
     * boundary described in the docs until RunAgentTurnJob is generalized
     * in task 1.9).
     */
    public static function newTrace(): self
    {
        return new self(Str::uuid()->toString());
    }

    /**
     * Same trace, scoped as a child of the given span id — pass this to
     * calls nested inside that span so they record with the right
     * `parent_span_id`.
     */
    public function withParentSpan(string $spanId): self
    {
        return new self($this->traceId, $spanId);
    }
}
