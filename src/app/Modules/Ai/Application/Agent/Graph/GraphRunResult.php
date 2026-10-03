<?php

namespace App\Modules\Ai\Application\Agent\Graph;

/**
 * What `GraphRunner::run()` hands back to its caller once it stops —
 * either because the definition ran out of steps (`completed`), a node
 * asked to pause (`paused`), or a node threw (`failed`). Mirrors
 * `AgentGraphRun::STATUSES` (minus `running`, which only exists while a run
 * is actively executing, never as a final result) — this is the in-memory
 * counterpart of that row, not a duplicate source of truth.
 *
 * `currentStepKey` on a paused result is where `resume()` re-enters — the
 * *same* step that paused, not the one after it. That is deliberate: a
 * pausing node (e.g. `HumanCheckpointNode`, task 4.8) is itself responsible
 * for deciding, given the state resume() was called with, whether the
 * condition it was waiting on is now satisfied — the runner does not guess
 * that on the node's behalf.
 */
final class GraphRunResult
{
    public const STATUS_COMPLETED = 'completed';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_FAILED = 'failed';

    private function __construct(
        public readonly string $status,
        public readonly GraphState $state,
        public readonly ?string $currentStepKey,
        public readonly ?string $failureReason = null,
    ) {}

    public static function completed(GraphState $state, string $lastStepKey): self
    {
        return new self(self::STATUS_COMPLETED, $state, $lastStepKey);
    }

    public static function paused(GraphState $state, string $pausedAtStepKey): self
    {
        return new self(self::STATUS_PAUSED, $state, $pausedAtStepKey);
    }

    public static function failed(GraphState $state, string $failedStepKey, string $reason): self
    {
        return new self(self::STATUS_FAILED, $state, $failedStepKey, $reason);
    }
}
