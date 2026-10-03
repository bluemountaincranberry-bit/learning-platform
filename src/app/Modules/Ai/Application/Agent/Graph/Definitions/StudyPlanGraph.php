<?php

namespace App\Modules\Ai\Application\Agent\Graph\Definitions;

use App\Modules\Ai\Application\Agent\Graph\GraphDefinition;
use App\Modules\Ai\Application\Agent\Graph\GraphNodeRegistry;
use App\Modules\Ai\Application\Agent\Graph\GraphState;
use App\Modules\Ai\Application\Agent\Graph\GraphStep;
use App\Modules\Ai\Application\Agent\Graph\Nodes\CallbackNode;
use App\Modules\Ai\Application\Agent\Graph\Nodes\ParallelNode;

/**
 * Task 4.12 — the composite "study plan" scenario (the IELTS-prep example
 * from `ai-platform-vision.md`, section 6), built as an explicit
 * `GraphDefinition` with `ParallelNode` (task 4.11) instead of
 * `PlanningAgentService` model-directing a chain of `HandoffTool` calls.
 * This is exactly the "graduate to Workflow" rule from ADR-006/that
 * section: "план подготовки -> параллельно {специалисты} -> мердж" is a
 * regular, known-in-advance composition, not something that needs an LLM
 * deciding "who do I call" on every request — a deterministic graph is
 * cheaper (no LLM call spent on that decision) and more predictable.
 *
 * Only two branches (`grammar`, `review`) rather than the vision doc's
 * three (`Speaking`, `Grammar`, `Exercise`): `SpeakingAgent` needs TTS/STT
 * this project doesn't have yet (explicitly out of scope, see roadmap
 * section 10), and task 4.5 decided not to build `ExerciseAgent`. The
 * graph shape (fan-out to N specialists, fan-in to one merged plan) is the
 * point being proven here, not the specific specialist count — adding a
 * third branch later is a config/registry change, not a redesign.
 */
final class StudyPlanGraph
{
    public const NAME = 'study_plan';

    public function __construct(private readonly GraphNodeRegistry $registry) {}

    public function definition(): GraphDefinition
    {
        $parallel = new ParallelNode([
            ['branch_key' => 'grammar', 'node' => 'grammar_agent_branch'],
            ['branch_key' => 'review', 'node' => 'review_agent_branch'],
        ], resultStateKey: 'branch_results');

        $merge = new CallbackNode(function (GraphState $state): GraphState {
            $branchResults = $state->get('branch_results', []);

            $grammarText = $branchResults['grammar']['specialist_result'] ?? null;
            $reviewText = $branchResults['review']['specialist_result'] ?? null;

            $sections = array_filter([
                is_string($grammarText) && $grammarText !== '' ? "Grammar focus:\n{$grammarText}" : null,
                is_string($reviewText) && $reviewText !== '' ? "Review plan:\n{$reviewText}" : null,
            ]);

            return $state->set('final_reply', implode("\n\n", $sections));
        });

        return new GraphDefinition(self::NAME, [
            new GraphStep('parallel', $parallel),
            new GraphStep('merge', $merge),
        ]);
    }
}
