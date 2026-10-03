<?php

namespace App\Modules\Ai\Application\Agent\Tools\Student;

use App\Modules\Ai\Application\Agent\Contracts\AgentTool;
use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;
use App\Modules\Learning\Application\Contracts\StudentVocabularyReaderInterface;

/**
 * Progress tool (task 3.3): total learned-vocabulary count for the acting
 * student from `user_lexeme_progress`, optionally scoped to one language,
 * broken down by CEFR level — the same join `GetUserLevelTool` uses, just
 * answering "how many words" instead of "what level".
 */
class GetVocabularySizeTool implements AgentTool
{
    public function __construct(private readonly StudentVocabularyReaderInterface $vocabulary) {}

    public function definition(): AgentToolDefinition
    {
        return new AgentToolDefinition(
            name: 'get_vocabulary_size',
            description: 'Returns the total number of words/phrases the student has learned, with a breakdown by CEFR level.',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'language' => [
                        'type' => 'string',
                        'description' => 'Optional ISO 639-1 language code to scope to, e.g. "en". Omit for all languages.',
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

        $summary = $this->vocabulary->summary($context->actingUserId, $language);

        return [
            'total_learned_word_count' => $summary['total'],
            'level_breakdown' => $summary['levels'],
        ];
    }
}
