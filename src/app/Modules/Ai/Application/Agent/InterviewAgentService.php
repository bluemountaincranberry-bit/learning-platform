<?php

namespace App\Modules\Ai\Application\Agent;

use App\Modules\Ai\Application\Agent\Contracts\AgentLoopObserver;
use App\Modules\Ai\Application\Agent\Contracts\AgentService;
use App\Modules\Ai\Application\Agent\Contracts\AgentTool;
use App\Modules\Ai\Application\Agent\Data\AgentBlueprint;
use App\Modules\Ai\Application\Agent\Data\AgentChatResponse;
use App\Modules\Ai\Application\Agent\Data\AgentToolCall;
use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;
use App\Modules\Ai\Application\Agent\Tools\Interview\GetInterviewPracticeContextTool;
use App\Modules\Ai\Application\Agent\Tracing\SpanRecorder;
use App\Modules\Ai\Application\Agent\Tracing\TraceContext;
use App\Modules\Ai\Domain\Models\AgentConversation;
use App\Modules\Ai\Domain\Models\AgentMessage;
use Illuminate\Support\Facades\Log;

final class InterviewAgentService implements AgentService
{
    public const AGENT_TYPE = 'interview';

    /** @param array<int, AgentTool> $tools */
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
            systemPrompt: <<<'PROMPT'
                You are the learner's Interview Agent. Before answering every turn, call get_interview_practice_context.
                The returned profile and question bank are confirmed learner data. Never invent personal history, skills,
                projects, or outcomes. Ask concise clarifying questions whenever a factual detail is missing.
                The learner is practising English: ask interview questions in English and explain coaching feedback in Russian.
                In coached mode, handle one question at a time, discuss the response, and help improve it before moving on.
                In mock mode, conduct the selected sequence in English and postpone all feedback until the learner says
                the interview is complete or the selected question count is reached. Never claim to save a proposed edit;
                summarize suggested changes and ask for confirmation in chat. Keep feedback specific to evidence in the
                learner's answer and avoid an overall readiness score.
                PROMPT,
            tools: [GetInterviewPracticeContextTool::class],
            maxIterations: 4,
            allowedSideEffects: [AgentToolDefinition::SIDE_EFFECT_READ_ONLY],
        );
    }

    public function handleTurn(int $conversationId): void
    {
        $conversation = AgentConversation::query()->findOrFail($conversationId);
        if ($conversation->agent_type !== self::AGENT_TYPE) {
            abort(404);
        }
        $trace = TraceContext::newTrace();
        $this->loop->run(
            systemPrompt: $this->blueprint->systemPrompt,
            startingMessages: $conversation->messages()->whereIn('role', [AgentMessage::ROLE_USER, AgentMessage::ROLE_ASSISTANT])
                ->orderBy('created_at')->get()->map(fn (AgentMessage $message) => ['role' => $message->role, 'content' => (string) $message->content])->all(),
            tools: $this->tools,
            maxIterations: $this->blueprint->maxIterations,
            context: new AgentToolContext($conversation->id, $conversation->created_by, trace: $trace),
            observer: new class($conversation) implements AgentLoopObserver
            {
                public function __construct(private readonly AgentConversation $conversation) {}

                public function onToolCallStarted(AgentToolCall $call): void {}

                public function onToolCallCompleted(AgentToolCall $call, array $result, int $latencyMs): void
                {
                    $this->conversation->messages()->create([
                        'role' => AgentMessage::ROLE_TOOL,
                        'tool_call_id' => $call->id,
                        'tool_name' => $call->name,
                        'tool_args' => $call->arguments,
                        'tool_result' => $result,
                        'latency_ms' => $latencyMs,
                    ]);
                }

                public function onFinalResponse(AgentChatResponse $response): void
                {
                    $this->conversation->messages()->create(['role' => AgentMessage::ROLE_ASSISTANT, 'content' => (string) $response->content]);
                }

                public function onIterationLimitReached(): void
                {
                    Log::warning('InterviewAgentService: iteration limit reached', ['conversation_id' => $this->conversation->id]);
                    $this->conversation->messages()->create(['role' => AgentMessage::ROLE_ASSISTANT, 'content' => 'I could not finish that response. Please try a shorter prompt.']);
                }
            },
            trace: $trace,
            spanRecorder: $this->spanRecorder,
            turnMetadata: ['agent_type' => self::AGENT_TYPE],
        );
    }
}
