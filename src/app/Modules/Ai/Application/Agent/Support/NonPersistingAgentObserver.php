<?php

namespace App\Modules\Ai\Application\Agent\Support;

use App\Modules\Ai\Application\Agent\Contracts\AgentLoopObserver;
use App\Modules\Ai\Application\Agent\Data\AgentChatResponse;
use App\Modules\Ai\Application\Agent\Data\AgentToolCall;

/**
 * Transient observer for an `AgentLoop::run()` invoked somewhere other than
 * a top-level, conversation-owning turn — captures only the final text (or
 * "hit its step limit"). Used by `HandoffTool::execute()` and by
 * specialist-agent services (`GrammarAgentService`, `ReviewAgentService`,
 * ...) that are only ever invoked *by* a coordinator (`ContentAgentService`/
 * `StudentTutorAgentService`, ADR-001), never directly by a user — so they
 * have no `AgentConversation` of their own to persist `AgentMessage` rows
 * into. The calling coordinator's own turn is what gets persisted, with the
 * specialist's result folded in as an ordinary tool/graph-node result.
 */
final class NonPersistingAgentObserver implements AgentLoopObserver
{
    public ?string $finalText = null;

    public bool $iterationLimitReached = false;

    public function onToolCallStarted(AgentToolCall $call): void
    {
        // See onToolCallCompleted() below — same reasoning, nothing to do.
    }

    public function onToolCallCompleted(AgentToolCall $call, array $result, int $latencyMs): void
    {
        // Sub-agent tool calls are an internal implementation detail from
        // the caller's point of view — not persisted or surfaced here. They
        // are still traced (each gets its own span nested under the
        // handoff/graph-node span, via SpanRecorder inside AgentLoop
        // itself), just not part of this observer's job.
    }

    public function onFinalResponse(AgentChatResponse $response): void
    {
        $this->finalText = $response->content;
    }

    public function onIterationLimitReached(): void
    {
        $this->iterationLimitReached = true;
    }
}
