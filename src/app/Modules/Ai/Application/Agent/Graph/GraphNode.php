<?php

namespace App\Modules\Ai\Application\Agent\Graph;

/**
 * One step of a `GraphDefinition` (task 4.1). A node reads/writes
 * `GraphState` and returns the (possibly mutated) state — it does not know
 * what runs before or after it, or whether the run will pause; that is
 * `GraphRunner`'s job. Concrete node "shapes" from
 * docs/architecture/agent-framework-roadmap.md, section 7 — `LlmCallNode`,
 * `ToolNode`, `AgentNode`, `RouterNode`, `HumanCheckpointNode`,
 * `ParallelNode` — are all just implementations of this one interface, not
 * separate mechanisms in the runner.
 *
 * A node that needs the run to pause (waiting for a human/external
 * `resume()`, task 4.8) calls `$state->pause()` before returning — the
 * runner checks that flag after every step, it is not a separate return
 * type. This is deliberately the same "generic, checkable state" pattern
 * `AgentToolDefinition::sideEffect` uses: a fact any node can set, that the
 * runner (not the node) is responsible for acting on.
 */
interface GraphNode
{
    public function run(GraphState $state): GraphState;
}
