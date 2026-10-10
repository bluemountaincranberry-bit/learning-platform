<?php

namespace App\Modules\Interview\Application;

use App\Contracts\Ai\InterviewDraftWriter;
use App\Modules\Interview\Domain\Models\InterviewAiDraft;
use App\Modules\Interview\Domain\Models\InterviewPracticeSession;
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
        $data = Validator::make($proposal, [
            'prompt_en' => ['required', 'string', 'max:2000'],
            'prompt_ru' => ['nullable', 'string', 'max:2000'],
            'topic_id' => ['nullable', 'integer'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:80'],
        ])->validate();
        if (! empty($data['topic_id'])) {
            InterviewTopic::query()->where('user_id', $userId)->findOrFail($data['topic_id']);
        }

        return InterviewAiDraft::query()->create(['user_id' => $userId, 'kind' => 'question', 'payload' => $data]);
    }

    public function confirm(int $draftId, int $userId): InterviewQuestion
    {
        return DB::transaction(function () use ($draftId, $userId): InterviewQuestion {
            $draft = InterviewAiDraft::query()->where('user_id', $userId)->lockForUpdate()->findOrFail($draftId);
            abort_unless($draft->status === 'pending', 409, 'This proposal has already been decided.');
            abort_unless($draft->kind === 'question', 409, 'This proposal type cannot be confirmed here.');
            $data = $draft->payload;
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
