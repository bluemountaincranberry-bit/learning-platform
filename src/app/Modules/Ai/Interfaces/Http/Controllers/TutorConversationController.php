<?php

namespace App\Modules\Ai\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SendChatMessageRequest;
use App\Modules\Ai\Application\Agent\StudentTutorAgentService;
use App\Contracts\Ai\AiErrorMessage;
use App\Modules\Ai\Domain\Models\AgentConversation;
use App\Modules\Ai\Domain\Models\AgentMessage;
use App\Support\AiConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Student-facing entry point into `StudentTutorAgentService` (task 3.5).
 * Reuses the `agent_conversations`/`agent_messages` schema from EPIC 1
 * (`agent_type = student_tutor`) — no new tables. Mirrors
 * `AiConversationController`'s two-endpoint shape (create conversation, then
 * post messages to it) for a familiar contract, and `ContentAgentChat`'s
 * boundary checks (feature flag, ownership, rate limit) for the agent side.
 *
 * Deliberate divergence from `ContentAgentService`'s architecture: this
 * controller runs `StudentTutorAgentService::handleTurn()` synchronously in
 * the request instead of dispatching `RunAgentTurnJob` to the queue. Every
 * TutorAgent tool today is a single fast, read-only SQL query (no PDF
 * extraction or multi-minute chains like the content-authoring agent), so
 * holding the request open is cheap. Task 3.6 makes this literal:
 * `storeMessage()` now streams the reply over SSE (`text/event-stream`,
 * `ai-engineering-learning-roadmap.md` step 1) rather than returning one
 * JSON blob once the whole turn finishes. `RunAgentTurnJob`/the queued path
 * is unaffected and still used by `ContentAgentService`.
 *
 * Access control lives here, at the boundary, not in any tool (task 3.8,
 * `agent-framework-roadmap.md` step 5.6) — see the `access-tutor-agent` gate
 * applied via route middleware in `Modules\Ai/Routes/api.php`.
 */
class TutorConversationController extends Controller
{
    public function __construct(
        private readonly StudentTutorAgentService $agent,
    ) {}

    public function store(Request $request): JsonResponse
    {
        if (! AiConfig::isAgentEnabled()) {
            return response()->json(['message' => 'AI agent feature is disabled.'], 503);
        }

        $conversation = AgentConversation::query()->create([
            'created_by' => $request->user()->id,
            'status' => AgentConversation::STATUS_ACTIVE,
            'agent_type' => StudentTutorAgentService::AGENT_TYPE,
        ]);

        return response()->json([
            'conversation_id' => $conversation->id,
        ], 201);
    }

    /**
     * Streams the assistant's reply as Server-Sent Events: zero or more
     * `data: {"delta": "..."}` events as text arrives, then one
     * `data: {"done": true, "message_id": ...}` event once the turn is
     * fully persisted. Errors before streaming starts (feature disabled,
     * not this user's conversation, rate limited) are plain JSON — the
     * client never opened an SSE connection for those. An error *during* the
     * stream (tool/LLM failure mid-turn) is reported as a
     * `data: {"error": "..."}` event instead, since the response headers
     * (200, text/event-stream) are already committed by then.
     */
    public function storeMessage(SendChatMessageRequest $request, AgentConversation $conversation): JsonResponse|StreamedResponse
    {
        if (! AiConfig::isAgentEnabled()) {
            return response()->json(['message' => 'AI agent feature is disabled.'], 503);
        }

        $this->assertOwnsTutorConversation($conversation, $request->user()->id);

        if (! $this->consumeRateLimit($request->user()->id)) {
            return response()->json([
                'message' => 'Daily limit reached for the tutor chat. Try again tomorrow.',
            ], 429);
        }

        $userMessage = $conversation->messages()->create([
            'role' => AgentMessage::ROLE_USER,
            'content' => $request->validated('content'),
            // Task 6.1: observational only — see the migration's docblock.
            // Populated only on the one message ChatPage.vue injects
            // query-param context into, never read back into the prompt.
            'context_type' => $request->validated('context_type'),
            'context_ref_id' => $request->validated('context_ref_id'),
            'context_label' => $request->validated('context_label'),
        ]);

        return response()->stream(
            fn () => $this->streamTurn($conversation, $userMessage->id),
            200,
            [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
                // Nginx would otherwise buffer the whole response before
                // sending anything, defeating streaming entirely.
                'X-Accel-Buffering' => 'no',
            ]
        );
    }

