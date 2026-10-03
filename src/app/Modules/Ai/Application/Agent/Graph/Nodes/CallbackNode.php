<?php

namespace App\Modules\Ai\Application\Agent\Graph\Nodes;

use App\Modules\Ai\Application\Agent\Graph\GraphNode;
use App\Modules\Ai\Application\Agent\Graph\GraphState;
use Closure;

/**
 * Generic node for small, graph-specific, non-LLM logic that doesn't
 * warrant its own reusable class — e.g. a merge step composing a final
 * reply from earlier steps' results, or a router's fallback branch. Same
 * "the node type is closed/reviewed, the closure is app code" reasoning as
 * `CallbackRouterNode`'s docblock.
 */
final class CallbackNode implements GraphNode
{
    /**
     * @param  Closure(GraphState): GraphState  $callback
     */
    public function __construct(private readonly Closure $callback) {}

    public function run(GraphState $state): GraphState
    {
        return ($this->callback)($state);
    }
}
