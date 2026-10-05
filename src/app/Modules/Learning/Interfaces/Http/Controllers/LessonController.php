<?php

namespace App\Modules\Learning\Interfaces\Http\Controllers;

use App\Contracts\Ai\LessonAssistant;
use App\Contracts\Ai\LessonNotesWriterInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SendLessonMessageRequest;
use App\Modules\Learning\Domain\Models\Lesson;
use App\Modules\Learning\Domain\Models\LessonAnalysisRun;
use App\Modules\Learning\Interfaces\Http\Requests\LessonFieldsRequest;
use App\Modules\Learning\Interfaces\Http\Requests\LessonIndexRequest;
use App\Support\AiConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Lessons belong to Learning; optional assistant work crosses the AI contract. */
class LessonController extends Controller
{
    public function __construct(
        private readonly LessonAssistant $assistant,
        private readonly LessonNotesWriterInterface $notes,
    ) {}

    public function store(LessonFieldsRequest $request): JsonResponse
    {
        $data = $request->validated();

        return DB::transaction(function () use ($request, $data): JsonResponse {
            $lesson = Lesson::query()->create([
                'user_id' => $request->user()->id,
                'status' => Lesson::STATUS_ACTIVE,
                'title' => $data['title'] ?? null,
                'lesson_date' => $data['lesson_date'] ?? null,
                'teacher' => $data['teacher'] ?? null,
                'topic' => $data['topic'] ?? null,
                'language' => $data['language'] ?? 'en',
                'tags' => $data['tags'] ?? [],
                'notes' => $data['notes'] ?? null,
                'homework' => $data['homework'] ?? null,
            ]);

            return response()->json([
                'lesson_id' => $lesson->id,
                'conversation_id' => $this->assistant->createConversation($lesson->id, $request->user()->id),
            ], 201);
        });
    }

    public function index(LessonIndexRequest $request): JsonResponse
    {
        $status = $request->query('status', 'active'); // active by default; all | active | archived
        $query = Lesson::query()->where('user_id', $request->user()->id);

        if ($status === 'active') {
            $query->where('status', Lesson::STATUS_ACTIVE);
        } elseif ($status === 'archived') {
            $query->where('status', Lesson::STATUS_ARCHIVED);
        }
        // 'all' or null = no filter

        $lessons = $query->orderByDesc('updated_at')->paginate(15);

        $lessons->getCollection()->transform(function (Lesson $lesson): array {
            $run = $lesson->latestAnalysisRun;

            return [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'lesson_date' => $lesson->lesson_date,
                'teacher' => $lesson->teacher,
                'topic' => $lesson->topic,
                'status' => $lesson->status,
                'updated_at' => $lesson->updated_at,
                'lexeme_count' => $run?->lexemeCandidates()->count() ?? 0,
                'grammar_count' => $run?->grammarCandidates()->count() ?? 0,
            ];
        });

        return response()->json($lessons);
    }

    public function show(Lesson $lesson): JsonResponse
    {
        $this->authorize('view', $lesson);

        return response()->json([
            'id' => $lesson->id,
            'title' => $lesson->title,
            'lesson_date' => $lesson->lesson_date,
            'teacher' => $lesson->teacher,
            'topic' => $lesson->topic,
            'language' => $lesson->language,
            'tags' => $lesson->tags ?? [],
            'notes' => $lesson->notes,
            'homework' => $lesson->homework,
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
    public function messages(Lesson $lesson): JsonResponse
    {
        $this->authorize('view', $lesson);

        return response()->json($this->assistant->messages($lesson->id));
    }

    public function storeMessage(SendLessonMessageRequest $request, Lesson $lesson): JsonResponse
    {
        if (! AiConfig::isAgentEnabled()) {
            return response()->json(['message' => 'AI agent feature is disabled.'], 503);
        }

        $this->authorize('view', $lesson);

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

    public function analyze(Lesson $lesson): JsonResponse
    {
        $this->authorize('view', $lesson);

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
            'language' => $c->run->lesson->language,
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

    public function update(LessonFieldsRequest $request, Lesson $lesson): JsonResponse
    {
        $this->authorize('update', $lesson);
        $data = $request->validated();
        if (array_key_exists('language', $data) && $data['language'] === null) {
            $data['language'] = 'en';
        }

        $lesson->update($data);

        return response()->json(['status' => 'updated']);
    }

    public function destroy(Lesson $lesson): JsonResponse
    {
        $this->authorize('delete', $lesson);

        $lesson->update(['status' => Lesson::STATUS_ARCHIVED]);

        return response()->json(['status' => 'archived']);
    }

    public function restore(Lesson $lesson): JsonResponse
    {
        $this->authorize('restore', $lesson);

        $lesson->update(['status' => Lesson::STATUS_ACTIVE]);

        return response()->json(['status' => 'restored']);
    }
}