    /**
     * Task 6.2: `$onToolEvent` receives exactly one of
     * `['tool_start' => $toolName]`, `['tool_end' => $toolName]`, or
     * `['handoff' => $toolName]` per call, from
     * `StudentTutorAgentService::observerFor()` — that array shape is
     * already the SSE payload, so this closure just forwards it as-is,
     * the same way `$onDelta` forwards `delta` chunks. The frontend
     * (`tutorApi.ts`) turns `$toolName` into a human-readable status label.
     *
     * Task 6.3 — how `GenerateQuizTool`'s JSON survives from tool-execution
     * point to the final `done` frame (documented here since it's the
     * trickiest wiring in this epic): it doesn't. There is no new pipe
     * threaded through `AgentLoop`/the observer/`$onDelta` for it. Instead,
     * `AgentLoopObserver::onToolCallCompleted()` already persists every tool
     * call's raw result onto its own `AgentMessage` row (`tool_result`,
     * EPIC 1) *before* this method's `handleTurnStreaming()` call even
     * returns. So once the turn is over, this method simply re-reads it
     * back: every `tool` message created after `$sinceMessageId` (this
     * turn's own user message) whose `tool_name` is `generate_quiz` has its
     * `tool_result` collected into `toolResults` and attached to `done`.
     * The database is the hand-off mechanism, not a new callback — no
     * `AgentLoop` change needed for this task at all.
     */
    private function streamTurn(AgentConversation $conversation, int $sinceMessageId): void
    {
        try {
            $this->agent->handleTurnStreaming(
                $conversation->id,
                fn (string $delta) => $this->emitSse(['delta' => $delta]),
                fn (array $event) => $this->emitSse($event)
            );
        } catch (Throwable $e) {
            Log::error('TutorConversationController: streamed turn failed', [
                'conversation_id' => $conversation->id,
                'message' => AiErrorMessage::safe($e),
            ]);
            $this->emitSse(['error' => 'Something went wrong while processing your request.']);

            return;
        }

        $reply = $conversation->messages()
            ->where('role', AgentMessage::ROLE_ASSISTANT)
            ->latest('id')
            ->first();

        $toolResults = $conversation->messages()
            ->where('id', '>', $sinceMessageId)
            ->where('role', AgentMessage::ROLE_TOOL)
            ->where('tool_name', 'generate_quiz')
            ->pluck('tool_result')
            ->all();

        $this->emitSse([
            'done' => true,
            'message_id' => $reply?->id,
            ...($toolResults !== [] ? ['toolResults' => $toolResults] : []),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function emitSse(array $payload): void
    {
        echo 'data: '.json_encode($payload)."\n\n";

        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }

    /**
     * Same "boundary, not per-tool" ownership check as `AiConversationController`
     * — a conversation belonging to another user, or one that isn't actually a
     * `student_tutor` conversation, doesn't exist as far as this controller is
     * concerned (404, not 403 — avoids confirming the id exists at all).
     */
    private function assertOwnsTutorConversation(AgentConversation $conversation, int $userId): void
    {
        if ($conversation->created_by !== $userId || $conversation->agent_type !== StudentTutorAgentService::AGENT_TYPE) {
            abort(404);
        }
    }

    /**
     * Same guardrail principle as task 1.8/`ContentAgentChat::send()`: the
     * limit is enforced at the boundary that creates the user message/runs
     * the turn, before any AI call happens — not inside the agent/tools.
     */
    private function consumeRateLimit(int $userId): bool
    {
        $limit = (int) config('ai.agent.turns_per_day', 0);
        if ($limit <= 0) {
            return true;
        }

        $key = 'ai:agent:rate_limit:tutor:'.$userId.':'.now()->format('Y-m-d');
        $count = (int) Cache::get($key, 0);

        if ($count >= $limit) {
            return false;
        }

        Cache::put($key, $count + 1, now()->endOfDay()->addSecond());

        return true;
    }
}
