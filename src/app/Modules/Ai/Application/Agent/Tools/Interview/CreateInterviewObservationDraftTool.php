<?php

namespace App\Modules\Ai\Application\Agent\Tools\Interview;

use App\Contracts\Ai\InterviewDraftWriter;
use App\Modules\Ai\Application\Agent\Contracts\AgentTool;
use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;

final class CreateInterviewObservationDraftTool implements AgentTool
{
    public function __construct(private readonly InterviewDraftWriter $drafts) {}

    public function definition(): AgentToolDefinition
    {
        return new AgentToolDefinition(
            name: 'propose_interview_question_state',
            description: 'Suggest a preparation state for a question from this active practice session. Include a concrete answer quote and a concise reason. This only creates a pending proposal; never update the question directly.',
            parameters: ['type' => 'object', 'properties' => [
                'question_id' => ['type' => 'integer'],
                'preparation_state' => ['type' => 'string', 'enum' => ['needs_practice', 'confident']],
                'evidence' => ['type' => 'string'],
                'reason' => ['type' => 'string'],
            ], 'required' => ['question_id', 'preparation_state', 'evidence', 'reason'], 'additionalProperties' => false],
            sideEffect: AgentToolDefinition::SIDE_EFFECT_DRAFT_ONLY,
        );
    }

    public function execute(array $arguments, AgentToolContext $context): array
    {
        $id = $this->drafts->observationDraft($context->conversationId, $context->actingUserId, $arguments);

        return ['draft_id' => $id, 'status' => 'pending', 'requires_learner_confirmation' => true];
    }
}
