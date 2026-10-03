<?php

namespace App\Modules\Ai\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SendLessonMessageRequest;
use App\Modules\Ai\Interfaces\Jobs\RunAgentTurnJob;
use App\Modules\Ai\Interfaces\Jobs\RunLessonAnalysisJob;
use App\Modules\Ai\Domain\Models\AgentConversation;
use App\Modules\Ai\Domain\Models\AgentMessage;
use App\Modules\Ai\Domain\Models\Lesson;
use App\Modules\Ai\Domain\Models\LessonAnalysisRun;
use App\Modules\Ai\Application\Agent\LessonAgentService;
use App\Support\AiConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * "Мои занятия" — student-facing entry point, mirrors TutorConversationController's
 * boundary checks (feature flag, ownership, rate limit) and ContentAgentChat's
 * queued-turn shape (RunAgentTurnJob, not synchronous SSE — this agent can
 * call extract_pdf_text, which is not the "one fast SQL query" case
 * TutorConversationController's docblock justifies running inline).
 *
 * A Lesson and its AgentConversation are created together (store()) and
 * stay 1:1 for the lesson's lifetime — see Lesson::conversation().
 */
class LessonController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        if (! AiConfig::isAgentEnabled()) {
            return response()->json(['message' => 'AI agent feature is disabled.'], 503);
        }

        $lesson = Lesson::query()->create([
            'user_id' => $request->user()->id,
            'status' => Lesson::STATUS_ACTIVE,
        ]);

        $conversation = AgentConversation::query()->create([
            'created_by' => $request->user()->id,
            'lesson_id' => $lesson->id,
            'status' => AgentConversation::STATUS_ACTIVE,
            'agent_type' => LessonAgentService::AGENT_TYPE,
        ]);

        return response()->json([
            'lesson_id' => $lesson->id,
            'conversation_id' => $conversation->id,
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $lessons = Lesson::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('updated_at')
            ->paginate(15);

        $lessons->getCollection()->transform(function (Lesson $lesson): array {
            // Approximate, latest-run-only counts for the feed (cheap: no
            // cross-run dedup query per row) — show() does the thorough
            // distinct count across every run for the lesson's own page.
            $run = $lesson->latestAnalysisRun;

            return [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'tutor' => $lesson->tutor,
                'status' => $lesson->status,
                'updated_at' => $lesson->updated_at,
                'lexeme_count' => $run?->lexemeCandidates()->count() ?? 0,
                'grammar_count' => $run?->grammarCandidates()->count() ?? 0,
            ];
        });

        return response()->json($lessons);
    }

    public function show(Request $request, Lesson $lesson): JsonResponse
    {
        $this->assertOwnsLesson($lesson, $request->user()->id);

        $conversation = $lesson->conversation;

        return response()->json([
            'id' => $lesson->id,
            'title' => $lesson->title,
            'tutor' => $lesson->tutor,
            'status' => $lesson->status,
            'conversation_id' => $conversation?->id,
            'analysis_status' => $lesson->latestAnalysisRun?->status,
            'lexemes' => $lesson->distinctLexemeCandidates()->map(fn ($c) => $this->lexemePayload($c))->values(),
            'grammar' => $lesson->distinctGrammarCandidates()->map(fn ($c) => $this->grammarPayload($c))->values(),
        ]);
    }

    /**
     * Polled by the SPA while a turn is in flight (mirrors
     * ContentAgentChat::getMessagesProperty()/getIsWaitingProperty(), the
     * same "queued job, poll for the reply" shape — this agent's turns
     * aren't streamed).
     */
    public function messages(Request $request, Lesson $lesson): JsonResponse
    {
        $this->assertOwnsLesson($lesson, $request->user()->id);

        $conversation = $lesson->conversation;
        $messages = $conversation?->messages()
            ->where('role', '!=', AgentMessage::ROLE_TOOL)
            ->orderBy('created_at')
            ->get(['id', 'role', 'content', 'attachment_name', 'created_at']) ?? collect();

        $isWaiting = $messages->isNotEmpty() && $messages->last()->role === AgentMessage::ROLE_USER;

        return response()->json([
            'messages' => $messages,
            'is_waiting' => $isWaiting,
        ]);
    }

    public function storeMessage(SendLessonMessageRequest $request, Lesson $lesson): JsonResponse
    {
        if (! AiConfig::isAgentEnabled()) {
            return response()->json(['message' => 'AI agent feature is disabled.'], 503);
        }

        $this->assertOwnsLesson($lesson, $request->user()->id);

        $content = trim((string) $request->validated('content'));
        $attachment = $request->file('attachment');

        if ($content === '' && $attachment === null) {
            return response()->json(['message' => 'Message content or an attachment is required.'], 422);
        }

        if (! $this->consumeRateLimit($request->user()->id)) {
            return response()->json(['message' => 'Daily limit reached. Try again tomorrow.'], 429);
        }

        $conversation = $lesson->conversation;
        if ($conversation === null) {
            return response()->json(['message' => 'This lesson has no conversation.'], 404);
        }

        $attachmentPath = null;
        $attachmentName = null;
        if ($attachment !== null) {
            $attachmentPath = $attachment->store('agent-uploads', 'local');
            $attachmentName = $attachment->getClientOriginalName();
        }

        // Deterministic, not dependent on the AI turn succeeding — a typed
        // message is part of the lesson's notes the moment it's sent, same
        // as LessonAgentService's observer folds in extracted PDF text once
        // that tool call completes (see that class's docblock).
        if ($content !== '') {
            $lesson->update(['source_text' => trim(((string) $lesson->source_text)."\n\n".$content)]);
        }

        $conversation->messages()->create([
            'role' => AgentMessage::ROLE_USER,
            'content' => $content !== '' ? $content : null,
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachmentName,
        ]);

        RunAgentTurnJob::dispatch($conversation->id);

        return response()->json(['status' => 'queued'], 202);
    }

    public function analyze(Request $request, Lesson $lesson): JsonResponse
    {
        $this->assertOwnsLesson($lesson, $request->user()->id);

        if (trim((string) $lesson->source_text) === '') {
            return response()->json(['message' => 'This lesson has no notes yet to analyze.'], 422);
        }

        $run = $lesson->analysisRuns()->create(['status' => LessonAnalysisRun::STATUS_PENDING]);

        RunLessonAnalysisJob::dispatch($run->id);

        return response()->json(['run_id' => $run->id, 'status' => $run->status], 202);
    }

    /**
     * @param  \App\Modules\Ai\Domain\Models\LessonLexemeCandidate  $c
     * @return array<string, mixed>
     */
    private function lexemePayload($c): array
    {
        return [
            'id' => $c->id,
            'text' => $c->text,
            'type' => $c->type,
            'level' => $c->level,
            'translation' => $c->translation,
            'example' => $c->example,
            'example_translation' => $c->example_translation,
            'status' => $c->status,
            'matched_lexeme_id' => $c->matched_lexeme_id,
        ];
    }

    /**
     * @param  \App\Modules\Ai\Domain\Models\LessonGrammarCandidate  $c
     * @return array<string, mixed>
     */
    private function grammarPayload($c): array
    {
        return [
            'id' => $c->id,
            'title' => $c->title,
            'summary' => $c->summary,
            'example' => $c->example,
            'example_translation' => $c->example_translation,
            'status' => $c->status,
            'matched_grammar_rule_id' => $c->matched_grammar_rule_id,
        ];
    }

    private function assertOwnsLesson(Lesson $lesson, int $userId): void
    {
        if ($lesson->user_id !== $userId) {
            abort(404);
        }
    }

    private function consumeRateLimit(int $userId): bool
    {
        $limit = (int) config('ai.agent.turns_per_day', 0);
        if ($limit <= 0) {
            return true;
        }

        $key = 'ai:agent:rate_limit:lesson:'.$userId.':'.now()->format('Y-m-d');
        $count = (int) Cache::get($key, 0);

        if ($count >= $limit) {
            return false;
        }

        Cache::put($key, $count + 1, now()->endOfDay()->addSecond());

        return true;
    }
}
