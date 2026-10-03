<?php

namespace App\Modules\Ai\Application\Agent;

use App\Modules\Ai\Application\Agent\Contracts\SpecialistAgentService;
use App\Modules\Ai\Application\Agent\Data\AgentBlueprint;
use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;
use App\Modules\Ai\Application\Agent\Support\NonPersistingAgentObserver;
use App\Modules\Ai\Application\Agent\Tools\Review\CreateReviewPlanTool;
use App\Modules\Ai\Application\Agent\Tools\Review\GetWeakWordsTool;
use App\Modules\Ai\Application\Agent\Tools\Review\ScheduleReviewTool;
use App\Modules\Ai\Application\Agent\Tracing\SpanRecorder;
use App\Modules\Ai\Application\Agent\Tracing\TraceContext;

/**
 * Second specialist agent (task 4.6) — a review-planning loop (find weak
 * words -> group into a day-by-day plan -> turn that into proposed dates),
 * same shape and same "not a third coordinator" status as
 * `GrammarAgentService` (see that class's docblock; ADR-001). Justified as
 * an `Agent` rather than folding into one `Tool` because the three steps
 * are genuinely sequential and each depends on the previous one's output
 * (`GetWeakWordsTool` -> `CreateReviewPlanTool` -> `ScheduleReviewTool`),
 * not a single completion with ready inputs (ADR-002).
 *
 * `allowedSideEffects` includes `draft_only` — `CreateReviewPlanTool`/
 * `ScheduleReviewTool` only ever propose, never write to `srs_cards`
 * directly (see those classes' docblocks).
 */
final class ReviewAgentService implements SpecialistAgentService
{
    public const AGENT_TYPE = 'review';

    public function __construct(
        private readonly AgentLoop $loop,
        private readonly AgentBlueprint $blueprint,
        private readonly array $tools,
        private readonly SpanRecorder $spanRecorder,
    ) {}

    public static function blueprint(): AgentBlueprint
    {
        return new AgentBlueprint(
            name: self::AGENT_TYPE,
            systemPrompt: self::systemPromptText(),
            tools: [
                GetWeakWordsTool::class,
                CreateReviewPlanTool::class,
                ScheduleReviewTool::class,
            ],
            maxIterations: 4,
            allowedSideEffects: [
                AgentToolDefinition::SIDE_EFFECT_READ_ONLY,
                AgentToolDefinition::SIDE_EFFECT_DRAFT_ONLY,
            ],
        );
    }

    public function run(string $task, AgentToolContext $context, TraceContext $trace): string
    {
        $observer = new NonPersistingAgentObserver;

        $this->loop->run(
            systemPrompt: $this->blueprint->systemPrompt,
            startingMessages: [['role' => 'user', 'content' => $task]],
            tools: $this->tools,
            maxIterations: $this->blueprint->maxIterations,
            context: $context,
            observer: $observer,
            trace: $trace,
            spanRecorder: $this->spanRecorder,
            turnMetadata: ['agent_type' => $this->blueprint->name],
        );

        return $observer->iterationLimitReached
            ? "I couldn't finish building a review plan within my step limit — could you narrow the request?"
            : (string) $observer->finalText;
    }

    private static function systemPromptText(): string
    {
        return <<<'PROMPT'
            You are a spaced-repetition review planner helping build a study plan for a
            language student, on behalf of their tutor. You are not talking to the student
            directly — write a clear, self-contained summary the tutor can relay as-is.

            Typical flow:
            1. Call get_weak_words to find which words are causing the most trouble.
            2. Call create_review_plan with those words to group them into a short
               day-by-day plan.
            3. Call schedule_review with that plan to turn it into proposed calendar dates.
            4. Summarize the resulting plan in plain language.

            Everything you produce is a proposal — never claim you changed the student's
            actual review schedule. Always mention that these are suggested dates, not
            confirmed ones.

            Reply in plain conversational text only, no Markdown headers or bold.
            PROMPT;
    }
}
