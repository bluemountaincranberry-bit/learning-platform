<?php

namespace App\Modules\Interview\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Interview\Application\InterviewDraftService;
use App\Modules\Interview\Application\InterviewPracticeService;
use App\Modules\Interview\Application\InterviewProfileService;
use App\Modules\Interview\Application\InterviewQuestionBankService;
use App\Modules\Interview\Domain\Models\InterviewProfile;
use App\Modules\Interview\Domain\Models\InterviewQuestion;
use App\Modules\Interview\Interfaces\Http\Requests\InterviewDraftRequest;
use App\Modules\Interview\Interfaces\Http\Requests\InterviewMessageRequest;
use App\Modules\Interview\Interfaces\Http\Requests\InterviewProfileRequest;
use App\Modules\Interview\Interfaces\Http\Requests\InterviewQuestionRequest;
use App\Modules\Interview\Interfaces\Http\Requests\InterviewSessionRequest;
use App\Modules\Interview\Interfaces\Http\Requests\InterviewTopicRequest;
use App\Modules\Interview\Interfaces\Http\Resources\InterviewDraftResource;
use App\Modules\Interview\Interfaces\Http\Resources\InterviewPracticeSessionResource;
use App\Modules\Interview\Interfaces\Http\Resources\InterviewProfileResource;
use App\Modules\Interview\Interfaces\Http\Resources\InterviewQuestionResource;
use App\Modules\Interview\Interfaces\Http\Resources\InterviewTopicResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

final class InterviewController extends Controller
{
    public function __construct(
        private readonly InterviewQuestionBankService $questionBank,
        private readonly InterviewPracticeService $practice,
        private readonly InterviewDraftService $drafts,
        private readonly InterviewProfileService $profiles,
    ) {}

    public function drafts(Request $request): JsonResponse
    {
        return InterviewDraftResource::collection($this->drafts->pendingDrafts($request->user()->id))->response();
    }

    public function storeDraft(InterviewDraftRequest $request): JsonResponse
    {
        return InterviewDraftResource::make($this->drafts->createQuestionDraft($request->user()->id, $request->validated('payload'))->fresh())
            ->response()->setStatusCode(201);
    }

    public function confirmDraft(Request $request, int $draft): JsonResponse
    {
        $confirmed = $this->drafts->confirm($draft, $request->user()->id);
        $result = $confirmed instanceof InterviewQuestion
            ? InterviewQuestionResource::make($confirmed->fresh(['topic', 'tags', 'answers.revisions']))
            : ($confirmed instanceof InterviewProfile ? InterviewProfileResource::make($confirmed->load(['milestones', 'observations'])) : $confirmed);

        return response()->json(['data' => ['status' => 'confirmed', 'result' => $result]]);
    }

    public function rejectDraft(Request $request, int $draft): JsonResponse
    {
        $this->drafts->reject($draft, $request->user()->id);

        return response()->json(['data' => ['status' => 'rejected']]);
    }

    public function sessions(Request $request): JsonResponse
    {
        return InterviewPracticeSessionResource::collection($this->practice->sessions($request->user()->id))->response();
    }

    public function storeSession(InterviewSessionRequest $request): JsonResponse
    {
        $session = $this->practice->start($request->user()->id, $request->validated());

        return InterviewPracticeSessionResource::make($session)->response()->setStatusCode(201);
    }

    public function showSession(Request $request, int $session): JsonResponse
    {
        return InterviewPracticeSessionResource::make($this->practice->ownedSession($session, $request->user()->id))->response();
    }

    public function completeSession(Request $request, int $session): JsonResponse
    {
        $record = $this->practice->complete($this->practice->ownedSession($session, $request->user()->id));

        return InterviewPracticeSessionResource::make($record)->response();
    }

    public function sendSessionMessage(InterviewMessageRequest $request, int $session): JsonResponse
    {
        $record = $this->practice->ownedSession($session, $request->user()->id);
        abort_unless(config('ai.agent.enabled', false), 503, 'AI practice is currently unavailable. Your interview bank is still available.');
        $voice = null;
        if ($audio = $request->file('voice_audio')) {
            $keepForever = $request->boolean('voice_audio_keep_forever');
            $voice = [
                'voice_audio_disk' => 'local',
                'voice_audio_path' => $audio->store('agent-voice', 'local'),
                'voice_audio_pinned' => $keepForever,
                'voice_audio_expires_at' => $keepForever ? null : now()->addDays(30),
                'transcription_provider' => $request->validated('transcription_provider'),
                'transcription_language' => $request->validated('transcription_language'),
            ];
        }
        $messageId = $this->practice->sendMessage($record, $request->user()->id, $request->validated('content'), $voice);
        if ($messageId === false && isset($voice['voice_audio_path'])) {
            Storage::disk('local')->delete($voice['voice_audio_path']);
        }
        abort_if($messageId === false, 429, 'Daily Interview Agent limit reached. Try again tomorrow.');

        return response()->json(['data' => ['id' => $messageId, 'status' => 'queued']], 202);
    }

    public function topics(Request $request): JsonResponse
    {
        return InterviewTopicResource::collection($this->questionBank->topics($request->user()->id))->response();
    }

    public function tags(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->questionBank->tags($request->user()->id)]);
    }

    public function storeTopic(InterviewTopicRequest $request): JsonResponse
    {
        return InterviewTopicResource::make($this->questionBank->createTopic($request->user()->id, $request->validated()))
            ->response()->setStatusCode(201);
    }

    public function updateTopic(InterviewTopicRequest $request, int $topic): JsonResponse
    {
        return InterviewTopicResource::make($this->questionBank->updateTopic($request->user()->id, $topic, $request->validated()))->response();
    }

    public function deleteTopic(Request $request, int $topic): JsonResponse
    {
        $this->questionBank->deleteTopic($request->user()->id, $topic);

        return response()->json([], 204);
    }

    public function questions(Request $request): JsonResponse
    {
        return InterviewQuestionResource::collection($this->questionBank->questions($request->user()->id, $request->only([
            'search', 'state', 'topic_id', 'tag', 'per_page',
        ])))->response();
    }

    public function showQuestion(Request $request, int $question): JsonResponse
    {
        return InterviewQuestionResource::make($this->questionBank->question($request->user()->id, $question))->response();
    }

    public function storeQuestion(InterviewQuestionRequest $request): JsonResponse
    {
        return InterviewQuestionResource::make($this->questionBank->createQuestion($request->user()->id, $request->validated()))
            ->response()->setStatusCode(201);
    }

    public function updateQuestion(InterviewQuestionRequest $request, int $question): JsonResponse
    {
        return InterviewQuestionResource::make($this->questionBank->updateQuestion($request->user()->id, $question, $request->validated()))->response();
    }

    public function deleteQuestion(Request $request, int $question): JsonResponse
    {
        $this->questionBank->deleteQuestion($request->user()->id, $question);

        return response()->json([], 204);
    }

    public function restoreAnswerRevision(Request $request, int $question, int $answer, int $revision): JsonResponse
    {
        return InterviewQuestionResource::make($this->questionBank->restoreAnswerRevision($request->user()->id, $question, $answer, $revision))->response();
    }

    public function profile(Request $request): JsonResponse
    {
        return InterviewProfileResource::make($this->profiles->get($request->user()->id))->response()->setStatusCode(200);
    }

    public function saveProfile(InterviewProfileRequest $request): JsonResponse
    {
        return InterviewProfileResource::make($this->profiles->save($request->user()->id, $request->validated()))->response()->setStatusCode(200);
    }
}
