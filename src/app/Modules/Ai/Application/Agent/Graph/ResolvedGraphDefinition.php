<?php

namespace App\Modules\Ai\Application\Agent\Graph;

/**
 * What `GraphDefinitionResolver::resolve()` hands back — the runnable
 * `GraphDefinition` plus which `graph_definition_versions.id` it came
 * from, or `null` when it came from the hand-built PHP class instead
 * (no published DB override exists for this graph_name). A caller
 * starting a brand-new run persists `$versionId` onto its
 * `agent_graph_runs` row (`EloquentGraphRunObserver::start()`) — without
 * it, there would be no way to answer "which version of this graph
 * actually ran" once an admin has since published a different one.
 */
final readonly class ResolvedGraphDefinition
{
    public function __construct(
        public GraphDefinition $definition,
        public ?int $versionId,
    ) {}
}
