<?php

namespace App\Modules\Interview\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Interview\Application\InterviewDraftService;
use App\Modules\Interview\Application\InterviewPracticeService;
use App\Modules\Interview\Domain\Models\InterviewAiDraft;
use App\Modules\Interview\Domain\Models\InterviewAnswerRevision;
use App\Modules\Interview\Domain\Models\InterviewAnswerVariant;
use App\Modules\Interview\Domain\Models\InterviewPracticeSession;
use App\Modules\Interview\Domain\Models\InterviewProfile;
use App\Modules\Interview\Domain\Models\InterviewQuestion;
use App\Modules\Interview\Domain\Models\InterviewTag;
use App\Modules\Interview\Domain\Models\InterviewTopic;
use App\Modules\Interview\Interfaces\Http\Requests\InterviewDraftRequest;
use App\Modules\Interview\Interfaces\Http\Requests\InterviewMessageRequest;
use App\Modules\Interview\Interfaces\Http\Requests\InterviewProfileRequest;
use App\Modules\Interview\Interfaces\Http\Requests\InterviewQuestionRequest;
use App\Modules\Interview\Interfaces\Http\Requests\InterviewSessionRequest;
use App\Modules\Interview\Interfaces\Http\Requests\InterviewTopicRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InterviewController extends Controller
{
    public function __construct(private readonly InterviewPracticeService $practice, private readonly InterviewDraftService $drafts) {}

    public function drafts(Request $request): JsonResponse
    {
        return response()->json(['data' => InterviewAiDraft::query()->where('user_id', $request->user()->id)
            ->where('status', 'pending')->latest()->get()]);
    }

    public function storeDraft(InterviewDraftRequest $request): JsonResponse
    {
        $draft = $this->drafts->createQuestionDraft($request->user()->id, $request->validated('payload'));

        return response()->json(['data' => $draft->fresh()], 201);
    }

    public function confirmDraft(Request $request, int $draft): JsonResponse
    {
        $confirmed = $this->drafts->confirm($draft, $request->user()->id);
        $result = $confirmed instanceof InterviewQuestion
            ? $this->questionPayload($confirmed->fresh(['topic', 'tags', 'answers']))
            : $confirmed->load('milestones');

        return response()->json(['data' => ['status' => 'confirmed', 'result' => $result]]);
    }

    public function rejectDraft(Request $request, int $draft): JsonResponse
    {
        $this->drafts->reject($draft, $request->user()->id);

        return response()->json(['data' => ['status' => 'rejected']]);
    }

    public function sessions(Request $request): JsonResponse
    {
        $sessions = InterviewPracticeSession::query()->where('user_id', $request->user()->id)
            ->latest('updated_at')->paginate(20);

        return response()->json($sessions);
    }

    public function storeSession(InterviewSessionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $session = $this->practice->start($request->user()->id, $data);

        return response()->json(['data' => $this->sessionPayload($session)], 201);
    }

    public function showSession(Request $request, int $session): JsonResponse
    {
        return response()->json(['data' => $this->sessionPayload($this->practice->ownedSession($session, $request->user()->id))]);
    }

    public function completeSession(Request $request, int $session): JsonResponse
    {
        $record = $this->practice->complete($this->practice->ownedSession($session, $request->user()->id));

        return response()->json(['data' => $this->sessionPayload($record->fresh())]);
    }

    public function sendSessionMessage(InterviewMessageRequest $request, int $session): JsonResponse
    {
        $record = $this->practice->ownedSession($session, $request->user()->id);
        abort_unless(config('ai.agent.enabled', false), 503, 'AI practice is currently unavailable. Your interview bank is still available.');
        $messageId = $this->practice->sendMessage($record, $request->user()->id, $request->validated('content'));
        abort_if($messageId === false, 429, 'Daily Interview Agent limit reached. Try again tomorrow.');

        return response()->json(['data' => ['id' => $messageId, 'status' => 'queued']], 202);
    }

    public function topics(Request $request): JsonResponse
    {
        return response()->json(['data' => InterviewTopic::query()->where('user_id', $request->user()->id)->orderBy('sort_order')->orderBy('name')->get()]);
    }

    public function tags(Request $request): JsonResponse
    {
        return response()->json(['data' => InterviewTag::query()->where('user_id', $request->user()->id)->orderBy('name')->pluck('name')]);
    }

    public function storeTopic(InterviewTopicRequest $request): JsonResponse
    {
        $data = $request->validated();
        if (! empty($data['parent_id'])) {
            $this->ownedTopic($data['parent_id'], $request->user()->id);
        }
        $topic = InterviewTopic::query()->create([...$data, 'user_id' => $request->user()->id]);

        return response()->json(['data' => $topic], 201);
    }

    public function updateTopic(InterviewTopicRequest $request, int $topic): JsonResponse
    {
        $record = $this->ownedTopic($topic, $request->user()->id);
        $data = $request->validated();
        if (isset($data['parent_id'])) {
            $this->ownedTopic($data['parent_id'], $request->user()->id);
            abort_if($this->isTopicDescendant($record->id, (int) $data['parent_id'], $request->user()->id), 422);
        }
        $record->update($data);

        return response()->json(['data' => $record->fresh()]);
    }

    public function deleteTopic(Request $request, int $topic): JsonResponse
    {
        $this->ownedTopic($topic, $request->user()->id)->delete();

        return response()->json([], 204);
    }

    public function questions(Request $request): JsonResponse
    {
        $query = InterviewQuestion::query()->with(['topic:id,name,parent_id', 'tags:id,name', 'answers:id,question_id,kind,text_en,text_ru'])
            ->where('user_id', $request->user()->id);
        if ($search = trim((string) $request->query('search', ''))) {
            $like = '%'.mb_strtolower($search).'%';
            $query->where(function ($q) use ($like, $search): void {
                if (DB::connection()->getDriverName() === 'pgsql') {
                    $q->whereRaw('prompt_en ILIKE ?', ['%'.$search.'%'])->orWhereRaw('COALESCE(prompt_ru, \'\') ILIKE ?', ['%'.$search.'%']);
                } else {
                    // SQLite's LOWER() is ASCII-only; retain exact Unicode matching in feature tests.
                    $q->whereRaw('LOWER(prompt_en) LIKE ?', [$like])->orWhere('prompt_en', 'like', '%'.$search.'%')
                        ->orWhere('prompt_ru', 'like', '%'.$search.'%');
                }
            });
        }
        if ($request->filled('state')) {
            $query->where('preparation_state', $request->query('state'));
        }
        if ($request->filled('topic_id')) {
            $topic = $this->ownedTopic((int) $request->query('topic_id'), $request->user()->id);
            $query->whereIn('topic_id', $this->topicSubtreeIds($topic->id, $request->user()->id));
        }
        if ($request->filled('tag')) {
            $query->whereHas('tags', fn ($q) => $q->where('name', $request->query('tag'))->where('interview_tags.user_id', $request->user()->id));
        }
        $questions = $query->orderBy('id')->paginate(min(100, max(1, (int) $request->query('per_page', 30))));
        $questions->getCollection()->transform(fn (InterviewQuestion $q) => $this->questionPayload($q));

        return response()->json($questions);
    }

    public function showQuestion(Request $request, int $question): JsonResponse
    {
        return response()->json(['data' => $this->questionPayload($this->ownedQuestion($question, $request->user()->id))]);
    }

    public function storeQuestion(InterviewQuestionRequest $request): JsonResponse
    {
        $data = $request->validated();
        if (! empty($data['topic_id'])) {
            $this->ownedTopic($data['topic_id'], $request->user()->id);
        }
        $question = DB::transaction(function () use ($data, $request): InterviewQuestion {
            $question = InterviewQuestion::query()->create([
                'user_id' => $request->user()->id, 'topic_id' => $data['topic_id'] ?? null,
                'prompt_en' => $data['prompt_en'], 'prompt_ru' => $data['prompt_ru'] ?? null,
                'preparation_state' => $data['preparation_state'] ?? 'unpracticed',
            ]);
            $this->syncTags($question, $data['tags'] ?? []);
            $this->saveAnswers($question, $data['answers'] ?? ['short' => [], 'full' => []], false, $request->user()->id);

            return $question;
        });

        return response()->json(['data' => $this->questionPayload($question->fresh(['topic', 'tags', 'answers']))], 201);
    }

    public function updateQuestion(InterviewQuestionRequest $request, int $question): JsonResponse
    {
        $record = $this->ownedQuestion($question, $request->user()->id);
        $data = $request->validated();
        if (! empty($data['topic_id'])) {
            $this->ownedTopic($data['topic_id'], $request->user()->id);
        }
        DB::transaction(function () use ($record, $data, $request): void {
            $record->update(collect($data)->except(['tags', 'answers'])->all());
            if (array_key_exists('tags', $data)) {
                $this->syncTags($record, $data['tags']);
            }
            if (isset($data['answers'])) {
                $this->saveAnswers($record, $data['answers'], true, $request->user()->id);
            }
        });

        return response()->json(['data' => $this->questionPayload($record->fresh(['topic', 'tags', 'answers']))]);
    }

    public function deleteQuestion(Request $request, int $question): JsonResponse
    {
        $this->ownedQuestion($question, $request->user()->id)->delete();

        return response()->json([], 204);
    }

    public function restoreAnswerRevision(Request $request, int $question, int $answer, int $revision): JsonResponse
    {
        $record = $this->ownedQuestion($question, $request->user()->id);
        $variant = $record->answers()->findOrFail($answer);
        $snapshot = $variant->revisions()->findOrFail($revision);
        InterviewAnswerRevision::query()->create([
            'answer_variant_id' => $variant->id, 'created_by' => $request->user()->id,
            'text_en' => $variant->text_en, 'text_ru' => $variant->text_ru,
        ]);
        $variant->update(['text_en' => $snapshot->text_en, 'text_ru' => $snapshot->text_ru]);

        return response()->json(['data' => $this->questionPayload($record->fresh(['topic', 'tags', 'answers']))]);
    }

    public function profile(Request $request): JsonResponse
    {
        $profile = InterviewProfile::query()->with('milestones')->firstOrCreate(['user_id' => $request->user()->id]);

        return response()->json(['data' => $profile]);
    }

    public function saveProfile(InterviewProfileRequest $request): JsonResponse
    {
        $data = $request->validated();
        $profile = DB::transaction(function () use ($data, $request): InterviewProfile {
            $profile = InterviewProfile::query()->firstOrNew(['user_id' => $request->user()->id]);
            $profile->fill(collect($data)->except('milestones')->all());
            $profile->save();
            foreach ($data['milestones'] ?? [] as $milestone) {
                if (isset($milestone['id'])) {
                    $profile->milestones()->whereKey($milestone['id'])->update(collect($milestone)->except('id')->all());
                } else {
                    $profile->milestones()->create(collect($milestone)->except('id')->all());
                }
            }

            return $profile;
        });

        return response()->json(['data' => $profile->load('milestones')]);
    }

    private function syncTags(InterviewQuestion $question, array $tags): void
    {
        $ids = collect($tags)->map(fn (string $name) => InterviewTag::query()->firstOrCreate(
            ['user_id' => $question->user_id, 'name' => trim($name)]
        )->id)->all();
        $question->tags()->sync($ids);
    }

    private function saveAnswers(InterviewQuestion $question, array $answers, bool $recordRevision, int $userId): void
    {
        foreach (['short', 'full'] as $kind) {
            if (! array_key_exists($kind, $answers)) {
                continue;
            }
            $variant = $question->answers()->firstOrNew(['kind' => $kind]);
            $next = ['text_en' => $answers[$kind]['en'] ?? null, 'text_ru' => $answers[$kind]['ru'] ?? null];
            if ($variant->exists && $recordRevision && ($variant->text_en !== $next['text_en'] || $variant->text_ru !== $next['text_ru'])) {
                InterviewAnswerRevision::query()->create([
                    'answer_variant_id' => $variant->id, 'created_by' => $userId,
                    'text_en' => $variant->text_en, 'text_ru' => $variant->text_ru,
                ]);
            }
            $variant->fill($next)->save();
        }
    }

    private function questionPayload(InterviewQuestion $question): array
    {
        return [
            'id' => $question->id, 'prompt_en' => $question->prompt_en, 'prompt_ru' => $question->prompt_ru,
            'preparation_state' => $question->preparation_state, 'topic' => $question->topic,
            'tags' => $question->tags->pluck('name')->values(),
            'answers' => $question->answers->keyBy('kind')->map(fn (InterviewAnswerVariant $a) => [
                'id' => $a->id, 'en' => $a->text_en, 'ru' => $a->text_ru,
                'revisions' => $a->revisions()->get(['id', 'text_en', 'text_ru', 'created_at']),
            ]),
        ];
    }

    private function ownedTopic(int $id, int $userId): InterviewTopic
    {
        return InterviewTopic::query()->where('user_id', $userId)->findOrFail($id);
    }

    private function ownedQuestion(int $id, int $userId): InterviewQuestion
    {
        return InterviewQuestion::query()->with(['topic', 'tags', 'answers'])->where('user_id', $userId)->findOrFail($id);
    }

    private function isTopicDescendant(int $topicId, int $candidateId, int $userId): bool
    {
        return $this->topicSubtreeIds($topicId, $userId)->contains($candidateId);
    }

    private function topicSubtreeIds(int $topicId, int $userId): \Illuminate\Support\Collection
    {
        $ids = collect([$topicId]);
        $frontier = $ids;
        while ($frontier->isNotEmpty()) {
            $frontier = InterviewTopic::query()->where('user_id', $userId)->whereIn('parent_id', $frontier)->pluck('id');
            $ids = $ids->concat($frontier);
        }

        return $ids;
    }

    private function sessionPayload(InterviewPracticeSession $session): array
    {
        $profile = InterviewProfile::query()->where('user_id', $session->user_id)->with('milestones')->first();
        $questions = $this->practice->questionsForSession($session)
            ->map(fn (InterviewQuestion $question) => $this->questionPayload($question))->values();
        $messages = $this->practice->history($session);

        return [
            'id' => $session->id,
            'conversation_id' => $session->agent_conversation_id,
            'mode' => $session->mode,
            'status' => $session->status,
            'question_count' => $session->question_count,
            'focus' => $session->focus,
            'questions' => $questions,
            'profile' => $profile,
            'messages' => $messages,
            'created_at' => $session->created_at,
            'updated_at' => $session->updated_at,
        ];
    }
}
