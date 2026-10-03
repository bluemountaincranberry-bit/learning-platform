<?php

namespace App\Modules\Ai\Application\Agent\Tools\Review;

use App\Exceptions\AgentToolException;
use App\Modules\Ai\Application\Agent\Contracts\AgentTool;
use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;
use App\Contracts\Ai\AiJsonClient;

/**
 * ReviewAgent tool 2/3 (task 4.6): groups a list of weak words (typically
 * from `GetWeakWordsTool`) into a short day-by-day review plan via one
 * structured completion — same `AiJsonClient::completeJson()` pattern
 * `GenerateQuizTool` uses. `sideEffect = draft_only`, same "propose, never
 * write live state" reasoning as `GenerateQuizTool` (ADR-001,
 * `agent_human_in_the_loop_apply` project memory): this never touches
 * `srs_cards.next_review_at` itself — see `ScheduleReviewTool` for why that
 * stays a separate, still-draft step rather than this tool writing dates
 * directly.
 */
class CreateReviewPlanTool implements AgentTool
{
    public function __construct(
        private readonly AiJsonClient $client,
    ) {}

    public function definition(): AgentToolDefinition
    {
        return new AgentToolDefinition(
            name: 'create_review_plan',
            description: 'Groups a list of weak words into a short day-by-day review plan (which words to revisit on which day). Proposes only — does not change any actual review schedule.',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'words' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'description' => 'The weak words to plan around, e.g. from get_weak_words.',
                    ],
                    'days' => [
                        'type' => 'integer',
                        'description' => 'How many days to spread the plan over (default 5, max 14).',
                    ],
                ],
                'required' => ['words'],
            ],
            sideEffect: AgentToolDefinition::SIDE_EFFECT_DRAFT_ONLY,
        );
    }

    public function execute(array $arguments, AgentToolContext $context): array
    {
        $words = is_array($arguments['words'] ?? null)
            ? array_values(array_filter(array_map(fn ($w) => trim((string) $w), $arguments['words']), fn ($w) => $w !== ''))
            : [];

        if ($words === []) {
            return ['error' => 'words is required and must be non-empty.'];
        }

        $days = min(14, max(1, (int) ($arguments['days'] ?? 5)));

        try {
            $result = $this->client->completeJson(
                $this->systemPrompt($days),
                'Weak words: '.implode(', ', $words),
                $this->responseSchema()
            );
        } catch (\Throwable $e) {
            throw new AgentToolException('Could not create a review plan right now: '.$e->getMessage());
        }

        $plan = $this->sanitizePlan(is_array($result['plan'] ?? null) ? $result['plan'] : []);

        return [
            'plan' => $plan,
            'is_draft' => true,
            'note' => 'This is a proposed plan only — nothing is scheduled automatically. Use schedule_review to turn it into proposed dates.',
        ];
    }

    private function systemPrompt(int $days): string
    {
        return "You are a spaced-repetition review planner. Given a list of weak words, group them into a "
            ."{$days}-day plan — a few words per day, prioritizing the words listed first (they are the weakest). "
            .'Every word must appear exactly once across the whole plan.';
    }

    /**
     * @return array<string, mixed>
     */
    private function responseSchema(): array
    {
        return [
            'plan' => [
                ['day' => 'integer (1-based)', 'items' => ['string', '...']],
            ],
        ];
    }

    /**
     * @param  array<int, mixed>  $plan
     * @return array<int, array{day: int, items: array<int, string>}>
     */
    private function sanitizePlan(array $plan): array
    {
        $sanitized = [];

        foreach ($plan as $entry) {
            if (! is_array($entry) || ! is_numeric($entry['day'] ?? null) || ! is_array($entry['items'] ?? null)) {
                continue;
            }

            $items = array_values(array_filter(array_map('strval', $entry['items']), fn ($item) => trim($item) !== ''));

            if ($items === []) {
                continue;
            }

            $sanitized[] = ['day' => (int) $entry['day'], 'items' => $items];
        }

        return $sanitized;
    }
}
