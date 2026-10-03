<?php

namespace App\Modules\Ai\Application\Data;

/**
 * Result of `PromptImprovementAssistantService::reply()` — the chat reply
 * and, separately, an optional concrete rewrite proposal. Kept apart
 * (rather than the assistant just replying with the new text inline) so
 * the editor can offer the proposal as an explicit "Insert into editor"
 * action instead of ever writing to the textarea on its own.
 */
final readonly class PromptImprovementReply
{
    public function __construct(
        public string $reply,
        public ?string $proposedSystemTemplate,
        public ?string $proposedUserTemplate,
    ) {}
}
