<?php

use App\Modules\Ai\Interfaces\Jobs\GraphBranchJob;
use App\Modules\Ai\Interfaces\Jobs\ResumeGraphJob;
use App\Modules\Ai\Domain\Models\AgentGraphBranchResult;
use App\Modules\Ai\Domain\Models\AgentGraphRun;
use App\Modules\Ai\Application\Agent\Graph\EloquentGraphRunObserver;
use App\Modules\Ai\Application\Agent\Graph\GraphDefinition;
use App\Modules\Ai\Application\Agent\Graph\GraphDefinitionResolver;
use App\Modules\Ai\Application\Agent\Graph\GraphNode;
use App\Modules\Ai\Application\Agent\Graph\GraphNodeRegistry;
use App\Modules\Ai\Application\Agent\Graph\GraphRunner;
use App\Modules\Ai\Application\Agent\Graph\GraphRunResult;
use App\Modules\Ai\Application\Agent\Graph\GraphState;
use App\Modules\Ai\Application\Agent\Graph\GraphStep;
use App\Modules\Ai\Application\Agent\Graph\Nodes\ParallelNode;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * QUEUE_CONNECTION=sync in tests (phpunit.xml), so Bus::batch() runs its
 * jobs inline instead of on a real Horizon worker. That makes the batch's
 * then() callback fire *while GraphRunner is still inside ParallelNode's
 * run()* — before the outer loop has persisted the run as paused — so the
 * auto-dispatched ResumeGraphJob correctly no-ops (its own "only resume an
 * actually-paused run" guard). This is a sync-queue testing artifact, not
 * a bug: in production, Bus::batch() dispatch is genuinely asynchronous
 * (Redis/Horizon), so ResumeGraphJob is only ever picked up after the run
 * is already persisted as paused. These tests exercise both halves
 * explicitly: the auto-fired (self-guarded) attempt, then a second,
 * explicit ResumeGraphJob call standing in for "Horizon eventually
 * delivers the queued job" — same outcome, same mechanism.
 */
uses(RefreshDatabase::class);

class ParallelTestBranchNode implements GraphNode
{
    public function __construct(private readonly string $tag = 'ran') {}

    public function run(GraphState $state): GraphState
    {
        return $state->set('branch_ran', $this->tag);
    }
}

class ParallelTestFailingBranchNode implements GraphNode
{
    public function run(GraphState $state): GraphState
    {
        throw new RuntimeException('branch exploded');
    }
}

/**
 * Stands in for a real Definitions/*.php class (AiAnalysisGraph,
 * TutorRoutingGraph) so GraphDefinitionResolver -> config('ai.graph.definitions')
 * can resolve this test's hand-built GraphDefinition for real, instead of
 * mocking the (final) resolver class.
 */
class ParallelTestGraphHolder
{
    public static ?GraphDefinition $definition = null;

    public function definition(): GraphDefinition
    {
        return self::$definition;
    }
}

function bindParallelTestRegistry(array $nodes): void
{
    app()->instance(GraphNodeRegistry::class, new GraphNodeRegistry(app(), $nodes));
}

test('fan-out: first entry dispatches one branch job per configured branch and pauses', function () {
    bindParallelTestRegistry(['branch_a' => ParallelTestBranchNode::class, 'branch_b' => ParallelTestBranchNode::class]);

    $node = new ParallelNode([
        ['branch_key' => 'branch_a', 'node' => 'branch_a'],
        ['branch_key' => 'branch_b', 'node' => 'branch_b'],
    ]);

    $definition = new GraphDefinition('demo_parallel', [new GraphStep('parallel', $node)]);
    $state = new GraphState;

    $observer = EloquentGraphRunObserver::start('demo_parallel', $state, 'parallel');
    $runId = $observer->run()->id;
    $state->set('graph_run_id', $runId);

    $result = (new GraphRunner)->run($definition, $state, $observer);

    expect($result->status)->toBe(GraphRunResult::STATUS_PAUSED)
        ->and(AgentGraphBranchResult::query()->where('graph_run_id', $runId)->count())->toBe(2)
        ->and(AgentGraphBranchResult::query()->where('graph_run_id', $runId)->where('status', 'completed')->count())->toBe(2);

    $dbRun = AgentGraphRun::query()->findOrFail($runId);
    expect($dbRun->status)->toBe(AgentGraphRun::STATUS_PAUSED);
});

test('fan-in: once every branch has completed, resuming aggregates results and the graph completes', function () {
    bindParallelTestRegistry(['branch_a' => ParallelTestBranchNode::class, 'branch_b' => ParallelTestBranchNode::class]);

    $after = new class implements GraphNode
    {
        public function run(GraphState $state): GraphState
        {
            return $state->set('after_parallel', true);
        }
    };

    $node = new ParallelNode([
        ['branch_key' => 'branch_a', 'node' => 'branch_a'],
        ['branch_key' => 'branch_b', 'node' => 'branch_b'],
    ]);

    $definitionSteps = [new GraphStep('parallel', $node), new GraphStep('after', $after)];
    $definition = new GraphDefinition('demo_parallel_2', $definitionSteps);
    $state = new GraphState;

    $observer = EloquentGraphRunObserver::start('demo_parallel_2', $state, 'parallel');
    $runId = $observer->run()->id;
    $state->set('graph_run_id', $runId);

    // First pass: dispatches + (sync-queue artifact) pauses even though the
    // branches already ran — see file docblock.
    $paused = (new GraphRunner)->run($definition, $state, $observer);
    expect($paused->status)->toBe(GraphRunResult::STATUS_PAUSED);

    // Register this test's hand-built definition under its graph_name so
    // the real GraphDefinitionResolver can find it (same mechanism
    // ResumeGraphJob uses in production).
    ParallelTestGraphHolder::$definition = $definition;
    config(['ai.graph.definitions' => ['demo_parallel_2' => ParallelTestGraphHolder::class]]);

    // Stand in for "Horizon delivers the queued ResumeGraphJob" (see file
    // docblock) — this time the run is genuinely paused, so it proceeds.
    (new ResumeGraphJob($runId))->handle(app(GraphRunner::class), app(GraphDefinitionResolver::class));

    $dbRun = AgentGraphRun::query()->findOrFail($runId);
    expect($dbRun->status)->toBe(AgentGraphRun::STATUS_COMPLETED)
        ->and($dbRun->state['after_parallel'])->toBeTrue()
        ->and($dbRun->state['branch_results'])->toHaveCount(2);
});

test('fail-fast: a branch job that throws marks its own branch result failed and rethrows for Bus::batch()', function () {
    bindParallelTestRegistry(['boom' => ParallelTestFailingBranchNode::class]);

    $run = AgentGraphRun::query()->create([
        'graph_name' => 'demo', 'state' => [], 'current_node' => 'parallel', 'status' => AgentGraphRun::STATUS_RUNNING, 'started_at' => now(),
    ]);

    $job = new GraphBranchJob($run->id, 'boom_branch', 'boom', []);

    expect(fn () => $job->handle(app(GraphNodeRegistry::class)))->toThrow(RuntimeException::class, 'branch exploded');

    $branchResult = AgentGraphBranchResult::query()->where('graph_run_id', $run->id)->where('branch_key', 'boom_branch')->first();
    expect($branchResult->status)->toBe(AgentGraphBranchResult::STATUS_FAILED)
        ->and($branchResult->error)->toBe('branch exploded');
});

test('fail-fast: one failing branch fails the whole graph run, not just that branch', function () {
    bindParallelTestRegistry(['ok' => ParallelTestBranchNode::class, 'boom' => ParallelTestFailingBranchNode::class]);

    $node = new ParallelNode([
        ['branch_key' => 'ok_branch', 'node' => 'ok'],
        ['branch_key' => 'boom_branch', 'node' => 'boom'],
    ]);

    $definition = new GraphDefinition('demo_parallel_fail', [new GraphStep('parallel', $node)]);
    $state = new GraphState;

    $observer = EloquentGraphRunObserver::start('demo_parallel_fail', $state, 'parallel');
    $runId = $observer->run()->id;
    $state->set('graph_run_id', $runId);

    // With QUEUE_CONNECTION=sync, Bus::batch() runs jobs inline; the
    // failing job's exception is caught by the queue worker layer and
    // routed to the batch's catch() (Laravel's own tested behavior, not
    // reimplemented here) — catch() marks the AgentGraphRun failed
    // directly, which is what this test asserts.
    (new GraphRunner)->run($definition, $state, $observer);

    $dbRun = AgentGraphRun::query()->findOrFail($runId);
    expect($dbRun->status)->toBe(AgentGraphRun::STATUS_FAILED)
        ->and($dbRun->failure_reason)->toContain('branch exploded');
});
