<?php

namespace App\Modules\Interview\Application;

use App\Contracts\Ai\InterviewDraftWriter;
use App\Modules\Interview\Domain\Models\InterviewAiDraft;
use App\Modules\Interview\Domain\Models\InterviewPracticeSession;
use App\Modules\Interview\Domain\Models\InterviewProfile;
use App\Modules\Interview\Domain\Models\InterviewQuestion;
use App\Modules\Interview\Domain\Models\InterviewTag;
use App\Modules\Interview\Domain\Models\InterviewTopic;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class InterviewDraftService implements InterviewDraftWriter
{
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

    public function confirm(int $draftId, int $userId): InterviewQuestion|InterviewProfile
    {
        return DB::transaction(function () use ($draftId, $userId): InterviewQuestion|InterviewProfile {
            $draft = InterviewAiDraft::query()->where('user_id', $userId)->lockForUpdate()->findOrFail($draftId);
            abort_unless($draft->status === 'pending', 409, 'This proposal has already been decided.');
            $data = $draft->payload;
            if ($draft->kind === 'profile') {
                $profile = InterviewProfile::query()->firstOrNew(['user_id' => $userId]);
                $profile->fill(collect($data)->except('milestones')->all())->save();
                foreach ($data['milestones'] ?? [] as $milestone) {
                    $profile->milestones()->create($milestone);
                }
                $draft->update(['status' => 'confirmed', 'result_profile_id' => $profile->id, 'decided_at' => now()]);

                return $profile->load('milestones');
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
