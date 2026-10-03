<?php

namespace App\Modules\Ai\Application\Agent\Graph;

use App\Modules\Ai\Domain\Models\PersistedGraphDefinition;
use App\Modules\Ai\Domain\Models\PersistedGraphDefinitionVersion;
use Illuminate\Database\Eloquent\Collection;

/**
 * Admin CRUD over graph_definitions/graph_definition_versions, the write
 * side `GraphDefinitionResolver` (read-only) never touches — same split
 * PromptTemplateAdminService keeps from PromptRegistryService, for the
 * same reason (Interface Segregation: the read path every live graph run
 * goes through stays small and boring).
 */
final class GraphDefinitionAdminService
{
    public function __construct(private readonly GraphNodeRegistry $nodeRegistry) {}

    public function list(): Collection
    {
        return PersistedGraphDefinition::query()->with('activeVersion')->orderBy('key')->get();
    }

    public function show(string $key): PersistedGraphDefinition
    {
        return PersistedGraphDefinition::query()->where('key', $key)->with('versions', 'activeVersion')->firstOrFail();
    }

    /**
     * Saving a draft does NOT validate nodes/edges against the live node
     * registry — a draft is never resolved by `GraphDefinitionResolver`,
     * only an active_version_id is, so an admin can save a work-in-progress
     * wiring (e.g. mid-drag on the canvas) without it needing to be
     * runnable yet. publish() below is where that validation is required.
     *
     * @param  array<int, array{key: string, node: string, x?: float, y?: float}>  $nodes
     * @param  array<int, array{from: string, to: string, label?: ?string}>  $edges
     */
    public function saveDraft(string $key, string $name, ?string $description, array $nodes, array $edges, ?int $userId): PersistedGraphDefinitionVersion
    {
        $record = PersistedGraphDefinition::query()->updateOrCreate(['key' => $key], ['name' => $name, 'description' => $description]);

        $nextVersion = ((int) $record->versions()->max('version')) + 1;

        return $record->versions()->create([
            'version' => $nextVersion,
            'nodes' => $nodes,
            'edges' => $edges,
            'created_by' => $userId,
        ]);
    }

    /**
     * Publishing re-validates nodes/edges by actually building a
     * `GraphDefinition` through `GraphNodeRegistry::buildDefinition()` —
     * the same construction `GraphDefinitionResolver::resolve()` will do
     * on every future run of this graph_name. A broken draft (unknown
     * node_key, a dangling edge, an ambiguous branch point) throws here
     * and is never activated, instead of silently becoming the version a
     * live `ResumeGraphJob`/analysis run picks up next.
     *
     * @throws \InvalidArgumentException if $versionId does not belong to this key, or the version's nodes/edges do not build a valid GraphDefinition
     */
    public function publish(string $key, int $versionId): PersistedGraphDefinition
    {
        $record = PersistedGraphDefinition::query()->where('key', $key)->firstOrFail();
        $version = $record->versions()->where('id', $versionId)->first();

        if ($version === null) {
            throw new \InvalidArgumentException("Version {$versionId} does not belong to graph definition \"{$key}\".");
        }

        // Throws InvalidArgumentException on any structural problem —
        // deliberately not caught here, see docblock above.
        $this->nodeRegistry->buildDefinition($key, $version->nodes, $version->edges);

        $record->update(['active_version_id' => $version->id]);

        return $record->fresh();
    }
}
