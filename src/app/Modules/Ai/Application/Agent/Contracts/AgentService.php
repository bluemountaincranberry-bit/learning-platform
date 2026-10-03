<?php

namespace App\Modules\Ai\Application\Agent\Contracts;

/**
 * What `RunAgentTurnJob` needs from any concrete agent (`ContentAgentService`,
 * `StudentTutorAgentService`, ...) to run one turn — Eloquent-owning,
 * agent-specific wrappers around the shared `AgentLoop`. The job resolves
 * which implementation to call via `config('ai.agent.registry')`, keyed by
 * `AgentConversation::agent_type` (see docs/architecture/agent-framework-roadmap.md,
 * step 5.7), instead of being hardcoded to one agent.
 */
interface AgentService
{
    public function handleTurn(int $conversationId): void;
}
