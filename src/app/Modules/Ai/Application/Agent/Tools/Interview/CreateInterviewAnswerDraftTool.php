<?php

namespace App\Modules\Ai\Application\Agent\Tools\Interview;

use App\Contracts\Ai\InterviewDraftWriter;
use App\Modules\Ai\Application\Agent\Contracts\AgentTool;
use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;

final class CreateInterviewAnswerDraftTool implements AgentTool
{
    public function __construct(private readonly InterviewDraftWriter $drafts) {}

    public function definition(): AgentToolDefinition
    {
        return new AgentToolDefinition(
            name: 'propose_interview_answer_revision',
            description: 'Propose a bilingual short or full answer revision only after the learner explicitly asks to save the revised wording. This only creates a pending proposal.',
            parameters: ['type' => 'object', 'properties' => [
                'question_id' => ['type' => 'integer'],
                'variant' => ['type' => 'string', 'enum' => ['short', 'full']],
                'text_en' => ['type' => ['string', 'null']],
                'text_ru' => ['type' => ['string', 'null']],
            ], 'required' => ['question_id', 'variant', 'text_en', 'text_ru'], 'additionalProperties' => false],
            sideEffect: AgentToolDefinition::SIDE_EFFECT_DRAFT_ONLY,
        );
    }

    public function execute(array $arguments, AgentToolContext $context): array
    {
        $id = $this->drafts->answerDraft($context->conversationId, $context->actingUserId, $arguments);

        return ['draft_id' => $id, 'status' => 'pending', 'requires_learner_confirmation' => true];
    }
}
