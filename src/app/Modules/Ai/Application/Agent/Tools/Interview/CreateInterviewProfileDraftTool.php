<?php

namespace App\Modules\Ai\Application\Agent\Tools\Interview;

use App\Contracts\Ai\InterviewDraftWriter;
use App\Modules\Ai\Application\Agent\Contracts\AgentTool;
use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;

final class CreateInterviewProfileDraftTool implements AgentTool
{
    public function __construct(private readonly InterviewDraftWriter $drafts) {}

    public function definition(): AgentToolDefinition
    {
        return new AgentToolDefinition(
            name: 'propose_interview_profile_update',
            description: 'Propose confirmed learner-provided preparation profile facts for explicit review. Never infer or invent personal history.',
            parameters: ['type' => 'object', 'properties' => [
                'career_goal' => ['type' => ['string', 'null']],
                'skills' => ['type' => 'array', 'items' => ['type' => 'string']],
                'experience_level' => ['type' => ['string', 'null']],
                'projects' => ['type' => 'array', 'items' => ['type' => 'string']],
                'experience_stories' => ['type' => 'array', 'items' => ['type' => 'string']],
                'milestones' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                    'title' => ['type' => 'string'], 'target_date' => ['type' => ['string', 'null']],
                ], 'required' => ['title'], 'additionalProperties' => false]],
            ], 'additionalProperties' => false],
            sideEffect: AgentToolDefinition::SIDE_EFFECT_DRAFT_ONLY,
        );
    }

    public function execute(array $arguments, AgentToolContext $context): array
    {
        $id = $this->drafts->profileDraft($context->conversationId, $context->actingUserId, $arguments);

        return ['draft_id' => $id, 'status' => 'pending', 'requires_learner_confirmation' => true];
    }
}
