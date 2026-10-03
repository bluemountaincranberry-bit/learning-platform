<?php

use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;
use App\Modules\Ai\Application\Agent\Graph\DescribesGraphNode;
use App\Modules\Ai\Application\Agent\Graph\GraphNode;
use App\Modules\Ai\Application\Agent\Graph\GraphNodeContract;
use App\Modules\Ai\Application\Agent\Graph\GraphNodeRegistry;
use App\Modules\Ai\Application\Agent\Graph\GraphState;

function fakeNodeContract(): GraphNodeContract
{
    return new GraphNodeContract(
        reads: ['x'],
        writes: ['y'],
        sideEffect: AgentToolDefinition::SIDE_EFFECT_READ_ONLY,
        execution: GraphNodeContract::EXECUTION_SYNC,
        canPause: false,
        failurePolicy: GraphNodeContract::FAILURE_POLICY_FAIL,
        queuesJobs: false,
    );
}

uses(Tests\TestCase::class);

class RegistryFakeNode implements GraphNode
{
    public function run(GraphState $state): GraphState
    {
        return $state->set('touched', true);
    }
}

class RegistryDescribedFakeNode implements GraphNode, DescribesGraphNode
{
    public function run(GraphState $state): GraphState
    {
        return $state;
    }

    public static function paletteLabel(): string
    {
        return 'Described node';
    }

    public static function paletteDescription(): string
    {
        return 'A node that describes itself for the palette.';
    }

    public static function promptKey(): ?string
    {
        return 'described_node_prompt';
    }

    public static function nodeContract(): GraphNodeContract
    {
        return fakeNodeContract();
    }
}

class NotAGraphNode {}

test('resolve() builds a registered node through the container', function () {
    $registry = new GraphNodeRegistry(app(), ['fake' => RegistryFakeNode::class]);

    expect($registry->resolve('fake'))->toBeInstanceOf(RegistryFakeNode::class)
        ->and($registry->has('fake'))->toBeTrue()
        ->and($registry->has('missing'))->toBeFalse();
});

test('resolve() throws for an unregistered node_key', function () {
    $registry = new GraphNodeRegistry(app(), ['fake' => RegistryFakeNode::class]);

    expect(fn () => $registry->resolve('does_not_exist'))
        ->toThrow(InvalidArgumentException::class, 'Unknown graph node_key "does_not_exist"');
});

test('resolve() throws if the registered class does not implement GraphNode', function () {
    $registry = new GraphNodeRegistry(app(), ['broken' => NotAGraphNode::class]);

    expect(fn () => $registry->resolve('broken'))->toThrow(RuntimeException::class);
});

test('buildDefinition() fails at load time — before any step runs — when a step references an unknown node_key', function () {
    $registry = new GraphNodeRegistry(app(), ['known' => RegistryFakeNode::class]);

    expect(fn () => $registry->buildDefinition('demo', [
        ['key' => 'a', 'node' => 'known'],
        ['key' => 'b', 'node' => 'not_registered'],
    ]))->toThrow(InvalidArgumentException::class, 'Unknown graph node_key "not_registered"');
});

test('buildDefinition() with only known node_keys produces a runnable GraphDefinition', function () {
    $registry = new GraphNodeRegistry(app(), ['known' => RegistryFakeNode::class]);

    $definition = $registry->buildDefinition('demo', [['key' => 'a', 'node' => 'known']]);

    $result = (new \App\Modules\Ai\Application\Agent\Graph\GraphRunner)->run($definition, new GraphState);

    expect($result->status)->toBe(\App\Modules\Ai\Application\Agent\Graph\GraphRunResult::STATUS_COMPLETED)
        ->and($result->state->get('touched'))->toBeTrue();
});

test('buildDefinition() plumbs data-shaped edges through to a runnable, edge-ordered GraphDefinition', function () {
    $registry = new GraphNodeRegistry(app(), ['known' => RegistryFakeNode::class]);

    // Steps listed out of execution order on purpose — edges, not array
    // position, must decide advancement (canvas layout order is arbitrary).
    $definition = $registry->buildDefinition(
        'demo',
        [
            ['key' => 'b', 'node' => 'known'],
            ['key' => 'a', 'node' => 'known'],
        ],
        [
            ['from' => 'a', 'to' => 'b'],
        ]
    );

    $result = (new \App\Modules\Ai\Application\Agent\Graph\GraphRunner)->run($definition, new GraphState, null, 'a');

    expect($result->status)->toBe(\App\Modules\Ai\Application\Agent\Graph\GraphRunResult::STATUS_COMPLETED)
        ->and($result->currentStepKey)->toBe('b');
});

test('buildDefinition() fails at load time when an edge references an unknown step key', function () {
    $registry = new GraphNodeRegistry(app(), ['known' => RegistryFakeNode::class]);

    expect(fn () => $registry->buildDefinition(
        'demo',
        [['key' => 'a', 'node' => 'known']],
        [['from' => 'a', 'to' => 'missing']]
    ))->toThrow(InvalidArgumentException::class, 'edge references unknown "to" step key "missing"');
});

test('describeAll() uses paletteLabel()/paletteDescription() for a node that implements DescribesGraphNode', function () {
    $registry = new GraphNodeRegistry(app(), ['described' => RegistryDescribedFakeNode::class]);

    expect($registry->describeAll())->toBe([
        [
            'node_key' => 'described',
            'label' => 'Described node',
            'description' => 'A node that describes itself for the palette.',
            'prompt_key' => 'described_node_prompt',
            'contract' => fakeNodeContract()->toArray(),
        ],
    ]);
});

test('describeAll() falls back to the raw key, empty description, and null prompt_key for a node that does not implement DescribesGraphNode', function () {
    $registry = new GraphNodeRegistry(app(), ['plain' => RegistryFakeNode::class]);

    expect($registry->describeAll())->toBe([
        ['node_key' => 'plain', 'label' => 'plain', 'description' => '', 'prompt_key' => null, 'contract' => null],
    ]);
});

test('describeAll() never instantiates the node class — no constructor call needed for a label-only listing', function () {
    $registry = new GraphNodeRegistry(app(), ['described' => RegistryConstructorThrowsNode::class]);

    expect($registry->describeAll())->toBe([
        ['node_key' => 'described', 'label' => 'Throws if constructed', 'description' => 'x', 'prompt_key' => null, 'contract' => fakeNodeContract()->toArray()],
    ]);
});

class RegistryConstructorThrowsNode implements GraphNode, DescribesGraphNode
{
    public function __construct()
    {
        throw new RuntimeException('should never be constructed just to list the palette');
    }

    public function run(GraphState $state): GraphState
    {
        return $state;
    }

    public static function paletteLabel(): string
    {
        return 'Throws if constructed';
    }

    public static function paletteDescription(): string
    {
        return 'x';
    }

    public static function promptKey(): ?string
    {
        return null;
    }

    public static function nodeContract(): GraphNodeContract
    {
        return fakeNodeContract();
    }
}
