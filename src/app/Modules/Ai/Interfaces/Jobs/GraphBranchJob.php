<?php

namespace App\Modules\Ai\Interfaces\Jobs;

use App\Modules\Ai\Domain\Models\AgentGraphBranchResult;
use App\Modules\Ai\Application\Agent\Graph\GraphNodeRegistry;
use App\Modules\Ai\Application\Agent\Graph\GraphState;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GraphBranchJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    /** @param array<string, mixed> $branchStateData */
    public function __construct(
        public readonly int $graphRunId,
        public readonly string $branchKey,
        public readonly string $nodeKey,
        public readonly array $branchStateData,
    ) {}

    public function tags(): array
    {
        return ['graph-run:'.$this->graphRunId, 'graph-branch:'.$this->branchKey, 'job:graph-branch'];
    }

    public function handle(GraphNodeRegistry $registry): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        AgentGraphBranchResult::query()->updateOrCreate(
            ['graph_run_id' => $this->graphRunId, 'branch_key' => $this->branchKey],
            ['status' => AgentGraphBranchResult::STATUS_RUNNING],
        );

        try {
            $resultState = $registry->resolve($this->nodeKey)->run(new GraphState($this->branchStateData));
            AgentGraphBranchResult::query()
                ->where('graph_run_id', $this->graphRunId)
                ->where('branch_key', $this->branchKey)
                ->update(['result' => $resultState->toArray(), 'status' => AgentGraphBranchResult::STATUS_COMPLETED]);
        } catch (Throwable $e) {
            AgentGraphBranchResult::query()
                ->where('graph_run_id', $this->graphRunId)
                ->where('branch_key', $this->branchKey)
                ->update(['status' => AgentGraphBranchResult::STATUS_FAILED, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
