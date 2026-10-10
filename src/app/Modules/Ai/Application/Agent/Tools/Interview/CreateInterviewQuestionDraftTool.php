<?php

namespace App\Modules\Ai\Application\Agent\Tools\Interview;

use App\Contracts\Ai\InterviewDraftWriter;
use App\Modules\Ai\Application\Agent\Contracts\AgentTool;
use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;

final class CreateInterviewQuestionDraftTool implements AgentTool
{
    public function __construct(private readonly InterviewDraftWriter $drafts) {}

    public function definition(): AgentToolDefinition
    {
        return new AgentToolDefinition(
            name: 'propose_interview_question',
            description: 'Create a pending bilingual interview question proposal for the learner to review. Never claim it is saved to their question bank.',
            parameters: ['type' => 'object', 'properties' => [
                'prompt_en' => ['type' => 'string'], 'prompt_ru' => ['type' => ['string', 'null']],
                'topic_id' => ['type' => ['integer', 'null']], 'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
            ], 'required' => ['prompt_en', 'prompt_ru'], 'additionalProperties' => false],
            sideEffect: AgentToolDefinition::SIDE_EFFECT_DRAFT_ONLY,
        );
    }

    public function execute(array $arguments, AgentToolContext $context): array
    {
        $id = $this->drafts->questionDraft($context->conversationId, $context->actingUserId, $arguments);

        return ['draft_id' => $id, 'status' => 'pending', 'requires_learner_confirmation' => true];
    }
}
