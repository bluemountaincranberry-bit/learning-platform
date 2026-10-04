<?php

namespace App\Modules\Learning\Interfaces\Http\Controllers;

use App\Contracts\Ai\LessonAssistant;
use App\Contracts\Ai\LessonNotesWriterInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SendLessonMessageRequest;
use App\Modules\Learning\Domain\Models\Lesson;
use App\Modules\Learning\Domain\Models\LessonAnalysisRun;
use App\Support\AiConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Lessons belong to Learning; optional assistant work crosses the AI contract. */
class LessonController extends Controller
{
    public function __construct(
        private readonly LessonAssistant $assistant,
        private readonly LessonNotesWriterInterface $notes,
    ) {}

    public function store(Request $request): JsonResponse
    {
        return DB::transaction(function () use ($request): JsonResponse {
            $lesson = Lesson::query()->create([
                'user_id' => $request->user()->id,
                'status' => Lesson::STATUS_ACTIVE,
            ]);

            return response()->json([
                'lesson_id' => $lesson->id,
                'conversation_id' => $this->assistant->createConversation($lesson->id, $request->user()->id),
            ], 201);
        });
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

        return response()->json([
            'id' => $lesson->id,
            'title' => $lesson->title,
            'tutor' => $lesson->tutor,
            'status' => $lesson->status,
            'conversation_id' => $this->assistant->conversationId($lesson->id),
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

        return response()->json($this->assistant->messages($lesson->id));
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

        if ($this->assistant->conversationId($lesson->id) === null) {
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
            $this->notes->appendNotes($lesson->id, $content);
        }

        $this->assistant->sendMessage($lesson->id, $content, $attachmentPath, $attachmentName);

        return response()->json(['status' => 'queued'], 202);
    }

    public function analyze(Request $request, Lesson $lesson): JsonResponse
    {
        $this->assertOwnsLesson($lesson, $request->user()->id);

        if (! AiConfig::isEnabled()) {
            return response()->json(['message' => 'AI analysis feature is disabled.'], 503);
        }

        if (trim((string) $lesson->source_text) === '') {
            return response()->json(['message' => 'This lesson has no notes yet to analyze.'], 422);
        }

        $run = $lesson->analysisRuns()->create(['status' => LessonAnalysisRun::STATUS_PENDING]);

        $this->assistant->dispatchAnalysis($run->id);

        return response()->json(['run_id' => $run->id, 'status' => $run->status], 202);
    }

    /**
     * @param  \App\Modules\Learning\Domain\Models\LessonLexemeCandidate  $c
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
            'language' => $c->run->language,
        ];
    }

    /**
     * @param  \App\Modules\Learning\Domain\Models\LessonGrammarCandidate  $c
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
