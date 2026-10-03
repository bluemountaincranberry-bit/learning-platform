<?php

namespace App\Modules\Ai\Application\Agent\Graph\Nodes;

use App\Modules\Ai\Application\Agent\Graph\GraphNode;
use App\Modules\Ai\Application\Agent\Graph\GraphState;
use Closure;

/**
 * Generic `RouterNode` (docs/architecture/agent-framework-roadmap.md,
 * section 7): a pure function of `GraphState` that picks the next step's
 * key, no LLM call and no side effect — reusable across any graph instead
 * of writing a bespoke class per decision. The set of possible next-step
 * keys is still exactly what the owning `GraphDefinition` wires it to
 * (closures are only ever written in reviewed application code, e.g.
 * `TutorRoutingGraph`, never constructed from external input) — this is
 * the same "the node *type* is a closed, reviewed list; what varies is
 * data/config" principle section 13 draws for `GraphNodeRegistry` (task
 * 4.10).
 */
final class CallbackRouterNode implements GraphNode
{
    /**
     * @param  Closure(GraphState): string  $decide
     */
    public function __construct(private readonly Closure $decide) {}

    public function run(GraphState $state): GraphState
    {
        $state->routeTo(($this->decide)($state));

        return $state;
    }
}
