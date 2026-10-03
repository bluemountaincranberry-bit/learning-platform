<?php

namespace App\Modules\Ai\Application\Agent\Data;

/**
 * Either the model produced a final textual reply, or it wants one or more
 * tools run first (`toolCalls` non-empty, `content` null) — never both, per
 * the OpenAI tool-calling contract this mirrors.
 */
final class AgentChatResponse
{
    /**
     * @param  array<int, AgentToolCall>  $toolCalls
     */
    public function __construct(
        public readonly ?string $content,
        public readonly array $toolCalls = [],
        public readonly ?int $promptTokens = null,
        public readonly ?int $completionTokens = null,
    ) {}

    public function hasToolCalls(): bool
    {
        return $this->toolCalls !== [];
    }
}
