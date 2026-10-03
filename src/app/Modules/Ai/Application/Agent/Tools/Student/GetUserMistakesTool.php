<?php

namespace App\Modules\Ai\Application\Agent\Tools\Student;

use App\Modules\Ai\Application\Agent\Contracts\AgentTool;
use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;
use App\Modules\Srs\Application\Contracts\ReviewMistakesReaderInterface;

/**
 * Memory tool (task 3.2): the acting student's most recent failed
 * spaced-repetition reviews, straight from `srs_reviews`/`srs_cards` — the
 * same "grade <= 2 is a failure" convention `SrsService::reviewCard()`
 * already uses to decide `state = relearning`. Grounds answers like "what
 * am I getting wrong?" instead of letting the model guess.
 */
class GetUserMistakesTool implements AgentTool
{
    public const FAILING_GRADE_THRESHOLD = 2;

    public function __construct(private readonly ReviewMistakesReaderInterface $mistakes) {}

    public function definition(): AgentToolDefinition
    {
        return new AgentToolDefinition(
            name: 'get_user_mistakes',
            description: "Returns the student's most recent failed spaced-repetition reviews (words/phrases they got wrong), most recent first.",
            parameters: [
                'type' => 'object',
                'properties' => [
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of mistakes to return (default 10, max 50).',
                    ],
                ],
                'required' => [],
            ],
            sideEffect: AgentToolDefinition::SIDE_EFFECT_READ_ONLY,
        );
    }

    public function execute(array $arguments, AgentToolContext $context): array
    {
        $limit = min(50, max(1, (int) ($arguments['limit'] ?? 10)));

        return $this->mistakes->recentForUser($context->actingUserId, $limit);
    }
}
