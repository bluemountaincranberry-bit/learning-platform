<?php

namespace App\Contracts\Ai;

use App\Modules\Ai\Application\Agent\Data\AgentChatResponse;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;

/**
 * Streaming variant of `AiToolCallingClient::chat()` (task 3.6): same
 * request shape (OpenAI chat message format + tool definitions), but reads
 * the response as Server-Sent Events and invokes `$onDelta` with each text
 * chunk as it arrives, instead of waiting for the full completion.
 *
 * Kept as its own small interface rather than added to `AiToolCallingClient`
 * — every existing caller of tool calling (`AgentLoop::run()`,
 * `ContentAgentService`) keeps working against the non-streaming contract
 * unchanged; only the new streaming path (`AgentLoop::runStreaming()`,
 * `TutorConversationController`) depends on this one. Same "OpenAI-shaped,
 * introduce a provider abstraction only once a second provider needs one"
 * stance as `AiToolCallingClient`.
 *
 * `$onDelta` fires only for assistant text content — a tool-call-only chunk
 * (the model decided to call a tool, no visible reply this turn) naturally
 * produces no content deltas, so callers do not need to distinguish "this
 * iteration streams" from "this iteration doesn't"; they just always pass an
 * `$onDelta` and it is called zero or more times depending on what the model
 * did.
 */
interface AiStreamingChatClient
{
    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, AgentToolDefinition>  $tools
     * @param  callable(string): void  $onDelta  Called with each text chunk as it streams in.
     */
    public function chatStream(array $messages, array $tools, callable $onDelta): AgentChatResponse;
}
