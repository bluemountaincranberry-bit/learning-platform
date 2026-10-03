<?php

namespace App\Modules\Ai\Application\Agent\Tools\Handoff;

use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;
use App\Modules\Ai\Application\Agent\Tools\HandoffTool;

/**
 * `ReviewAgentService` counterpart of `HandoffToGrammarAgentTool` — see
 * that class's docblock for why this exists and the model-directed vs
 * graph-directed distinction.
 *
 * `sideEffect = draft_only` (not `read_only`): `ReviewAgentService`'s
 * widest allowed sideEffect is `draft_only` (`CreateReviewPlanTool`/
 * `ScheduleReviewTool` propose plans), so `HandoffTool`'s wiring-time
 * coverage check requires at least that here — declaring this
 * `read_only` would under-represent what the target agent can actually
 * produce and fail to construct (see `HandoffTool::assertSideEffectCoversTarget()`).
 */
final class HandoffToReviewAgentTool extends HandoffTool
{
    public function definition(): AgentToolDefinition
    {
        return new AgentToolDefinition(
            name: 'handoff_to_review_planner',
            description: 'Hands off to a review-planning specialist to build a personalized, multi-day spaced-repetition review plan for the student — use when the student asks for a study/review plan, not for a single lookup like "what\'s due today" (use get_review_schedule for that instead).',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'task' => [
                        'type' => 'string',
                        'description' => 'The student\'s request, in enough detail for the specialist to work from without seeing the rest of the conversation.',
                    ],
                ],
                'required' => ['task'],
            ],
            sideEffect: AgentToolDefinition::SIDE_EFFECT_DRAFT_ONLY,
        );
    }
}
