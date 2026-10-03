<?php

use App\Modules\Ai\Application\Agent\Graph\Contracts\GraphRunObserver;
use App\Modules\Ai\Application\Agent\Graph\GraphDefinition;
use App\Modules\Ai\Application\Agent\Graph\GraphEdge;
use App\Modules\Ai\Application\Agent\Graph\GraphNode;
use App\Modules\Ai\Application\Agent\Graph\GraphRunner;
use App\Modules\Ai\Application\Agent\Graph\GraphRunResult;
use App\Modules\Ai\Application\Agent\Graph\GraphState;
use App\Modules\Ai\Application\Agent\Graph\GraphStep;

/**
 * Pure unit tests: fake in-memory nodes and a recording observer, no
 * Eloquent/database involved anywhere — proves GraphRunner is a pure engine
 * exactly like AgentLoopTest proves AgentLoop is (see that file's docblock).
 */
uses(Tests\TestCase::class);

final class FakeGraphNode implements GraphNode
{
    /** @var array<int, GraphState> */
    public array $calls = [];

    public function __construct(private readonly ?Closure $behavior = null) {}

    public function run(GraphState $state): GraphState
    {
        $this->calls[] = $state;

        if ($this->behavior !== null) {
            return ($this->behavior)($state);
        }

        return $state;
    }
}

final class RecordingGraphRunObserver implements GraphRunObserver
{
    /** @var array<int, string> */
    public array $stepsCompleted = [];

    public ?string $pausedAt = null;

    public ?string $completedAt = null;

    public ?string $failedAt = null;

    public ?Throwable $failure = null;

    public function onStepCompleted(string $stepKey, GraphState $state): void
    {
        $this->stepsCompleted[] = $stepKey;
    }

    public function onCompleted(string $lastStepKey, GraphState $state): void
    {
        $this->completedAt = $lastStepKey;
    }

    public function onPaused(string $stepKey, GraphState $state): void
    {
        $this->pausedAt = $stepKey;
    }

    public function onFailed(string $stepKey, Throwable $e): void
    {
        $this->failedAt = $stepKey;
        $this->failure = $e;
    }
}

test('runs every step in order and reports completed', function () {
    $a = new FakeGraphNode(fn (GraphState $s) => $s->set('a', true));
    $b = new FakeGraphNode(fn (GraphState $s) => $s->set('b', true));

    $definition = new GraphDefinition('demo', [
        new GraphStep('a', $a),
        new GraphStep('b', $b),
    ]);

    $observer = new RecordingGraphRunObserver;
    $result = (new GraphRunner)->run($definition, new GraphState, $observer);

    expect($result->status)->toBe(GraphRunResult::STATUS_COMPLETED)
        ->and($result->currentStepKey)->toBe('b')
        ->and($result->state->toArray())->toBe(['a' => true, 'b' => true])
        ->and($observer->stepsCompleted)->toBe(['a', 'b'])
        ->and($observer->completedAt)->toBe('b');
});

test('a node that pauses stops the runner before later steps run', function () {
    $a = new FakeGraphNode(fn (GraphState $s) => $s->set('a', true));
    $checkpoint = new FakeGraphNode(function (GraphState $s) {
        if (! $s->get('approved')) {
            $s->pause('awaiting_review');
        }

        return $s;
    });
    $c = new FakeGraphNode(fn (GraphState $s) => $s->set('c', true));

    $definition = new GraphDefinition('demo', [
        new GraphStep('a', $a),
        new GraphStep('checkpoint', $checkpoint),
        new GraphStep('c', $c),
    ]);

    $observer = new RecordingGraphRunObserver;
    $result = (new GraphRunner)->run($definition, new GraphState, $observer);

    expect($result->status)->toBe(GraphRunResult::STATUS_PAUSED)
        ->and($result->currentStepKey)->toBe('checkpoint')
        ->and($result->state->toArray())->toBe(['a' => true])
        ->and($c->calls)->toBe([])
        ->and($observer->pausedAt)->toBe('checkpoint')
        ->and($observer->completedAt)->toBeNull();
});

test('resume() re-enters at the paused step, and the node deciding to proceed continues the run', function () {
    $checkpoint = new FakeGraphNode(function (GraphState $s) {
        if (! $s->get('approved')) {
            $s->pause('awaiting_review');
        }

        return $s;
    });
    $after = new FakeGraphNode(fn (GraphState $s) => $s->set('after', true));

    $definition = new GraphDefinition('demo', [
        new GraphStep('checkpoint', $checkpoint),
        new GraphStep('after', $after),
    ]);

    $runner = new GraphRunner;
    $paused = $runner->run($definition, new GraphState, null);
    expect($paused->status)->toBe(GraphRunResult::STATUS_PAUSED);

    $resumedState = GraphState::fromArray($paused->state->toArray())->set('approved', true);
    $resumed = $runner->run($definition, $resumedState, null, $paused->currentStepKey);

    expect($resumed->status)->toBe(GraphRunResult::STATUS_COMPLETED)
        ->and($resumed->state->toArray())->toBe(['approved' => true, 'after' => true])
        ->and($checkpoint->calls)->toHaveCount(2);
});

test('a node that throws stops the runner and reports failed without running later steps', function () {
    $a = new FakeGraphNode(fn (GraphState $s) => $s->set('a', true));
    $boom = new FakeGraphNode(function () {
        throw new RuntimeException('kaboom');
    });
    $c = new FakeGraphNode(fn (GraphState $s) => $s->set('c', true));

    $definition = new GraphDefinition('demo', [
        new GraphStep('a', $a),
        new GraphStep('boom', $boom),
        new GraphStep('c', $c),
    ]);

    $observer = new RecordingGraphRunObserver;
    $result = (new GraphRunner)->run($definition, new GraphState, $observer);

    expect($result->status)->toBe(GraphRunResult::STATUS_FAILED)
        ->and($result->currentStepKey)->toBe('boom')
        ->and($result->failureReason)->toBe('kaboom')
        ->and($c->calls)->toBe([])
        ->and($observer->failedAt)->toBe('boom');
});

