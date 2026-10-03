<?php

namespace App\Modules\Ai\Application\Agent\Graph\Nodes;

use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;
use App\Modules\Ai\Application\Agent\Graph\DescribesGraphNode;
use App\Modules\Ai\Application\Agent\Graph\GraphNode;
use App\Modules\Ai\Application\Agent\Graph\GraphNodeContract;
use App\Modules\Ai\Application\Agent\Graph\GraphState;

/**
 * Task 4.8 — generalizes what task 4.2's `ApplyNode` did ad hoc (pause
 * itself unless already approved) into a reusable, standalone node: any
 * graph can insert a `HumanCheckpointNode` step wherever it needs to stop
 * and wait for an explicit external decision, without that decision logic
 * living inside the node that actually does the work afterwards.
 * `AiAnalysisGraph` (task 4.2) is refactored by this task to use it ahead
 * of `ApplyNode`, which no longer checks approval itself — see that
 * class's updated docblock.
 *
 * On first entry, pauses unless `$approvalStateKey` is already truthy in
 * state. `GraphRunner::resume()` re-enters at this exact step (see
 * `GraphRunner`'s docblock on why resume does not skip ahead) — whoever
 * calls resume is expected to have set `$approvalStateKey` to true first
 * (e.g. `AiAnalysisGraphService::approveAndResume()`), so the second
 * `run()` call sees it approved and lets the graph continue past this
 * step instead of pausing again.
 */
final class HumanCheckpointNode implements GraphNode, DescribesGraphNode
{
    public function __construct(
        private readonly string $approvalStateKey = 'approved',
        private readonly string $pauseReason = 'awaiting_human_review',
    ) {}

    public static function paletteLabel(): string
    {
        return 'Human review checkpoint';
    }

    public static function paletteDescription(): string
    {
        return 'Pauses the run until a human explicitly approves, then continues from this exact step.';
    }

    public static function promptKey(): ?string
    {
        return null;
    }

    /**
     * `reads`/pause-key shown at the constructor's default ("approved") —
     * a differently-configured instance (a non-default `$approvalStateKey`)
     * isn't reflected here, same "illustrative, not per-instance-exact"
     * limitation `promptKey()` already documents for `HumanCheckpointNode`
     * generally.
     */
    public static function nodeContract(): GraphNodeContract
    {
        return new GraphNodeContract(
            reads: ['approved'],
            writes: [],
            sideEffect: AgentToolDefinition::SIDE_EFFECT_READ_ONLY,
            execution: GraphNodeContract::EXECUTION_SYNC,
            canPause: true,
            failurePolicy: GraphNodeContract::FAILURE_POLICY_FAIL,
            queuesJobs: false,
        );
    }

    public function run(GraphState $state): GraphState
    {
        if (! $state->get($this->approvalStateKey, false)) {
            $state->pause($this->pauseReason);
        }

        return $state;
    }
}
