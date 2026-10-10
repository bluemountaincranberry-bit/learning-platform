<?php

namespace App\Modules\Interview\Application;

use App\Contracts\Ai\InterviewConversationGateway;
use App\Contracts\Ai\InterviewDraftWriter;
use App\Modules\Interview\Domain\Models\InterviewAiDraft;
use App\Modules\Interview\Domain\Models\InterviewAnswerRevision;
use App\Modules\Interview\Domain\Models\InterviewPracticeSession;
use App\Modules\Interview\Domain\Models\InterviewProfile;
use App\Modules\Interview\Domain\Models\InterviewQuestion;
use App\Modules\Interview\Domain\Models\InterviewTag;
use App\Modules\Interview\Domain\Models\InterviewTopic;
use App\Modules\Learning\Application\Contracts\PersonalVocabularyWriterInterface;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class InterviewDraftService implements InterviewDraftWriter
{
    public function __construct(
        private readonly PersonalVocabularyWriterInterface $vocabulary,
        private readonly InterviewConversationGateway $conversations,
    ) {}

    public function questionDraft(int $conversationId, int $userId, array $proposal): int
    {
        InterviewPracticeSession::query()->where('agent_conversation_id', $conversationId)
            ->where('user_id', $userId)->firstOrFail();

        return $this->createQuestionDraft($userId, $proposal)->id;
    }

    /** @param array<string, mixed> $proposal */
    public function createQuestionDraft(int $userId, array $proposal): InterviewAiDraft
    {
        $data = Validator::make($proposal, InterviewQuestionDraftRules::rules())->validate();
        if (! empty($data['topic_id'])) {
            InterviewTopic::query()->where('user_id', $userId)->findOrFail($data['topic_id']);
        }

        return InterviewAiDraft::query()->create(['user_id' => $userId, 'kind' => 'question', 'payload' => $data]);
    }

    public function profileDraft(int $conversationId, int $userId, array $proposal): int
    {
        InterviewPracticeSession::query()->where('agent_conversation_id', $conversationId)
            ->where('user_id', $userId)->firstOrFail();
        $data = Validator::make($proposal, [
            'career_goal' => ['sometimes', 'nullable', 'string', 'max:500'],
            'skills' => ['sometimes', 'array'], 'skills.*' => ['string', 'max:120'],
            'experience_level' => ['sometimes', 'nullable', 'string', 'max:120'],
            'projects' => ['sometimes', 'array'], 'projects.*' => ['string', 'max:1000'],
            'experience_stories' => ['sometimes', 'array'], 'experience_stories.*' => ['string', 'max:4000'],
            'milestones' => ['sometimes', 'array', 'max:50'],
            'milestones.*.title' => ['required', 'string', 'max:250'],
            'milestones.*.target_date' => ['nullable', 'date'],
        ])->validate();
        abort_if($data === [], 422, 'A profile proposal must contain at least one change.');

        return InterviewAiDraft::query()->create(['user_id' => $userId, 'kind' => 'profile', 'payload' => $data])->id;
    }

    public function answerDraft(int $conversationId, int $userId, array $proposal): int
    {
        $session = InterviewPracticeSession::query()->where('agent_conversation_id', $conversationId)
            ->where('user_id', $userId)->firstOrFail();
        $data = Validator::make($proposal, [
            'question_id' => ['required', 'integer'],
            'variant' => ['required', 'in:short,full'],
            'text_en' => ['present', 'nullable', 'string', 'max:10000'],
            'text_ru' => ['present', 'nullable', 'string', 'max:10000'],
        ])->validate();
        abort_unless(in_array((int) $data['question_id'], array_map('intval', $session->question_ids ?? []), true), 404);
        $question = InterviewQuestion::query()->where('user_id', $userId)->findOrFail($data['question_id']);
        $data['question_prompt_en'] = $question->prompt_en;
        $data['question_prompt_ru'] = $question->prompt_ru;

        return InterviewAiDraft::query()->create(['user_id' => $userId, 'kind' => 'answer', 'payload' => $data])->id;
    }

    public function vocabularyDraft(int $conversationId, int $userId, array $proposal): int
    {
        InterviewPracticeSession::query()->where('agent_conversation_id', $conversationId)
            ->where('user_id', $userId)->firstOrFail();
        $data = Validator::make($proposal, [
            'lemma' => ['required', 'string', 'max:120'],
            'language' => ['required', 'in:en'],
        ])->validate();

        return InterviewAiDraft::query()->create(['user_id' => $userId, 'kind' => 'vocabulary', 'payload' => $data])->id;
    }

    public function observationDraft(int $conversationId, int $userId, array $proposal): int
    {
        $session = InterviewPracticeSession::query()->where('agent_conversation_id', $conversationId)
            ->where('user_id', $userId)->firstOrFail();
        abort_if($session->status !== 'active', 409, 'Only an active practice session can propose a question state change.');
        $data = Validator::make($proposal, [
            'question_id' => ['required', 'integer'],
            'preparation_state' => ['required', 'in:needs_practice,confident'],
            'evidence' => ['required', 'string', 'max:2000'],
            'reason' => ['required', 'string', 'max:1000'],
        ])->validate();
        abort_unless(in_array((int) $data['question_id'], array_map('intval', $session->question_ids ?? []), true), 404);
        $question = InterviewQuestion::query()->where('user_id', $userId)->findOrFail($data['question_id']);
        $sessionQuestions = InterviewQuestion::query()->where('user_id', $userId)
            ->whereIn('id', array_map('intval', $session->question_ids ?? []))->get(['id', 'prompt_en']);
        $activeQuestionId = null;
        $sourceMessage = null;
        foreach ($this->conversations->history($conversationId, $userId) as $message) {
            if ($message['role'] === 'assistant') {
                $matchingQuestionIds = [];
                foreach ($sessionQuestions as $sessionQuestion) {
                    if (str_contains($message['content'], $sessionQuestion->prompt_en)) {
                        $matchingQuestionIds[] = $sessionQuestion->id;
                    }
                }
                $activeQuestionId = count($matchingQuestionIds) === 1 ? $matchingQuestionIds[0] : null;
            } else {
                if ($activeQuestionId === (int) $question->id && str_contains($message['content'], $data['evidence'])) {
                    $sourceMessage = $message;
                }
                $activeQuestionId = null;
            }
        }
        abort_unless($sourceMessage !== null, 422, 'Evidence must quote a learner answer to this question.');
        $data['question_prompt_en'] = $question->prompt_en;
        $data['question_prompt_ru'] = $question->prompt_ru;
        $data['source_conversation_id'] = $conversationId;
        $data['source_message_id'] = $sourceMessage['id'];

        return InterviewAiDraft::query()->create(['user_id' => $userId, 'kind' => 'observation', 'payload' => $data])->id;
    }

    public function confirm(int $draftId, int $userId): InterviewQuestion|InterviewProfile|array
    {
        return DB::transaction(function () use ($draftId, $userId): InterviewQuestion|InterviewProfile|array {
            $draft = InterviewAiDraft::query()->where('user_id', $userId)->lockForUpdate()->findOrFail($draftId);
            abort_unless($draft->status === 'pending', 409, 'This proposal has already been decided.');
            $data = $draft->payload;
            if ($draft->kind === 'profile') {
                User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();
                $profile = InterviewProfile::query()->firstOrNew(['user_id' => $userId]);
                $profile->fill(collect($data)->except('milestones')->all())->save();
                foreach ($data['milestones'] ?? [] as $milestone) {
                    $existingMilestone = $profile->milestones()->where('title', $milestone['title'])->first();
                    if ($existingMilestone) {
                        $existingMilestone->update(['target_date' => $milestone['target_date'] ?? null]);
                    } else {
                        $profile->milestones()->create($milestone);
                    }
                }
                $draft->update(['status' => 'confirmed', 'result_profile_id' => $profile->id, 'decided_at' => now()]);

                return $profile->load('milestones');
            }
            if ($draft->kind === 'answer') {
                $question = InterviewQuestion::query()->where('user_id', $userId)->lockForUpdate()->findOrFail($data['question_id']);
                $variant = $question->answers()->where('kind', $data['variant'])->lockForUpdate()->first();
                if ($variant && ($variant->text_en !== $data['text_en'] || $variant->text_ru !== $data['text_ru'])) {
                    InterviewAnswerRevision::query()->create([
                        'answer_variant_id' => $variant->id, 'created_by' => $userId,
                        'text_en' => $variant->text_en, 'text_ru' => $variant->text_ru,
                    ]);
                }
                $variant ??= $question->answers()->make(['kind' => $data['variant']]);
                $variant->fill(['text_en' => $data['text_en'], 'text_ru' => $data['text_ru']])->save();
                $draft->update(['status' => 'confirmed', 'result_question_id' => $question->id, 'result_answer_id' => $variant->id, 'decided_at' => now()]);

                return $question->fresh(['topic', 'tags', 'answers']);
            }
            if ($draft->kind === 'vocabulary') {
                $word = $this->vocabulary->addConfirmedWord($userId, $data['language'], $data['lemma']);
                $draft->update(['status' => 'confirmed', 'decided_at' => now()]);

                return $word;
            }
            if ($draft->kind === 'observation') {
                $question = InterviewQuestion::query()->where('user_id', $userId)->lockForUpdate()->findOrFail($data['question_id']);
                $question->update(['preparation_state' => $data['preparation_state']]);
                $draft->update(['status' => 'confirmed', 'result_question_id' => $question->id, 'decided_at' => now()]);

                return $question->fresh(['topic', 'tags', 'answers']);
            }
            abort_unless($draft->kind === 'question', 409, 'This proposal type cannot be confirmed here.');
            if (! empty($data['topic_id'])) {
                $this->ownedTopic((int) $data['topic_id'], $userId);
            }
            $question = InterviewQuestion::query()->create([
                'user_id' => $userId, 'topic_id' => $data['topic_id'] ?? null,
                'prompt_en' => $data['prompt_en'], 'prompt_ru' => $data['prompt_ru'] ?? null,
                'preparation_state' => 'unpracticed',
            ]);
            $tags = collect($data['tags'] ?? [])->map(fn (string $name) => InterviewTag::query()->firstOrCreate(
                ['user_id' => $userId, 'name' => trim($name)]
            )->id)->all();
            $question->tags()->sync($tags);
            foreach (['short', 'full'] as $kind) {
                $question->answers()->create(['kind' => $kind]);
            }
            $draft->update(['status' => 'confirmed', 'result_question_id' => $question->id, 'decided_at' => now()]);

            return $question;
        });
    }

    public function reject(int $draftId, int $userId): void
    {
        DB::transaction(function () use ($draftId, $userId): void {
            $draft = InterviewAiDraft::query()->where('user_id', $userId)->lockForUpdate()->findOrFail($draftId);
            abort_unless($draft->status === 'pending', 409, 'This proposal has already been decided.');
            $draft->update(['status' => 'rejected', 'decided_at' => now()]);
        });
    }

    private function ownedTopic(int $topicId, int $userId): InterviewTopic
    {
        return InterviewTopic::query()->where('user_id', $userId)->findOrFail($topicId);
    }
}
