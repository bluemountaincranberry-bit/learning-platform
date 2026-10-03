<?php

namespace App\Modules\Ai\Application\Agent\Graph;

use InvalidArgumentException;

/**
 * Named, ordered set of `GraphStep`s — the graph-level analogue of
 * `AgentBlueprint`. Default advancement is sequential (array order); a step
 * whose node is a `RouterNode` can redirect to any other step by key via
 * `GraphState::routeTo()` (task 4.7). Built as a plain PHP structure for
 * now (task 4.1/4.2) — task 4.10 introduces `GraphNodeRegistry` and
 * converts definitions to a data/JSON shape instead of hand-built node
 * instances, without changing this class's runtime contract.
 *
 * `edges` (optional, added for the graph-builder canvas): when empty —
 * every definition in `Graph/Definitions/` today — behavior is unchanged,
 * array order is the only source of truth for `stepAfter()`. When
 * non-empty, edges become the source of truth instead: this is what lets
 * a canvas-authored definition rearrange `steps` in any order (positions
 * are just layout, not execution order) and still have well-defined
 * default advancement. Real branching decisions still happen only in
 * reviewed `GraphNode` code via `GraphState::routeTo()` — edges never
 * drive runtime routing themselves, they only have to agree with what the
 * node is allowed to do (see `stepAfter()`).
 */
final class GraphDefinition
{
    /**
     * @param  array<int, GraphStep>  $steps
     * @param  array<int, GraphEdge>  $edges
     */
    public function __construct(
        public readonly string $name,
        public readonly array $steps,
        public readonly array $edges = [],
    ) {
        if (trim($name) === '') {
            throw new InvalidArgumentException('GraphDefinition requires a non-empty name.');
        }

        if ($steps === []) {
            throw new InvalidArgumentException(sprintf('GraphDefinition "%s" requires at least one step.', $name));
        }

        $seenKeys = [];
        foreach ($steps as $step) {
            if (! $step instanceof GraphStep) {
                throw new InvalidArgumentException(sprintf('GraphDefinition "%s": every step must be a GraphStep instance.', $name));
            }

            if (isset($seenKeys[$step->key])) {
                throw new InvalidArgumentException(sprintf('GraphDefinition "%s": duplicate step key "%s".', $name, $step->key));
            }

            $seenKeys[$step->key] = true;
        }

        foreach ($edges as $edge) {
            if (! $edge instanceof GraphEdge) {
                throw new InvalidArgumentException(sprintf('GraphDefinition "%s": every edge must be a GraphEdge instance.', $name));
            }

            if (! isset($seenKeys[$edge->from])) {
                throw new InvalidArgumentException(sprintf('GraphDefinition "%s": edge references unknown "from" step key "%s".', $name, $edge->from));
            }

            if (! isset($seenKeys[$edge->to])) {
                throw new InvalidArgumentException(sprintf('GraphDefinition "%s": edge references unknown "to" step key "%s".', $name, $edge->to));
            }
        }
    }

    public function firstStep(): GraphStep
    {
        return $this->steps[0];
    }

    public function stepByKey(string $key): ?GraphStep
    {
        foreach ($this->steps as $step) {
            if ($step->key === $key) {
                return $step;
            }
        }

        return null;
    }

    public function indexOf(string $key): ?int
    {
        foreach ($this->steps as $index => $step) {
            if ($step->key === $key) {
                return $index;
            }
        }

        return null;
    }

    /**
     * The step that follows the given key: from `edges` when the
     * definition has any (canvas-authored), otherwise definition order
     * (unchanged legacy behavior). Null means "terminal node" — the run
     * completes here if nothing calls `GraphState::routeTo()`.
     *
     * A step with more than one outgoing edge is a branch point (e.g. a
     * `RouterNode` with several possible destinations) and has no single
     * "default" — that step's node is required to call `routeTo()` itself.
     * If it doesn't, this throws rather than silently completing the run
     * at an ambiguous branch, the same "fail fast on a malformed
     * definition" principle `GraphNodeRegistry::buildDefinition()` and the
     * constructor above already apply.
     */
    public function stepAfter(string $key): ?GraphStep
    {
        if ($this->edges !== []) {
            $outgoing = array_values(array_filter($this->edges, fn (GraphEdge $edge) => $edge->from === $key));

            if ($outgoing === []) {
                return null;
            }

            if (count($outgoing) > 1) {
                throw new InvalidArgumentException(sprintf(
                    'GraphDefinition "%s": step "%s" has %d outgoing edges and no single default — its node must call GraphState::routeTo() instead of relying on stepAfter().',
                    $this->name,
                    $key,
                    count($outgoing)
                ));
            }

            return $this->stepByKey($outgoing[0]->to);
        }

        $index = $this->indexOf($key);

        if ($index === null) {
            return null;
        }

        return $this->steps[$index + 1] ?? null;
    }
}
