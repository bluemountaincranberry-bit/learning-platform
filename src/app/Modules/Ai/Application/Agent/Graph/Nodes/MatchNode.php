<?php

namespace App\Modules\Ai\Application\Agent\Graph\Nodes;

use App\Modules\Ai\Domain\Models\AiAnalysisRun;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;
use App\Modules\Ai\Application\Agent\Graph\DescribesGraphNode;
use App\Modules\Ai\Application\Agent\Graph\GraphNode;
use App\Modules\Ai\Application\Agent\Graph\GraphNodeContract;
use App\Modules\Ai\Application\Agent\Graph\GraphState;
use App\Modules\Ai\Application\CandidateMatchingService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Second step of `AiAnalysisGraph` (task 4.2) — wraps the same
 * `CandidateMatchingService::matchRun()` `RunAiContentAnalysisJob` already
 * calls. Deliberately does not decide anything about pausing for human
 * review — see `ApplyNode`'s docblock for where that lives at this stage of
 * the epic, and task 4.8 for where it moves to.
 *
 * Task 8.1: matching is best-effort here, exactly like
 * `RunAiContentAnalysisJob`'s own try/catch around `matchRun()` — a matching
 * hiccup (e.g. the embeddings API being down) is logged and swallowed
 * rather than failing the whole graph run, so `apply` still gets a chance
 * to run on whatever candidates `analyze` already found.
 */
final class MatchNode implements GraphNode, DescribesGraphNode
{
    public function __construct(private readonly CandidateMatchingService $service) {}

    public static function paletteLabel(): string
    {
        return 'Match candidates';
    }

    public static function paletteDescription(): string
    {
        return 'Matches extracted candidates against existing canonical lexemes/grammar rules.';
    }

    public static function promptKey(): ?string
    {
        return null;
    }

    public static function nodeContract(): GraphNodeContract
    {
        return new GraphNodeContract(
            reads: ['ai_analysis_run_id'],
            writes: ['matched'],
            sideEffect: AgentToolDefinition::SIDE_EFFECT_DRAFT_ONLY,
            execution: GraphNodeContract::EXECUTION_SYNC,
            canPause: false,
            failurePolicy: GraphNodeContract::FAILURE_POLICY_BEST_EFFORT,
            queuesJobs: false,
        );
    }

    public function run(GraphState $state): GraphState
    {
        $run = AiAnalysisRun::query()->findOrFail($state->get('ai_analysis_run_id'));

        try {
            $this->service->matchRun($run);

            return $state->set('matched', true);
        } catch (Throwable $e) {
            Log::warning('CandidateMatchingService failed for analysis run (graph path)', [
                'run_id' => $run->id,
                'message' => $e->getMessage(),
            ]);

            return $state->set('matched', false);
        }
    }
}
