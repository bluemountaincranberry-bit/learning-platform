<?php

namespace App\Modules\Interview\Application;

use App\Contracts\Ai\InterviewConversationGateway;
use App\Contracts\Ai\InterviewDraftWriter;
use App\Modules\Interview\Domain\Models\InterviewAiDraft;
use App\Modules\Interview\Domain\Models\InterviewAnswerRevision;
use App\Modules\Interview\Domain\Models\InterviewCoachingObservation;
use App\Modules\Interview\Domain\Models\InterviewPracticeSession;
use App\Modules\Interview\Domain\Models\InterviewProfile;
use App\Modules\Interview\Domain\Models\InterviewQuestion;
use App\Modules\Interview\Domain\Models\InterviewTag;
use App\Modules\Interview\Domain\Models\InterviewTopic;
use App\Modules\Learning\Application\Contracts\PersonalVocabularyWriterInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class InterviewDraftService implements InterviewDraftWriter
{
    public function __construct(
        private readonly PersonalVocabularyWriterInterface $vocabulary,
        private readonly InterviewConversationGateway $conversations,
    ) {}

    public function pendingDrafts(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        return InterviewAiDraft::query()->where('user_id', $userId)->where('status', 'pending')->latest()->get();
    }

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
        InterviewUseCaseException::ensure($data !== [], InterviewUseCaseException::INVALID, 'A profile proposal must contain at least one change.');

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
        InterviewUseCaseException::ensure(in_array((int) $data['question_id'], array_map('intval', $session->question_ids ?? []), true), InterviewUseCaseException::NOT_FOUND);
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
        InterviewUseCaseException::ensure($session->status === 'active', InterviewUseCaseException::CONFLICT, 'Only an active practice session can propose a question state change.');
        $data = Validator::make($proposal, [
            'question_id' => ['required', 'integer'],
            'preparation_state' => ['required', 'in:needs_practice,confident'],
            'evidence' => ['required', 'string', 'max:2000'],
            'reason' => ['required', 'string', 'max:1000'],
        ])->validate();
        $verifiedExample = $this->verifiedAnswerEvidence($session, $userId, (int) $data['question_id'], $data['evidence']);
        InterviewUseCaseException::ensure($verifiedExample !== null, InterviewUseCaseException::INVALID, 'Evidence must quote a learner answer to this question.');
        $question = InterviewQuestion::query()->where('user_id', $userId)->findOrFail($data['question_id']);
        $data['question_prompt_en'] = $question->prompt_en;
        $data['question_prompt_ru'] = $question->prompt_ru;
        $data['source_conversation_id'] = $conversationId;
        $data['source_message_id'] = $verifiedExample['source_message_id'];

        return InterviewAiDraft::query()->create(['user_id' => $userId, 'kind' => 'observation', 'payload' => $data])->id;
    }

    /** @param array<string, mixed> $proposal */
    public function patternObservationDraft(int $conversationId, int $userId, array $proposal): int
    {
        $origin = InterviewPracticeSession::query()->where('agent_conversation_id', $conversationId)
            ->where('user_id', $userId)->firstOrFail();
        InterviewUseCaseException::ensure($origin->status === 'active', InterviewUseCaseException::CONFLICT, 'Only an active practice session can propose a profile observation.');
        $data = Validator::make($proposal, [
            'pattern_type' => ['required', 'in:strength,improvement'],
            'summary' => ['required', 'string', 'max:500'],
            'examples' => ['required', 'array', 'min:2', 'max:6'],
            'examples.*.session_id' => ['required', 'integer', 'distinct'],
            'examples.*.question_id' => ['required', 'integer', 'distinct'],
            'examples.*.evidence' => ['required', 'string', 'max:2000'],
        ])->validate();

        $sourceIds = array_map('intval', array_column($data['examples'], 'session_id'));
        $sourceSessions = InterviewPracticeSession::query()->where('user_id', $userId)->whereIn('id', $sourceIds)->get()->keyBy('id');
        InterviewUseCaseException::ensure($sourceSessions->count() === count($sourceIds), InterviewUseCaseException::NOT_FOUND, 'A source practice session was not found.');

        $verifiedExamples = [];
        foreach ($data['examples'] as $example) {
            $sourceSession = $sourceSessions->get((int) $example['session_id']);
            InterviewUseCaseException::ensure($sourceSession !== null, InterviewUseCaseException::NOT_FOUND, 'A source practice session was not found.');
            InterviewUseCaseException::ensure($sourceSession->id === $origin->id || $sourceSession->status === 'completed', InterviewUseCaseException::INVALID, 'Prior examples must come from completed practice sessions.');
            $verified = $this->verifiedAnswerEvidence($sourceSession, $userId, (int) $example['question_id'], $example['evidence']);
            InterviewUseCaseException::ensure($verified !== null, InterviewUseCaseException::INVALID, 'Each example must quote the learner answer to its selected question.');
            $verifiedExamples[] = [
                'session_id' => $sourceSession->id,
                'question_id' => (int) $example['question_id'],
                ...$verified,
            ];
        }

        $messageIds = array_map(fn (array $example): int => $example['source_message_id'], $verifiedExamples);
        sort($messageIds);
        $data['examples'] = $verifiedExamples;
        $data['source_key'] = hash('sha256', $data['pattern_type'].'|'.implode(',', $messageIds));

        return InterviewAiDraft::query()->create(['user_id' => $userId, 'kind' => 'pattern_observation', 'payload' => $data])->id;
    }

    public function confirm(int $draftId, int $userId): InterviewQuestion|InterviewProfile|array
    {
        return DB::transaction(function () use ($draftId, $userId): InterviewQuestion|InterviewProfile|array {
            $draft = InterviewAiDraft::query()->where('user_id', $userId)->lockForUpdate()->findOrFail($draftId);
            InterviewUseCaseException::ensure($draft->status === 'pending', InterviewUseCaseException::CONFLICT, 'This proposal has already been decided.');
            $data = $draft->payload;
            if ($draft->kind === 'profile') {
                $this->lockUser($userId);
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
            if ($draft->kind === 'pattern_observation') {
                $this->lockUser($userId);
                $profile = InterviewProfile::query()->firstOrNew(['user_id' => $userId]);
                $profile->save();
                InterviewUseCaseException::ensure(! InterviewCoachingObservation::query()->where('profile_id', $profile->id)
                    ->where('source_key', $data['source_key'])->exists(), InterviewUseCaseException::CONFLICT, 'This evidence has already been saved as an observation.');
                $profile->observations()->create([
                    'pattern_type' => $data['pattern_type'],
                    'summary' => $data['summary'],
                    'examples' => $data['examples'],
                    'source_key' => $data['source_key'],
                ]);
                $draft->update(['status' => 'confirmed', 'result_profile_id' => $profile->id, 'decided_at' => now()]);

                return $profile->fresh()->load(['milestones', 'observations']);
            }
            if ($draft->kind === 'observation') {
                $question = InterviewQuestion::query()->where('user_id', $userId)->lockForUpdate()->findOrFail($data['question_id']);
                $question->update(['preparation_state' => $data['preparation_state']]);
                $draft->update(['status' => 'confirmed', 'result_question_id' => $question->id, 'decided_at' => now()]);

                return $question->fresh(['topic', 'tags', 'answers']);
            }
            InterviewUseCaseException::ensure($draft->kind === 'question', InterviewUseCaseException::CONFLICT, 'This proposal type cannot be confirmed here.');
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
            InterviewUseCaseException::ensure($draft->status === 'pending', InterviewUseCaseException::CONFLICT, 'This proposal has already been decided.');
            $draft->update(['status' => 'rejected', 'decided_at' => now()]);
        });
    }

    private function ownedTopic(int $topicId, int $userId): InterviewTopic
    {
        return InterviewTopic::query()->where('user_id', $userId)->findOrFail($topicId);
    }

    private function lockUser(int $userId): void
    {
        InterviewUseCaseException::ensure(DB::table('users')->where('id', $userId)->lockForUpdate()->first() !== null, InterviewUseCaseException::NOT_FOUND);
    }

    /** @return array{evidence: string, source_message_id: int, question_prompt_en: string, question_prompt_ru: ?string}|null */
    private function verifiedAnswerEvidence(InterviewPracticeSession $session, int $userId, int $questionId, string $evidence): ?array
    {
        $questionIds = array_map('intval', $session->question_ids ?? []);
        if (! in_array($questionId, $questionIds, true)) {
            return null;
        }
        $questions = InterviewQuestion::query()->where('user_id', $userId)->whereIn('id', $questionIds)->get(['id', 'prompt_en', 'prompt_ru']);
        $question = $questions->firstWhere('id', $questionId);
        if ($question === null) {
            return null;
        }

        $activeQuestionId = null;
        $source = null;
        foreach ($this->conversations->history($session->agent_conversation_id, $userId) as $message) {
            if ($message['role'] === 'assistant') {
                $matchingIds = $questions->filter(fn (InterviewQuestion $candidate): bool => str_contains($message['content'], $candidate->prompt_en))
                    ->pluck('id')->all();
                $activeQuestionId = count($matchingIds) === 1 ? (int) $matchingIds[0] : null;
            } else {
                if ($activeQuestionId === $questionId && str_contains($message['content'], $evidence)) {
                    $source = $message;
                }
                $activeQuestionId = null;
            }
        }
        if ($source === null) {
            return null;
        }

        return [
            'evidence' => $evidence,
            'source_message_id' => (int) $source['id'],
            'question_prompt_en' => $question->prompt_en,
            'question_prompt_ru' => $question->prompt_ru,
        ];
    }
}
