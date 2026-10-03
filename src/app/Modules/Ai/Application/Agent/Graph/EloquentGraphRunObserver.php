<?php

namespace App\Modules\Ai\Application\Agent\Graph;

use App\Modules\Ai\Domain\Models\AgentGraphRun;
use App\Modules\Ai\Application\Agent\Graph\Contracts\GraphRunObserver;

/**
 * Persists a `GraphRunner` run to `agent_graph_runs` as it progresses — the
 * graph-engine analogue of how `ContentAgentService::observerFor()` persists
 * `AgentLoop` events to `agent_messages`. Bound to one already-created
 * `AgentGraphRun` row; callers create that row (via `start()` below) before
 * handing this observer to `GraphRunner::run()`.
 */
final class EloquentGraphRunObserver implements GraphRunObserver
{
    public function __construct(private readonly AgentGraphRun $run) {}

    /**
     * Creates the `agent_graph_runs` row a fresh run needs before
     * `GraphRunner::run()` is called — the durable counterpart of
     * `TraceContext::newTrace()`, at the same "start of a run" boundary.
     *
     * `$definitionVersionId` — pass `GraphDefinitionResolver::resolve()`'s
     * `versionId` here so this run records which published graph version
     * (if any) it actually ran with; `null` means it ran from the
     * hand-built PHP class, not "unknown".
     */
    public static function start(string $graphName, GraphState $initialState, string $firstStepKey, ?int $definitionVersionId = null): self
    {
        $run = AgentGraphRun::query()->create([
            'graph_name' => $graphName,
            'graph_definition_version_id' => $definitionVersionId,
            'state' => $initialState->toArray(),
            'current_node' => $firstStepKey,
            'status' => AgentGraphRun::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        return new self($run);
    }

    /**
     * Loads an existing paused run for resume() — throws if it is not
     * actually paused, since resuming a completed/failed/still-running run
     * is a caller bug, not a recoverable state.
     */
    public static function resume(int $graphRunId): self
    {
        $run = AgentGraphRun::query()->findOrFail($graphRunId);

        if ($run->status !== AgentGraphRun::STATUS_PAUSED) {
            throw new \RuntimeException(sprintf(
                'AgentGraphRun #%d cannot be resumed: status is "%s", expected "%s".',
                $run->id,
                $run->status,
                AgentGraphRun::STATUS_PAUSED
            ));
        }

        return new self($run);
    }

    public function run(): AgentGraphRun
    {
        return $this->run;
    }

    public function onStepCompleted(string $stepKey, GraphState $state): void
    {
        $this->run->update([
            'current_node' => $stepKey,
            'state' => $state->toArray(),
            'status' => AgentGraphRun::STATUS_RUNNING,
        ]);
    }

    public function onCompleted(string $lastStepKey, GraphState $state): void
    {
        $this->run->update([
            'current_node' => $lastStepKey,
            'state' => $state->toArray(),
            'status' => AgentGraphRun::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    public function onPaused(string $stepKey, GraphState $state): void
    {
        $this->run->update([
            'current_node' => $stepKey,
            'state' => $state->toArray(),
            'status' => AgentGraphRun::STATUS_PAUSED,
        ]);
    }

    public function onFailed(string $stepKey, \Throwable $e): void
    {
        $this->run->update([
            'current_node' => $stepKey,
            'status' => AgentGraphRun::STATUS_FAILED,
            'failure_reason' => $e->getMessage(),
            'completed_at' => now(),
        ]);
    }
}
