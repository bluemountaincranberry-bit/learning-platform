<?php

namespace App\Modules\Ai\Application\Agent\Tools\Interview;

use App\Modules\Ai\Application\Agent\Contracts\AgentTool;
use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;
use App\Modules\Interview\Application\Contracts\InterviewSessionContextReader;

final class GetInterviewPracticeContextTool implements AgentTool
{
    public function __construct(private readonly InterviewSessionContextReader $contextReader) {}

    public function definition(): AgentToolDefinition
    {
        return new AgentToolDefinition(
            name: 'get_interview_practice_context',
            description: 'Read the confirmed career profile, session questions and a small sample of learned English vocabulary for the current private practice session.',
            parameters: ['type' => 'object', 'properties' => (object) [], 'required' => []],
            sideEffect: AgentToolDefinition::SIDE_EFFECT_READ_ONLY,
        );
    }

    public function execute(array $arguments, AgentToolContext $context): array
    {
        return $this->contextReader->forConversation($context->conversationId, $context->actingUserId);
    }
}
