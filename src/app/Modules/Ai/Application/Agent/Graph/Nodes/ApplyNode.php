<?php

namespace App\Modules\Ai\Application\Agent\Graph\Nodes;

use App\Modules\Ai\Domain\Models\AiAnalysisRun;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;
use App\Modules\Ai\Application\Agent\Graph\DescribesGraphNode;
use App\Modules\Ai\Application\Agent\Graph\GraphNode;
use App\Modules\Ai\Application\Agent\Graph\GraphNodeContract;
use App\Modules\Ai\Application\Agent\Graph\GraphState;
use App\Modules\Ai\Application\AiCandidateApplyService;

/**
 * Final step of `AiAnalysisGraph` (task 4.2) — wraps the same
 * `AiCandidateApplyService::apply()` the existing "Apply approved AI
 * candidates" Filament action already calls
 * (`ApplyAiCandidatesAction`/`agent_human_in_the_loop_apply` project
 * memory).
 *
 * As of task 4.8, this node only ever applies — it no longer decides
 * whether it's allowed to run. That decision moved to a `HumanCheckpointNode`
 * step immediately before this one in `AiAnalysisGraph::definition()`
 * (previously, before task 4.8, this node paused itself; see git history /
 * this epic's final report for that ad hoc-to-generalized-node refactor).
 *
 * `GraphState::get('test_mode')` is a second, independent safety interlock
 * (graph-builder "test run this graph version" feature —
 * `GraphDefinitionTestRunService`): wrapping a whole test run in a DB
 * transaction that gets rolled back is enough to undo `AnalyzeNode`'s/
 * `MatchNode`'s candidate rows, but NOT enough to undo this node —
 * `AiCandidateApplyService::apply()` dispatches
 * `ComputeGrammarRuleEmbeddingsJob`/lexeme-sync jobs, which are queued
 * outside the Postgres transaction and would still fire for real even
 * after a rollback. So this node refuses to call `apply()` at all when
 * `test_mode` is set — a structural guarantee, not something that
 * depends on every draft graph happening to keep a `HumanCheckpointNode`
 * in front of this step. Any future node with a real-world side effect
 * (an external API call, a queued job, anything a DB rollback can't
 * undo) must add the same check — see `GraphDefinitionTestRunService`'s
 * docblock.
 */
final class ApplyNode implements GraphNode, DescribesGraphNode
{
    public function __construct(private readonly AiCandidateApplyService $service) {}

    public static function paletteLabel(): string
    {
        return 'Apply approved candidates';
    }

    public static function paletteDescription(): string
    {
        return 'Applies accepted AI candidates into the live catalog.';
    }

    public static function promptKey(): ?string
    {
        return null;
    }

    public static function nodeContract(): GraphNodeContract
    {
        return new GraphNodeContract(
            reads: ['ai_analysis_run_id', 'test_mode'],
            writes: ['applied', 'skipped_reason'],
            sideEffect: AgentToolDefinition::SIDE_EFFECT_PUBLISH,
            execution: GraphNodeContract::EXECUTION_SYNC,
            canPause: false,
            failurePolicy: GraphNodeContract::FAILURE_POLICY_FAIL,
            queuesJobs: true,
        );
    }

    public function run(GraphState $state): GraphState
    {
        if ($state->get('test_mode', false)) {
            return $state->set('applied', null)->set('skipped_reason', 'test_mode');
        }

        $run = AiAnalysisRun::query()->findOrFail($state->get('ai_analysis_run_id'));

        $result = $this->service->apply($run);

        return $state->set('applied', $result);
    }
}
