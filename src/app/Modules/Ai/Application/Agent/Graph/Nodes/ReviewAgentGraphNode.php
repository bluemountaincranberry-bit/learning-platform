<?php

namespace App\Modules\Ai\Application\Agent\Graph\Nodes;

use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;
use App\Modules\Ai\Application\Agent\Graph\DescribesGraphNode;
use App\Modules\Ai\Application\Agent\Graph\GraphNode;
use App\Modules\Ai\Application\Agent\Graph\GraphNodeContract;
use App\Modules\Ai\Application\Agent\Graph\GraphState;
use App\Modules\Ai\Application\Agent\ReviewAgentService;

/**
 * Task 4.12 support — `ReviewAgentService` counterpart of
 * `GrammarAgentGraphNode`; see that class's docblock for why this thin
 * per-specialist wrapper exists instead of registering `AgentNode`
 * directly.
 */
final class ReviewAgentGraphNode implements GraphNode, DescribesGraphNode
{
    private readonly AgentNode $inner;

    public function __construct(ReviewAgentService $service)
    {
        $this->inner = new AgentNode($service, 'specialist_result', 'merge');
    }

    public static function paletteLabel(): string
    {
        return 'Review specialist branch';
    }

    public static function paletteDescription(): string
    {
        return 'Runs the review/SRS specialist agent as one branch of a parallel fan-out.';
    }

    /**
     * Same "shared agent-level prompt, not node-scoped" note as
     * `GrammarAgentGraphNode::promptKey()`.
     */
    public static function promptKey(): ?string
    {
        return 'agent_'.ReviewAgentService::AGENT_TYPE.'_system_prompt';
    }

    /**
     * `sideEffect` is `draft_only`, not `read_only`: `ReviewAgentService`
     * can call `create_review_plan`/`schedule_review` (draft-only writes),
     * alongside the read-only `get_weak_words` — see
     * `PromptCatalogService`'s tool listing for this agent. `execution` —
     * see `GrammarAgentGraphNode::nodeContract()`'s note on `async_fanout`.
     */
    public static function nodeContract(): GraphNodeContract
    {
        return new GraphNodeContract(
            reads: ['task', 'conversation_id', 'acting_user_id', 'trace_id', 'parent_span_id'],
            writes: ['specialist_result'],
            sideEffect: AgentToolDefinition::SIDE_EFFECT_DRAFT_ONLY,
            execution: GraphNodeContract::EXECUTION_ASYNC_FANOUT,
            canPause: false,
            failurePolicy: GraphNodeContract::FAILURE_POLICY_FAIL,
            queuesJobs: false,
        );
    }

    public function run(GraphState $state): GraphState
    {
        return $this->inner->run($state);
    }
}
