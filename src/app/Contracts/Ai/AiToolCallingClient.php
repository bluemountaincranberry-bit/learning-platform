<?php

namespace App\Contracts\Ai;

use App\Modules\Ai\Application\Agent\Data\AgentChatResponse;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;

/**
 * Multi-turn chat with function/tool calling. Unlike AiClientInterface and
 * AiJsonClient (single prompt in, one string/array out), this drives an
 * agent loop: the model can request tool calls instead of a final answer.
 *
 * Deliberately OpenAI-shaped rather than provider-neutral — `$messages` uses
 * the OpenAI chat message format (role/content/tool_calls/tool_call_id)
 * directly. Tool calling isn't implemented for Ollama; introduce an
 * abstraction here only once a second provider actually needs one.
 */
interface AiToolCallingClient
{
    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, AgentToolDefinition>  $tools
     */
    public function chat(array $messages, array $tools): AgentChatResponse;
}
