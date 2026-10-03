<?php

namespace App\Modules\Ai\Application\Agent\Graph;

use App\Modules\Ai\Domain\Models\PersistedGraphDefinition;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Task 4.11 support: `ResumeGraphJob` only has `agent_graph_runs.graph_name`
 * (a string) to work with — it needs a way back to the actual
 * `GraphDefinition` to resume running. Explicit, reviewed `graph_name =>
 * FQCN` map (`config('ai.graph.definitions')`), same "explicit map,
 * resolved through the container" shape as `GraphNodeRegistry` — the class
 * must expose a public `definition(): GraphDefinition` method (the same
 * shape `AiAnalysisGraph`/`TutorRoutingGraph` already have).
 *
 * Graph-builder groundwork: `resolve()` now checks `PersistedGraphDefinition`
 * (a canvas-authored, published override) before falling back to the
 * config map above — same "DB active version, else code default" shape
 * `PromptRegistryService` already applies to prompt text.
 *
 * **Every real entry point that starts or resumes a graph run must go
 * through this resolver, not inject `AiAnalysisGraph`/`StudyPlanGraph`
 * directly** — `AiAnalysisGraphService`/`PlanningAgentService` were
 * fixed to do so specifically because they didn't: injecting the
 * concrete graph class instead of resolving by name silently made a
 * published canvas override of the graph's *structure* (nodes/edges)
 * have zero effect on real runs, even though `Publish` reported success.
 * Prompt-text overrides were unaffected by that bug (they resolve inside
 * the node's own service, e.g. `AiContentAnalysisService`, independent of
 * which `GraphDefinition` object wired the steps) — only structural
 * edits were silently ignored. Returns `ResolvedGraphDefinition` (not a
 * bare `GraphDefinition`) so a caller starting a *new* run can persist
 * which version actually ran (`EloquentGraphRunObserver::start()`'s
 * `$definitionVersionId`) — `null` there means "ran from the hand-built
 * PHP class", not "unknown".
 */
final class GraphDefinitionResolver
{
    public function __construct(
        private readonly Container $container,
        private readonly GraphNodeRegistry $nodeRegistry,
    ) {}

    public function resolve(string $graphName): ResolvedGraphDefinition
    {
        $record = PersistedGraphDefinition::query()->where('key', $graphName)->with('activeVersion')->first();
        $version = $record?->activeVersion;

        if ($version !== null) {
            $definition = $this->nodeRegistry->buildDefinition($graphName, $version->nodes, $version->edges);

            return new ResolvedGraphDefinition($definition, $version->id);
        }

        $registry = config('ai.graph.definitions', []);
        $class = $registry[$graphName] ?? null;

        if ($class === null) {
            throw new InvalidArgumentException(sprintf(
                'Unknown graph_name "%s" — not registered in config(\'ai.graph.definitions\') and no published PersistedGraphDefinition exists for it.',
                $graphName
            ));
        }

        $graph = $this->container->make($class);

        return new ResolvedGraphDefinition($graph->definition(), null);
    }
}
