<?php

namespace App\Modules\Ai\Application\Agent\Tools\Interview;

use App\Contracts\Ai\InterviewDraftWriter;
use App\Modules\Ai\Application\Agent\Contracts\AgentTool;
use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;

final class CreateInterviewVocabularyDraftTool implements AgentTool
{
    public function __construct(private readonly InterviewDraftWriter $drafts) {}

    public function definition(): AgentToolDefinition
    {
        return new AgentToolDefinition(
            name: 'propose_interview_vocabulary',
            description: 'Propose one useful new English word for the learner vocabulary list. This only creates a pending proposal; never add the word directly.',
            parameters: ['type' => 'object', 'properties' => [
                'lemma' => ['type' => 'string'], 'language' => ['type' => 'string', 'enum' => ['en']],
            ], 'required' => ['lemma', 'language'], 'additionalProperties' => false],
            sideEffect: AgentToolDefinition::SIDE_EFFECT_DRAFT_ONLY,
        );
    }

    public function execute(array $arguments, AgentToolContext $context): array
    {
        $id = $this->drafts->vocabularyDraft($context->conversationId, $context->actingUserId, $arguments);

        return ['draft_id' => $id, 'status' => 'pending', 'requires_learner_confirmation' => true];
    }
}
