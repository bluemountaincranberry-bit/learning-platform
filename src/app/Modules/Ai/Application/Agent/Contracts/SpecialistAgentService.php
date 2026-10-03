<?php

namespace App\Modules\Ai\Application\Agent\Contracts;

use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Tracing\TraceContext;

/**
 * What a specialist agent (`GrammarAgentService`, `ReviewAgentService`, ...
 * — never a third coordinator, ADR-001) exposes to be invoked as one
 * reasoning step by something else: a `HandoffTool` subclass, or, since
 * task 4.7, `Agent\Graph\Nodes\AgentNode` wrapping it as a graph step. Kept
 * separate from `AgentService` (`handleTurn(conversationId)`) deliberately
 * — a specialist has no `AgentConversation` of its own to load history
 * from or persist into (see `NonPersistingAgentObserver`'s docblock), so
 * its entry point takes the task text and context directly instead.
 */
interface SpecialistAgentService
{
    public function run(string $task, AgentToolContext $context, TraceContext $trace): string;
}