test('a RouterNode redirects to a named step instead of the next one in definition order', function () {
    $router = new FakeGraphNode(function (GraphState $s) {
        $s->routeTo('grammar');

        return $s;
    });
    $exercise = new FakeGraphNode(fn (GraphState $s) => $s->set('branch', 'exercise'));
    $grammar = new FakeGraphNode(fn (GraphState $s) => $s->set('branch', 'grammar'));

    $definition = new GraphDefinition('demo', [
        new GraphStep('router', $router),
        new GraphStep('exercise', $exercise),
        new GraphStep('grammar', $grammar),
    ]);

    $result = (new GraphRunner)->run($definition, new GraphState);

    expect($result->status)->toBe(GraphRunResult::STATUS_COMPLETED)
        ->and($result->state->toArray())->toBe(['branch' => 'grammar'])
        ->and($exercise->calls)->toBe([]);
});

test('running from an unknown step key throws', function () {
    $definition = new GraphDefinition('demo', [
        new GraphStep('only', new FakeGraphNode),
    ]);

    (new GraphRunner)->run($definition, new GraphState, null, 'missing');
})->throws(InvalidArgumentException::class);

test('with edges, advancement follows edges instead of array order — steps may be listed out of order', function () {
    $a = new FakeGraphNode(fn (GraphState $s) => $s->set('a', true));
    $b = new FakeGraphNode(fn (GraphState $s) => $s->set('b', true));

    // Steps deliberately declared out of execution order (as a canvas with
    // arbitrary node layout would produce) — edges alone must decide order.
    $definition = new GraphDefinition('demo', [
        new GraphStep('b', $b),
        new GraphStep('a', $a),
    ], [
        new GraphEdge('a', 'b'),
    ]);

    $result = (new GraphRunner)->run($definition, new GraphState, null, 'a');

    expect($result->status)->toBe(GraphRunResult::STATUS_COMPLETED)
        ->and($result->currentStepKey)->toBe('b')
        ->and($result->state->toArray())->toBe(['a' => true, 'b' => true]);
});

test('a step with no outgoing edge is terminal, same as the last step in array-order mode', function () {
    $a = new FakeGraphNode(fn (GraphState $s) => $s->set('a', true));
    $b = new FakeGraphNode(fn (GraphState $s) => $s->set('b', true));

    $definition = new GraphDefinition('demo', [
        new GraphStep('a', $a),
        new GraphStep('b', $b),
    ], [
        new GraphEdge('a', 'b'),
    ]);

    $result = (new GraphRunner)->run($definition, new GraphState, null, 'b');

    expect($result->status)->toBe(GraphRunResult::STATUS_COMPLETED)
        ->and($result->currentStepKey)->toBe('b')
        ->and($result->state->toArray())->toBe(['b' => true]);
});

test('a branch point (multiple outgoing edges) whose node does not routeTo() throws instead of silently completing', function () {
    $router = new FakeGraphNode; // does not call routeTo()
    $exercise = new FakeGraphNode(fn (GraphState $s) => $s->set('branch', 'exercise'));
    $grammar = new FakeGraphNode(fn (GraphState $s) => $s->set('branch', 'grammar'));

    $definition = new GraphDefinition('demo', [
        new GraphStep('router', $router),
        new GraphStep('exercise', $exercise),
        new GraphStep('grammar', $grammar),
    ], [
        new GraphEdge('router', 'exercise', 'is_exercise'),
        new GraphEdge('router', 'grammar', 'is_grammar'),
    ]);

    expect(fn () => (new GraphRunner)->run($definition, new GraphState))
        ->toThrow(InvalidArgumentException::class, 'has 2 outgoing edges and no single default');
});

test('a branch point node that does call routeTo() still works with edges present, edges only document the possible branches', function () {
    $router = new FakeGraphNode(function (GraphState $s) {
        $s->routeTo('grammar');

        return $s;
    });
    $exercise = new FakeGraphNode(fn (GraphState $s) => $s->set('branch', 'exercise'));
    $grammar = new FakeGraphNode(fn (GraphState $s) => $s->set('branch', 'grammar'));

    $definition = new GraphDefinition('demo', [
        new GraphStep('router', $router),
        new GraphStep('exercise', $exercise),
        new GraphStep('grammar', $grammar),
    ], [
        new GraphEdge('router', 'exercise', 'is_exercise'),
        new GraphEdge('router', 'grammar', 'is_grammar'),
    ]);

    $result = (new GraphRunner)->run($definition, new GraphState);

    expect($result->status)->toBe(GraphRunResult::STATUS_COMPLETED)
        ->and($result->state->toArray())->toBe(['branch' => 'grammar'])
        ->and($exercise->calls)->toBe([]);
});

test('GraphDefinition rejects an edge that references an unknown step key', function () {
    expect(fn () => new GraphDefinition('demo', [
        new GraphStep('a', new FakeGraphNode),
    ], [
        new GraphEdge('a', 'missing'),
    ]))->toThrow(InvalidArgumentException::class, 'edge references unknown "to" step key "missing"');
});

test('GraphEdge rejects empty from/to keys', function () {
    expect(fn () => new GraphEdge('', 'b'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new GraphEdge('a', ''))->toThrow(InvalidArgumentException::class);
});
