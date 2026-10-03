<?php

use App\Modules\Ai\Domain\Models\PersistedGraphDefinition;
use App\Modules\Ai\Application\Agent\Graph\Definitions\AiAnalysisGraph;
use App\Modules\Ai\Application\Agent\Graph\GraphDefinitionResolver;
use App\Modules\Ai\Application\Agent\Graph\GraphNode;
use App\Modules\Ai\Application\Agent\Graph\GraphState;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

class ResolverFakeNode implements GraphNode
{
    public function run(GraphState $state): GraphState
    {
        return $state;
    }
}

test('resolve() falls back to the config-registered PHP class when no PersistedGraphDefinition row exists, versionId is null', function () {
    $resolved = app(GraphDefinitionResolver::class)->resolve(AiAnalysisGraph::NAME);

    expect($resolved->definition->name)->toBe(AiAnalysisGraph::NAME)
        ->and($resolved->definition->stepByKey('analyze'))->not->toBeNull()
        ->and($resolved->versionId)->toBeNull();
});

test('resolve() falls back when a PersistedGraphDefinition row exists but has no active_version_id (draft)', function () {
    PersistedGraphDefinition::query()->create(['key' => AiAnalysisGraph::NAME, 'name' => 'AI analysis (draft edit)']);

    $resolved = app(GraphDefinitionResolver::class)->resolve(AiAnalysisGraph::NAME);

    expect($resolved->definition->stepByKey('analyze'))->not->toBeNull()
        ->and($resolved->versionId)->toBeNull();
});

test('resolve() uses the published PersistedGraphDefinition version instead of the config class when one is active, and returns its versionId', function () {
    config(['ai.graph.node_registry' => ['fake' => ResolverFakeNode::class]]);

    $record = PersistedGraphDefinition::query()->create(['key' => 'custom_graph', 'name' => 'Custom']);
    $version = $record->versions()->create([
        'version' => 1,
        'nodes' => [['key' => 'a', 'node' => 'fake']],
        'edges' => [],
    ]);
    $record->update(['active_version_id' => $version->id]);

    $resolved = app(GraphDefinitionResolver::class)->resolve('custom_graph');

    expect($resolved->definition->name)->toBe('custom_graph')
        ->and($resolved->definition->steps)->toHaveCount(1)
        ->and($resolved->definition->steps[0]->key)->toBe('a')
        ->and($resolved->versionId)->toBe($version->id);
});

test('resolve() throws for a graph_name unknown to both the DB and config', function () {
    expect(fn () => app(GraphDefinitionResolver::class)->resolve('totally_unknown'))
        ->toThrow(InvalidArgumentException::class, 'Unknown graph_name "totally_unknown"');
});

test('resolve() rejects a published version whose nodes reference an unregistered node_key', function () {
    $record = PersistedGraphDefinition::query()->create(['key' => 'broken_graph', 'name' => 'Broken']);
    $version = $record->versions()->create([
        'version' => 1,
        'nodes' => [['key' => 'a', 'node' => 'not_a_real_node_type']],
        'edges' => [],
    ]);
    $record->update(['active_version_id' => $version->id]);

    expect(fn () => app(GraphDefinitionResolver::class)->resolve('broken_graph'))
        ->toThrow(InvalidArgumentException::class, 'Unknown graph node_key "not_a_real_node_type"');
});
