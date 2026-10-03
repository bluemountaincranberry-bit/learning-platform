<?php

namespace App\Modules\Ai\Application\Agent\Tools\Student;

use App\Modules\Ai\Application\Agent\Contracts\AgentTool;
use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;
use App\Modules\Learning\Application\Contracts\StudentVocabularyReaderInterface;

/**
 * Memory tool (task 3.2): the acting student's vocabulary-learning history
 * from `user_lexeme_progress`, most recently learned first, optionally
 * scoped to one language — grounds questions like "what have I learned
 * recently?" or "how many French words do I know?" in real data.
 */
class GetLearningHistoryTool implements AgentTool
{
    public function __construct(private readonly StudentVocabularyReaderInterface $vocabulary) {}

    public function definition(): AgentToolDefinition
    {
        return new AgentToolDefinition(
            name: 'get_learning_history',
            description: "Returns the student's vocabulary-learning history — words/phrases they have already learned, most recently learned first.",
            parameters: [
                'type' => 'object',
                'properties' => [
                    'language' => [
                        'type' => 'string',
                        'description' => 'Optional ISO 639-1 language code to scope to, e.g. "en". Omit for all languages.',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of words to return (default 20, max 100).',
                    ],
                ],
                'required' => [],
            ],
            sideEffect: AgentToolDefinition::SIDE_EFFECT_READ_ONLY,
        );
    }

    public function execute(array $arguments, AgentToolContext $context): array
    {
        $language = isset($arguments['language']) ? strtolower(trim((string) $arguments['language'])) : null;
        $limit = min(100, max(1, (int) ($arguments['limit'] ?? 20)));

        $history = $this->vocabulary->history($context->actingUserId, $language, $limit);

        return [
            'learned_word_count' => count($history),
            'words' => $history,
        ];
    }
}
