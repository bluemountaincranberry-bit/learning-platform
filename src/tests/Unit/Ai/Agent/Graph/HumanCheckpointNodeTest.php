<?php

use App\Modules\Ai\Application\Agent\Graph\GraphDefinition;
use App\Modules\Ai\Application\Agent\Graph\GraphNode;
use App\Modules\Ai\Application\Agent\Graph\GraphRunner;
use App\Modules\Ai\Application\Agent\Graph\GraphRunResult;
use App\Modules\Ai\Application\Agent\Graph\GraphState;
use App\Modules\Ai\Application\Agent\Graph\GraphStep;
use App\Modules\Ai\Application\Agent\Graph\Nodes\HumanCheckpointNode;

/**
 * Task 4.8's explicit enforcing test: "graph pauses, resume() continues
 * from the same node" — HumanCheckpointNode in isolation, no domain
 * (AiAnalysisRun) coupling, on the pure GraphRunner (same style as
 * GraphRunnerTest).
 */
uses(Tests\TestCase::class);

test('pauses on first entry when the approval key is missing', function () {
    $definition = new GraphDefinition('demo', [new GraphStep('checkpoint', new HumanCheckpointNode)]);

    $result = (new GraphRunner)->run($definition, new GraphState);

    expect($result->status)->toBe(GraphRunResult::STATUS_PAUSED)
        ->and($result->currentStepKey)->toBe('checkpoint');
});

test('resume() re-enters at the checkpoint step and proceeds once approved=true is set', function () {
    $after = new class implements GraphNode
    {
        public int $calls = 0;

        public function run(GraphState $state): GraphState
        {
            $this->calls++;

            return $state->set('after_ran', true);
        }
    };

    $definition = new GraphDefinition('demo', [
        new GraphStep('checkpoint', new HumanCheckpointNode),
        new GraphStep('after', $after),
    ]);

    $runner = new GraphRunner;
    $paused = $runner->run($definition, new GraphState);
    expect($paused->status)->toBe(GraphRunResult::STATUS_PAUSED)
        ->and($after->calls)->toBe(0);

    $resumedState = GraphState::fromArray($paused->state->toArray())->set('approved', true);
    $resumed = $runner->run($definition, $resumedState, null, $paused->currentStepKey);

    expect($resumed->status)->toBe(GraphRunResult::STATUS_COMPLETED)
        ->and($resumed->state->toArray())->toBe(['approved' => true, 'after_ran' => true])
        ->and($after->calls)->toBe(1);
});

test('a custom approval key and pause reason are honored', function () {
    $node = new HumanCheckpointNode(approvalStateKey: 'admin_confirmed', pauseReason: 'waiting_on_admin');
    $definition = new GraphDefinition('demo', [new GraphStep('checkpoint', $node)]);

    $result = (new GraphRunner)->run($definition, new GraphState(['admin_confirmed' => false]));
    expect($result->status)->toBe(GraphRunResult::STATUS_PAUSED)
        ->and($result->state->pauseReason())->toBe('waiting_on_admin');

    $approved = (new GraphRunner)->run($definition, new GraphState(['admin_confirmed' => true]));
    expect($approved->status)->toBe(GraphRunResult::STATUS_COMPLETED);
});
