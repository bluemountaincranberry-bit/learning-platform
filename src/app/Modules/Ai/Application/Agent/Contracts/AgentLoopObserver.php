<?php

namespace App\Modules\Ai\Application\Agent\Contracts;

use App\Modules\Ai\Application\Agent\Data\AgentChatResponse;
use App\Modules\Ai\Application\Agent\Data\AgentToolCall;

/**
 * Reacts to `AgentLoop` events as they happen, one call at a time — the
 * loop itself never writes to storage. This is what lets a caller persist
 * partial progress (tool calls already executed) as it happens rather than
 * batching everything until the end, so a mid-turn crash in the job still
 * leaves an accurate history behind instead of losing it with the
 * unhandled exception.
 */
interface AgentLoopObserver
{
    /**
     * A tool call is about to run, before `AgentTool::execute()` is invoked
     * (task 6.2). Exists so a caller can surface "the agent is doing X" to a
     * live UI (`TutorConversationController`'s SSE `tool_start`/`handoff`
     * events) — the loop itself has no notion of "live" vs "batch", it just
     * reports the boundary as it happens, same as `onToolCallCompleted()`.
     * Most observers (anything not streaming to a client, e.g.
     * `NonPersistingAgentObserver`) simply do nothing here.
     */
    public function onToolCallStarted(AgentToolCall $call): void;

    /**
     * One tool call finished (successfully or with a caught error — the
     * distinction is inside `$result`, e.g. an `error` key).
     *
     * @param  array<string, mixed>  $result
     */
    public function onToolCallCompleted(AgentToolCall $call, array $result, int $latencyMs): void;

    /**
     * The model produced a final textual reply — the loop is done for this turn.
     */
    public function onFinalResponse(AgentChatResponse $response): void;

    /**
     * The loop used up its iteration budget without the model producing a
     * final textual reply.
     */
    public function onIterationLimitReached(): void;
}
