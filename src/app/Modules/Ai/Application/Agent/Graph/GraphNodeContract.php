<?php

namespace App\Modules\Ai\Application\Agent\Graph;

/**
 * What `DescribesGraphNode::nodeContract()` returns — a static, human-facing
 * description of what one node type actually does to `GraphState` and the
 * outside world, for the builder canvas's node property panel. Exists
 * because a node card on its own only ever showed "this is an Apply node"
 * — indistinguishable, at a glance, from a node that does nothing but
 * pause. This is deliberately a *declared* contract (each node states its
 * own reads/writes by hand, the same way `AgentToolDefinition::sideEffect`
 * is a declared value, not inferred from source) — reflection-based
 * inference would be more "automatic" but silently wrong the moment a
 * node's `run()` changes without its contract being updated, which is
 * worse than requiring the update explicitly.
 *
 * `sideEffect` reuses `AgentToolDefinition::SIDE_EFFECT_*` rather than
 * inventing a second vocabulary — a node and a tool are both "a reviewed
 * unit of code that may or may not touch real data", the same concern in
 * both places (see that class's docblock). `queuesJobs` is the one thing
 * `sideEffect` alone can't express: whether this node's effects survive a
 * rolled-back DB transaction — the exact question
 * `GraphDefinitionTestRunService`'s test-mode safety layer had to answer
 * for `ApplyNode` specifically (queued jobs live outside Postgres).
 */
final readonly class GraphNodeContract
{
    public const EXECUTION_SYNC = 'sync';

    public const EXECUTION_ASYNC_FANOUT = 'async_fanout';

    public const FAILURE_POLICY_FAIL = 'fail';

    public const FAILURE_POLICY_BEST_EFFORT = 'best_effort';

    /**
     * @param  array<int, string>  $reads   GraphState keys this node reads, illustrative (a configurable key like HumanCheckpointNode's approvalStateKey is shown at its default).
     * @param  array<int, string>  $writes  GraphState keys this node sets.
     */
    public function __construct(
        public array $reads,
        public array $writes,
        public string $sideEffect,
        public string $execution,
        public bool $canPause,
        public string $failurePolicy,
        public bool $queuesJobs,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'reads' => $this->reads,
            'writes' => $this->writes,
            'side_effect' => $this->sideEffect,
            'execution' => $this->execution,
            'can_pause' => $this->canPause,
            'failure_policy' => $this->failurePolicy,
            'queues_jobs' => $this->queuesJobs,
        ];
    }
}
