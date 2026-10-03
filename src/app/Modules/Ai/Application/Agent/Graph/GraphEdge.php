<?php

namespace App\Modules\Ai\Application\Agent\Graph;

use InvalidArgumentException;

/**
 * One directed connection between two `GraphStep` keys — the persisted,
 * canvas-facing counterpart to what a `RouterNode` already decides at
 * runtime via `GraphState::routeTo()`. `label` is purely descriptive
 * (e.g. "approved" / "rejected") for rendering a branch on the builder
 * canvas — it is never evaluated as a condition; the actual branching
 * decision still lives in reviewed `GraphNode` code, never in data. See
 * `GraphDefinition::stepAfter()` for how a step with several outgoing
 * edges (a branch point) differs from one with a single, unambiguous
 * edge (the default sequential continuation).
 */
final class GraphEdge
{
    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly ?string $label = null,
    ) {
        if (trim($from) === '') {
            throw new InvalidArgumentException('GraphEdge requires a non-empty "from" step key.');
        }

        if (trim($to) === '') {
            throw new InvalidArgumentException('GraphEdge requires a non-empty "to" step key.');
        }
    }
}
