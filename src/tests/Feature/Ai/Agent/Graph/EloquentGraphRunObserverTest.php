<?php

use App\Modules\Ai\Domain\Models\AgentGraphRun;
use App\Modules\Ai\Application\Agent\Graph\EloquentGraphRunObserver;
use App\Modules\Ai\Application\Agent\Graph\GraphDefinition;
use App\Modules\Ai\Application\Agent\Graph\GraphNode;
use App\Modules\Ai\Application\Agent\Graph\GraphRunner;
use App\Modules\Ai\Application\Agent\Graph\GraphRunResult;
use App\Modules\Ai\Application\Agent\Graph\GraphState;
use App\Modules\Ai\Application\Agent\Graph\GraphStep;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('start() persists a running row and step progress updates current_node/state', function () {
    $a = new class implements GraphNode
    {
        public function run(GraphState $state): GraphState
        {
            return $state->set('touched', 'a');
        }
    };
    $b = new class implements GraphNode
    {
        public function run(GraphState $state): GraphState
        {
            return $state->set('touched', 'b');
        }
    };

    $definition = new GraphDefinition('demo_graph', [
        new GraphStep('a', $a),
        new GraphStep('b', $b),
    ]);

    $initial = new GraphState;
    $observer = EloquentGraphRunObserver::start('demo_graph', $initial, 'a');
    $dbRun = $observer->run();

    expect($dbRun->status)->toBe(AgentGraphRun::STATUS_RUNNING)
        ->and($dbRun->graph_name)->toBe('demo_graph')
        ->and($dbRun->current_node)->toBe('a');

    $result = (new GraphRunner)->run($definition, $initial, $observer);

    expect($result->status)->toBe(GraphRunResult::STATUS_COMPLETED);

    $dbRun->refresh();
    expect($dbRun->status)->toBe(AgentGraphRun::STATUS_COMPLETED)
        ->and($dbRun->current_node)->toBe('b')
        ->and($dbRun->state)->toBe(['touched' => 'b'])
        ->and($dbRun->completed_at)->not->toBeNull();
});

test('a paused run persists status=paused and resume() rejects a run that is not paused', function () {
    $checkpoint = new class implements GraphNode
    {
        public function run(GraphState $state): GraphState
        {
            if (! $state->get('approved')) {
                $state->pause('awaiting_review');
            }

            return $state;
        }
    };

    $definition = new GraphDefinition('demo_graph', [new GraphStep('checkpoint', $checkpoint)]);

    $observer = EloquentGraphRunObserver::start('demo_graph', new GraphState, 'checkpoint');
    (new GraphRunner)->run($definition, new GraphState, $observer);

    $dbRun = $observer->run()->refresh();
    expect($dbRun->status)->toBe(AgentGraphRun::STATUS_PAUSED)
        ->and($dbRun->current_node)->toBe('checkpoint');

    // Resuming a completed run must be rejected, not silently allowed.
    $dbRun->update(['status' => AgentGraphRun::STATUS_COMPLETED]);
    expect(fn () => EloquentGraphRunObserver::resume($dbRun->id))->toThrow(RuntimeException::class);
});

test('a failing step persists status=failed with the failure_reason', function () {
    $boom = new class implements GraphNode
    {
        public function run(GraphState $state): GraphState
        {
            throw new RuntimeException('nope');
        }
    };

    $definition = new GraphDefinition('demo_graph', [new GraphStep('boom', $boom)]);

    $observer = EloquentGraphRunObserver::start('demo_graph', new GraphState, 'boom');
    (new GraphRunner)->run($definition, new GraphState, $observer);

    $dbRun = $observer->run()->refresh();
    expect($dbRun->status)->toBe(AgentGraphRun::STATUS_FAILED)
        ->and($dbRun->failure_reason)->toBe('nope');
});
