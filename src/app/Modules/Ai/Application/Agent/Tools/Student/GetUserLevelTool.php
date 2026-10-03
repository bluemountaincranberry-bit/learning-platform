<?php

namespace App\Modules\Ai\Application\Agent\Tools\Student;

use App\Modules\Ai\Application\Agent\Contracts\AgentTool;
use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;
use App\Modules\Learning\Application\Contracts\StudentVocabularyReaderInterface;

/**
 * Read-only proof-of-concept tool for `StudentTutorAgentService` (task
 * 1.10): a genuine, if simple, estimate of the acting student's CEFR level
 * from the vocabulary they have already learned (`user_lexeme_progress`
 * joined to `lexemes.level`) — not a stub. Real tutoring tools
 * (`GetWeakTopicsTool`, `GetDueReviewsTool`, ...) belong to EPIC 3
 * (`docs/architecture/ai-platform-vision.md`, section 4); this one exists
 * to prove the shared runtime genuinely works for a second agent.
 */
class GetUserLevelTool implements AgentTool
{
    public function __construct(private readonly StudentVocabularyReaderInterface $vocabulary) {}

    public function definition(): AgentToolDefinition
    {
        return new AgentToolDefinition(
            name: 'get_user_level',
            description: 'Estimates the current student\'s CEFR level (A1-C2) from the vocabulary they have already learned, optionally scoped to one language.',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'language' => [
                        'type' => 'string',
                        'description' => 'Optional ISO 639-1 language code to scope the estimate to, e.g. "en". Omit to use all languages the student has learned words in.',
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

        $counts = $this->vocabulary->summary($context->actingUserId, $language)['levels'];

        if ($counts === []) {
            return [
                'learned_word_count' => 0,
                'estimated_level' => null,
                'note' => 'No learned vocabulary with a known CEFR level yet.',
            ];
        }

        return [
            'learned_word_count' => array_sum($counts),
            'level_breakdown' => $counts,
            // Simple mode heuristic: the level with the most learned words so
            // far — good enough for a proof step, refine when EPIC 3 builds
            // the real progress tools.
            'estimated_level' => array_key_first(collect($counts)->sortDesc()->all()),
        ];
    }
}
