<?php

namespace App\Modules\Ai\Application\Agent\Tools\Student;

use App\Contracts\Ai\SpeakingMistakeRecorderInterface;
use App\Modules\Ai\Application\Agent\Contracts\AgentTool;
use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;

final class RecordSpeakingMistakeTool implements AgentTool
{
    public function __construct(private readonly SpeakingMistakeRecorderInterface $recorder) {}

    public function definition(): AgentToolDefinition
    {
        return new AgentToolDefinition(
            name: 'record_speaking_mistake',
            description: 'Save a clear English grammar or vocabulary error from the student\'s own sentence to their private practice list, with a correction and a short explanation.',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'original_text' => ['type' => 'string', 'description' => 'The student\'s exact English wording.'],
                    'corrected_text' => ['type' => 'string', 'description' => 'A natural corrected version preserving the intended meaning.'],
                    'prompt_text' => ['type' => 'string', 'description' => 'Russian sentence or intended meaning for later English practice.'],
                    'explanation' => ['type' => 'string', 'description' => 'Brief Russian explanation of the correction.'],
                    'category' => ['type' => 'string', 'enum' => ['grammar', 'vocabulary', 'articles', 'word_order', 'verb_tense', 'preposition', 'pronunciation', 'general']],
                ],
                'required' => ['original_text', 'corrected_text', 'prompt_text', 'explanation', 'category'],
            ],
            sideEffect: AgentToolDefinition::SIDE_EFFECT_LEARNER_MEMORY,
        );
    }

    public function execute(array $arguments, AgentToolContext $context): array
    {
        $result = $this->recorder->recordWrongAnswer($context->actingUserId, [
            'language' => 'en',
            'native_language' => 'ru',
            'prompt_text' => $arguments['prompt_text'] ?? null,
            'original_text' => $arguments['original_text'] ?? '',
            'corrected_text' => $arguments['corrected_text'] ?? '',
            'explanation' => $arguments['explanation'] ?? null,
            'category' => $arguments['category'] ?? 'general',
            'source_type' => 'chat',
            'source_id' => $context->conversationId,
        ]);

        return $result ?? ['saved' => false, 'reason' => 'The example was incomplete.'];
    }
}
