<?php

namespace App\Modules\Ai\Application\Agent\Tools\Interview;

use App\Contracts\Ai\InterviewDraftWriter;
use App\Modules\Ai\Application\Agent\Contracts\AgentTool;
use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;

final class CreateInterviewPatternObservationDraftTool implements AgentTool
{
    public function __construct(private readonly InterviewDraftWriter $drafts) {}

    public function definition(): AgentToolDefinition
    {
        return new AgentToolDefinition(
            name: 'propose_repeated_interview_observation',
            description: 'Propose a persistent strength or improvement only when at least two distinct completed session/question examples support it. Provide exact learner quotes and their source IDs. This only creates a pending draft and requires learner confirmation.',
            parameters: ['type' => 'object', 'properties' => [
                'pattern_type' => ['type' => 'string', 'enum' => ['strength', 'improvement']],
                'summary' => ['type' => 'string'],
                'examples' => ['type' => 'array', 'minItems' => 2, 'maxItems' => 6, 'items' => ['type' => 'object', 'properties' => [
                    'session_id' => ['type' => 'integer'],
                    'question_id' => ['type' => 'integer'],
                    'evidence' => ['type' => 'string'],
                ], 'required' => ['session_id', 'question_id', 'evidence'], 'additionalProperties' => false]],
            ], 'required' => ['pattern_type', 'summary', 'examples'], 'additionalProperties' => false],
            sideEffect: AgentToolDefinition::SIDE_EFFECT_DRAFT_ONLY,
        );
    }

    public function execute(array $arguments, AgentToolContext $context): array
    {
        $id = $this->drafts->patternObservationDraft($context->conversationId, $context->actingUserId, $arguments);

        return ['draft_id' => $id, 'status' => 'pending', 'requires_learner_confirmation' => true];
    }
}
