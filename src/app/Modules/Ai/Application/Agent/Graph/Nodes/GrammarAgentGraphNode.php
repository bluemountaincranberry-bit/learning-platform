<?php

namespace App\Modules\Ai\Application\Agent\Graph\Nodes;

use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;
use App\Modules\Ai\Application\Agent\GrammarAgentService;
use App\Modules\Ai\Application\Agent\Graph\DescribesGraphNode;
use App\Modules\Ai\Application\Agent\Graph\GraphNode;
use App\Modules\Ai\Application\Agent\Graph\GraphNodeContract;
use App\Modules\Ai\Application\Agent\Graph\GraphState;

/**
 * Task 4.12 support: a `GraphNodeRegistry`-resolvable branch for
 * `StudyPlanGraph`'s `ParallelNode`. Exists because `AgentNode` (task 4.7)
 * is generic over the `SpecialistAgentService` *interface*, which the
 * container can't disambiguate when resolving a bare `AgentNode::class`
 * registry entry (it wouldn't know whether to inject
 * `GrammarAgentService` or `ReviewAgentService`) — this thin, concrete
 * wrapper pins it to `GrammarAgentService` specifically so
 * `GraphNodeRegistry::resolve('grammar_agent_branch')` is unambiguous.
 *
 * Composes `AgentNode` rather than duplicating its logic. The
 * `routeToAfter` it's built with is inert here: as a `ParallelNode`
 * branch, this runs inside `GraphBranchJob` against its own
 * branch-local `GraphState`, and only that state's plain data (`toArray()`)
 * is ever read back (into `agent_graph_branch_results.result`) — any
 * `routeTo()` call on that local, ephemeral state is simply never
 * consulted, unlike when `AgentNode` is used directly as a sequential
 * graph step (`TutorRoutingGraph`).
 */
final class GrammarAgentGraphNode implements GraphNode, DescribesGraphNode
{
    private readonly AgentNode $inner;

    public function __construct(GrammarAgentService $service)
    {
        $this->inner = new AgentNode($service, 'specialist_result', 'merge');
    }

    public static function paletteLabel(): string
    {
        return 'Grammar specialist branch';
    }

    public static function paletteDescription(): string
    {
        return 'Runs the grammar specialist agent as one branch of a parallel fan-out.';
    }

    /**
     * The grammar specialist's *agent-level* system prompt
     * (`AiServiceProvider::resolveBlueprintSystemPrompt()`) — shared with
     * every other place this agent runs (direct handoff, other graphs),
     * not scoped to this one graph step.
     */
    public static function promptKey(): ?string
    {
        return 'agent_'.GrammarAgentService::AGENT_TYPE.'_system_prompt';
    }

    /**
     * `sideEffect` is `read_only`: every tool `GrammarAgentService` can
     * call (`grammar_search`, `grammar_explanation_lookup`,
     * `analyze_grammar_error` — see `PromptCatalogService`'s tool listing
     * for this agent) only ever reads. `execution` is
     * `async_fanout`, not `sync`: as a `ParallelNode` branch this runs
     * inside a queued `GraphBranchJob`, not in the same process/request as
     * the rest of the graph.
     */
    public static function nodeContract(): GraphNodeContract
    {
        return new GraphNodeContract(
            reads: ['task', 'conversation_id', 'acting_user_id', 'trace_id', 'parent_span_id'],
            writes: ['specialist_result'],
            sideEffect: AgentToolDefinition::SIDE_EFFECT_READ_ONLY,
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
