<?php

namespace App\Modules\Ai\Application\Agent\Graph\Definitions;

use App\Modules\Ai\Application\Agent\Graph\GraphDefinition;
use App\Modules\Ai\Application\Agent\Graph\GraphState;
use App\Modules\Ai\Application\Agent\Graph\GraphStep;
use App\Modules\Ai\Application\Agent\Graph\Nodes\AgentNode;
use App\Modules\Ai\Application\Agent\Graph\Nodes\CallbackNode;
use App\Modules\Ai\Application\Agent\Graph\Nodes\CallbackRouterNode;
use App\Modules\Ai\Application\Agent\GrammarAgentService;
use App\Modules\Ai\Application\Agent\ReviewAgentService;

/**
 * Task 4.7 — `TutorAgent`-as-a-graph: a deterministic `RouterNode` picks a
 * specialist by an already-unambiguous signal (`GraphState::get('intent')`
 * — e.g. a "Explain this grammar" / "Plan my review" UI affordance that
 * already knows what it means), each specialist runs as one `AgentNode`,
 * and a merge step composes the final reply.
 *
 * **Why this is graph-directed routing, not model-directed handoff**: per
 * `ai-platform-vision.md` section 3 and ADR-006's "graduate to Workflow"
 * rule — when the signal is already explicit (a button, not free text
 * needing interpretation), spending an LLM call asking "which specialist
 * is this?" is pure waste; a deterministic `RouterNode` is strictly
 * cheaper and more predictable. Free-text requests where the right
 * specialist genuinely isn't obvious from an explicit signal remain a
 * `StudentTutorAgentService`-model-directed decision (a future
 * `HandoffTool` subclass, not built by this task — see this epic's final
 * report) — this graph is the deterministic path, not a replacement for
 * that.
 *
 * `ExerciseAgent` has no branch here: task 4.5 decided not to build it
 * (see roadmap section 9) — `GenerateQuizTool` stays a plain `Tool` on
 * `StudentTutorAgentService` directly, nothing for this router to reach.
 *
 * Like `AiAnalysisGraph` (task 4.2), this is additive: proven correct by
 * its own tests, not yet wired into `TutorConversationController` — see
 * this epic's final report for why.
 */
final class TutorRoutingGraph
{
    public const NAME = 'tutor_routing';

    public const INTENT_GRAMMAR = 'grammar';

    public const INTENT_REVIEW = 'review';

    public function __construct(
        private readonly GrammarAgentService $grammarAgent,
        private readonly ReviewAgentService $reviewAgent,
    ) {}

    public function definition(): GraphDefinition
    {
        $router = new CallbackRouterNode(fn (GraphState $state): string => match ($state->get('intent')) {
            self::INTENT_GRAMMAR => 'grammar',
            self::INTENT_REVIEW => 'review',
            default => 'fallback',
        });

        $grammarNode = new AgentNode($this->grammarAgent, 'specialist_result', 'merge');
        $reviewNode = new AgentNode($this->reviewAgent, 'specialist_result', 'merge');

        $fallback = new CallbackNode(function (GraphState $state): GraphState {
            $state->set(
                'specialist_result',
                "I'm not sure yet whether this is a grammar question or a review-planning request — could you say which?"
            );
            $state->routeTo('merge');

            return $state;
        });

        $merge = new CallbackNode(fn (GraphState $state): GraphState => $state->set(
            'final_reply',
            (string) $state->get('specialist_result', '')
        ));

        return new GraphDefinition(self::NAME, [
            new GraphStep('router', $router),
            new GraphStep('grammar', $grammarNode),
            new GraphStep('review', $reviewNode),
            new GraphStep('fallback', $fallback),
            new GraphStep('merge', $merge),
        ]);
    }
}
