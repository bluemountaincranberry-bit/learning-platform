<?php

namespace App\Modules\Ai\Application\Agent\Graph;

/**
 * One named position in a `GraphDefinition`: a stable `key` (persisted as
 * `agent_graph_runs.current_node`, and what `resume()` and `HandoffTool`-
 * style callers refer to) paired with the `GraphNode` instance that runs
 * there. Kept as its own value object rather than a raw `['key' => ...,
 * 'node' => ...]` array so a malformed definition fails fast with a real
 * type error instead of a silent `null` key lookup.
 */
final class GraphStep
{
    public function __construct(
        public readonly string $key,
        public readonly GraphNode $node,
    ) {
        if (trim($key) === '') {
            throw new \InvalidArgumentException('GraphStep requires a non-empty key.');
        }
    }
}
