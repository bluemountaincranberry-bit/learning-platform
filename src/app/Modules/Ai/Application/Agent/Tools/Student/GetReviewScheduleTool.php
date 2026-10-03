<?php

namespace App\Modules\Ai\Application\Agent\Tools\Student;

use App\Modules\Ai\Application\Agent\Contracts\AgentTool;
use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;
use App\Modules\Srs\Application\Contracts\ReviewScheduleReaderInterface;

/**
 * Progress tool (task 3.3): the acting student's spaced-repetition review
 * schedule from `srs_cards` — how many cards are due right now, and the
 * next few upcoming ones in order. Grounds "what's due today?" /
 * "what should I study next?" instead of the model guessing.
 */
class GetReviewScheduleTool implements AgentTool
{
    public function __construct(private readonly ReviewScheduleReaderInterface $schedule) {}

    public function definition(): AgentToolDefinition
    {
        return new AgentToolDefinition(
            name: 'get_review_schedule',
            description: 'Returns how many spaced-repetition cards are due for review right now, and the next few upcoming reviews in order.',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of upcoming cards to list (default 10, max 50).',
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

        return $this->schedule->forUser($context->actingUserId, $limit);
    }
}
