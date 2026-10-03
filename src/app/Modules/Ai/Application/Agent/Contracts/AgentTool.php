<?php

namespace App\Modules\Ai\Application\Agent\Contracts;

use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;

/**
 * One capability an agent can invoke. Implementations must validate
 * `$arguments` themselves — it is the model's raw decoded JSON and must
 * never be trusted blindly, especially anything that reaches the database.
 *
 * `definition()->sideEffect` is a safety invariant, not documentation: it
 * is checked when an agent's tool list is wired up
 * (`AgentToolDefinition::assertSideEffectsAllowed()`, called from
 * `AiServiceProvider`) so a tool that writes more than an agent is allowed
 * to can never be silently attached to it — see
 * docs/architecture/agent-framework-roadmap.md, step 5.3.
 */
interface AgentTool
{
    public function definition(): AgentToolDefinition;

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed> JSON-serializable result fed back to the model
     */
    public function execute(array $arguments, AgentToolContext $context): array;
}
