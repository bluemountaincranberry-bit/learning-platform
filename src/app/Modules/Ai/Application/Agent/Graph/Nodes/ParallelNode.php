<?php

namespace App\Modules\Ai\Application\Agent\Graph\Nodes;

use App\Modules\Ai\Interfaces\Jobs\GraphBranchJob;
use App\Modules\Ai\Interfaces\Jobs\ResumeGraphJob;
use App\Modules\Ai\Domain\Models\AgentGraphBranchResult;
use App\Modules\Ai\Domain\Models\AgentGraphRun;
use App\Modules\Ai\Application\Agent\Graph\GraphNode;
use App\Modules\Ai\Application\Agent\Graph\GraphState;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * `ParallelNode` (task 4.11) — fan-out/fan-in via `Bus::batch()`, the same
 * "pause and let something external resume us" mechanic `HumanCheckpointNode`
 * uses, just waiting on a batch of Horizon jobs instead of a human click:
 * see docs/architecture/agent-framework-roadmap.md, section 12 ("та же
 * самая пауза").
 *
 * On first entry (no branch results recorded yet for this run): dispatches
 * one `GraphBranchJob` per configured branch as a `Bus::batch()`, then
 * pauses. `then()` (all branches succeeded) dispatches `ResumeGraphJob`;
 * `catch()` (any branch failed) marks the whole `AgentGraphRun` failed
 * directly — **fail-fast**: fan-in never runs against a partial result set
 * (section 12's explicit policy for v1; best-effort merge is out of scope
 * until a real scenario needs it).
 *
 * On the resumed re-entry (`ResumeGraphJob` -> `GraphRunner::resume()`,
 * which re-enters at this same step per `GraphRunner`'s docblock): every
 * branch's result is already in `agent_graph_branch_results`, so this
 * collects them into `$resultStateKey` and returns without pausing again,
 * letting the graph continue to whatever step follows.
 *
 * Requires `GraphState::get('graph_run_id')` (an int) — set by whoever
 * starts the graph run (`EloquentGraphRunObserver::start()`'s caller),
 * same convention `AgentNode` uses for `conversation_id`/`trace_id`.
 */
final class ParallelNode implements GraphNode
{
    /**
     * @param  array<int, array{branch_key: string, node: string}>  $branches  node is a GraphNodeRegistry key.
     */
    public function __construct(
        private readonly array $branches,
        private readonly string $resultStateKey = 'branch_results',
    ) {
        if ($branches === []) {
            throw new InvalidArgumentException('ParallelNode requires at least one branch.');
        }
    }

    public function run(GraphState $state): GraphState
    {
        $runId = $state->get('graph_run_id');

        if (! is_int($runId)) {
            throw new RuntimeException('ParallelNode requires an integer graph_run_id in GraphState.');
        }

        $branchKeys = array_map(fn (array $b) => $b['branch_key'], $this->branches);

        $completed = AgentGraphBranchResult::query()
            ->where('graph_run_id', $runId)
            ->whereIn('branch_key', $branchKeys)
            ->where('status', AgentGraphBranchResult::STATUS_COMPLETED)
            ->pluck('result', 'branch_key');

        $missing = array_values(array_diff($branchKeys, $completed->keys()->all()));

        if ($missing === []) {
            return $state->set($this->resultStateKey, $completed->all());
        }

        $jobs = array_map(
            fn (array $branch) => new GraphBranchJob($runId, $branch['branch_key'], $branch['node'], $state->toArray()),
            $this->branches
        );

        Bus::batch($jobs)
            ->name("graph-run-{$runId}-parallel")
            ->then(function (Batch $batch) use ($runId): void {
                ResumeGraphJob::dispatch($runId);
            })
            ->catch(function (Batch $batch, Throwable $e) use ($runId): void {
                AgentGraphRun::query()->whereKey($runId)->update([
                    'status' => AgentGraphRun::STATUS_FAILED,
                    'failure_reason' => 'Parallel branch failed: '.$e->getMessage(),
                    'completed_at' => now(),
                ]);
            })
            ->dispatch();

        $state->pause('awaiting_parallel_branches');

        return $state;
    }
}
