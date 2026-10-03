<?php

namespace App\Modules\Ai\Application\Agent\Graph;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use RuntimeException;

/**
 * Task 4.10 — explicit, reviewed `node_key => FQCN` map (config, same
 * pattern `config('ai.agent.registry')` already uses for `agent_type =>
 * AgentService`), resolved through the container so each `GraphNode`'s own
 * constructor dependencies (services, other nodes) are wired normally.
 *
 * This is what lets a `GraphDefinition` become **data** instead of
 * hand-built node instances (`AiAnalysisGraph`, converted by this task) —
 * see docs/architecture/agent-framework-roadmap.md, section 13: the set of
 * possible node *types* stays a closed, reviewed list (this registry's
 * keys), only *which of them, and in what order* becomes data. Auto-
 * discovery of arbitrary `GraphNode` classes is still explicitly out of
 * scope, same reasoning as the explicit tool list in `AiServiceProvider`.
 */
final class GraphNodeRegistry
{
    /**
     * @param  array<string, class-string<GraphNode>>  $nodes
     */
    public function __construct(
        private readonly Container $container,
        private readonly array $nodes,
    ) {}

    public function has(string $nodeKey): bool
    {
        return array_key_exists($nodeKey, $this->nodes);
    }

    public function resolve(string $nodeKey): GraphNode
    {
        $class = $this->nodes[$nodeKey] ?? null;

        if ($class === null) {
            throw new InvalidArgumentException(sprintf(
                'Unknown graph node_key "%s" — not registered in GraphNodeRegistry (registered: %s).',
                $nodeKey,
                implode(', ', array_keys($this->nodes)) ?: '(none)'
            ));
        }

        $node = $this->container->make($class);

        if (! $node instanceof GraphNode) {
            throw new RuntimeException(sprintf(
                'Graph node_key "%s" resolves to "%s", which does not implement GraphNode.',
                $nodeKey,
                $class
            ));
        }

        return $node;
    }

    /**
     * Builds a `GraphDefinition` from a data-shaped step list — e.g.
     * `[['key' => 'analyze', 'node' => 'analyze'], ...]` — instead of
     * hand-built `GraphStep`/node instances. Fails at **definition load
     * time** (here), not on first run, if any step's `node` isn't
     * registered — this is task 4.10's explicit acceptance test.
     *
     * `edgeDefinitions` is the same data shape `GraphDefinition::$edges`
     * expects, plumbed through unbuilt so a canvas/DB-authored definition
     * (a later step in the graph-builder work) can hand this method the
     * same JSON shape it stores, without a separate assembly path.
     * Omitted (default `[]`) keeps existing callers — the three hand-built
     * `Graph/Definitions/*` classes — on the legacy array-order behavior.
     *
     * @param  array<int, array{key: string, node: string}>  $stepDefinitions
     * @param  array<int, array{from: string, to: string, label?: ?string}>  $edgeDefinitions
     */
    public function buildDefinition(string $name, array $stepDefinitions, array $edgeDefinitions = []): GraphDefinition
    {
        $steps = array_map(
            fn (array $stepDef) => new GraphStep($stepDef['key'], $this->resolve($stepDef['node'])),
            $stepDefinitions
        );

        $edges = array_map(
            fn (array $edgeDef) => new GraphEdge($edgeDef['from'], $edgeDef['to'], $edgeDef['label'] ?? null),
            $edgeDefinitions
        );

        return new GraphDefinition($name, $steps, $edges);
    }

    /**
     * The builder canvas's node palette: every registered node_key with a
     * label/description, for a node that implements `DescribesGraphNode`,
     * or the raw key/an empty description as a fallback for one that
     * doesn't. Reads `paletteLabel()`/`paletteDescription()` as *static*
     * methods on the class-string directly (`is_a(..., true)`) rather than
     * resolving a real instance through the container — those two methods
     * need no constructor dependencies, and this can be called from a
     * lightweight "list what's available" request that has no reason to
     * build a full `AiContentAnalysisService` etc. just to read a label.
     *
     * @return array<int, array{node_key: string, label: string, description: string, prompt_key: ?string, contract: ?array<string, mixed>}>
     */
    public function describeAll(): array
    {
        return array_map(
            function (string $nodeKey, string $class): array {
                $isDescribed = is_a($class, DescribesGraphNode::class, true);

                return [
                    'node_key' => $nodeKey,
                    'label' => $isDescribed ? $class::paletteLabel() : $nodeKey,
                    'description' => $isDescribed ? $class::paletteDescription() : '',
                    'prompt_key' => $isDescribed ? $class::promptKey() : null,
                    'contract' => $isDescribed ? $class::nodeContract()->toArray() : null,
                ];
            },
            array_keys($this->nodes),
            array_values($this->nodes)
        );
    }
}
