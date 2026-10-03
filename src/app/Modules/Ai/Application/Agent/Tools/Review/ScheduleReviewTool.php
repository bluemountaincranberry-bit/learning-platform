<?php

namespace App\Modules\Ai\Application\Agent\Tools\Review;

use App\Modules\Ai\Application\Agent\Contracts\AgentTool;
use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;
use Illuminate\Support\Carbon;

/**
 * ReviewAgent tool 3/3 (task 4.6): turns a day-by-day plan (from
 * `CreateReviewPlanTool`, `day` numbers relative to "today") into concrete
 * proposed calendar dates. Deliberately deterministic, no LLM call — this
 * is arithmetic on the plan the previous tool already produced, not a new
 * judgment call.
 *
 * `sideEffect = draft_only`, not `read_only`: even though this never
 * touches `srs_cards.next_review_at` (real SRS scheduling stays owned by
 * `SrsService::reviewCard()`, run through the student's normal review
 * flow, never by an agent — the same boundary `GenerateQuizTool` draws
 * around `user_lexeme_progress`), the *intent* of this tool's output is a
 * proposed change to the student's schedule, which the
 * `StudentTutorAgentService`/`HandoffTool` sideEffect ceiling
 * (`allowedSideEffects = [read_only, draft_only]`) is designed to keep
 * visible as more than a plain read — see ADR-006 on why a handoff can
 * never smuggle a stronger sideEffect than what it labels.
 */
class ScheduleReviewTool implements AgentTool
{
    public function definition(): AgentToolDefinition
    {
        return new AgentToolDefinition(
            name: 'schedule_review',
            description: 'Converts a day-by-day review plan (day numbers relative to today) into proposed calendar dates. Proposes only — does not change the student\'s actual spaced-repetition schedule.',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'plan' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'day' => ['type' => 'integer'],
                                'items' => ['type' => 'array', 'items' => ['type' => 'string']],
                            ],
                        ],
                        'description' => 'The plan produced by create_review_plan.',
                    ],
                ],
                'required' => ['plan'],
            ],
            sideEffect: AgentToolDefinition::SIDE_EFFECT_DRAFT_ONLY,
        );
    }

    public function execute(array $arguments, AgentToolContext $context): array
    {
        $plan = is_array($arguments['plan'] ?? null) ? $arguments['plan'] : [];

        if ($plan === []) {
            return ['error' => 'plan is required and must be non-empty.'];
        }

        $today = Carbon::today();
        $schedule = [];

        foreach ($plan as $entry) {
            if (! is_array($entry) || ! isset($entry['day']) || ! is_numeric($entry['day']) || ! is_array($entry['items'] ?? null)) {
                continue;
            }

            $date = $today->copy()->addDays(max(0, (int) $entry['day'] - 1));

            foreach ($entry['items'] as $item) {
                if (! is_string($item) || trim($item) === '') {
                    continue;
                }

                $schedule[] = [
                    'item' => trim($item),
                    'proposed_review_date' => $date->toDateString(),
                ];
            }
        }

        return [
            'schedule' => $schedule,
            'is_draft' => true,
            'note' => 'These are proposed dates only — the student\'s actual spaced-repetition schedule is unchanged.',
        ];
    }
}
