<?php

namespace App\Modules\Ai\Application\Agent\Graph\Nodes;

use App\Modules\Ai\Domain\Models\AiAnalysisRun;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;
use App\Modules\Ai\Application\Agent\Graph\DescribesGraphNode;
use App\Modules\Ai\Application\Agent\Graph\GraphNode;
use App\Modules\Ai\Application\Agent\Graph\GraphNodeContract;
use App\Modules\Ai\Application\Agent\Graph\GraphState;
use App\Contracts\Ai\ContentAnalysisCapability;

/**
 * First step of `AiAnalysisGraph` (task 4.2) — a `ToolNode`-shaped wrapper
 * (docs/architecture/agent-framework-roadmap.md, section 7) around the
 * exact same `AiContentAnalysisService::analyze()` that
 * `RunAiContentAnalysisJob` already calls. This does not replace that job —
 * see `AiAnalysisGraphService`'s docblock for why the production pipeline
 * keeps running through the job/Filament action while this graph proves the
 * engine on the same well-understood process.
 */
final class AnalyzeNode implements GraphNode, DescribesGraphNode
{
    public function __construct(private readonly ContentAnalysisCapability $service) {}

    public static function paletteLabel(): string
    {
        return 'Analyze transcript';
    }

    public static function paletteDescription(): string
    {
        return 'Runs AI extraction of lexeme/grammar candidates from the content transcript.';
    }

    public static function promptKey(): ?string
    {
        return 'content_analysis_system_prompt';
    }

    public static function nodeContract(): GraphNodeContract
    {
        return new GraphNodeContract(
            reads: ['ai_analysis_run_id'],
            writes: ['analyzed'],
            sideEffect: AgentToolDefinition::SIDE_EFFECT_DRAFT_ONLY,
            execution: GraphNodeContract::EXECUTION_SYNC,
            canPause: false,
            failurePolicy: GraphNodeContract::FAILURE_POLICY_FAIL,
            queuesJobs: false,
        );
    }

    public function run(GraphState $state): GraphState
    {
        $run = AiAnalysisRun::query()->findOrFail($state->get('ai_analysis_run_id'));

        $this->service->analyze($run);

        return $state->set('analyzed', true);
    }
}
