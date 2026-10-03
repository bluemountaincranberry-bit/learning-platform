<?php

namespace App\Modules\Ai\Application\Agent\Graph\Contracts;

use App\Modules\Ai\Application\Agent\Graph\GraphState;

/**
 * How `GraphRunner` reports progress without knowing about Eloquent or
 * `agent_graph_runs` itself — the same "pure engine + observer persists"
 * split already used by `AgentLoop`/`AgentLoopObserver` (task 1.2). A real
 * implementation (`EloquentGraphRunObserver`) writes to `agent_graph_runs`
 * after every step so a run can be resumed even if the process crashes
 * mid-graph; tests can pass an in-memory recording double instead.
 */
interface GraphRunObserver
{
    /**
     * Called after a step finishes without pausing or throwing.
     */
    public function onStepCompleted(string $stepKey, GraphState $state): void;

    /**
     * Called once, after the last step in the definition completes.
     */
    public function onCompleted(string $lastStepKey, GraphState $state): void;

    /**
     * Called when a step's node leaves $state paused — the run stops here
     * until an external resume() re-enters at $stepKey.
     */
    public function onPaused(string $stepKey, GraphState $state): void;

    /**
     * Called when a step's node throws. The run stops; it does not
     * continue to later steps.
     */
    public function onFailed(string $stepKey, \Throwable $e): void;
}
