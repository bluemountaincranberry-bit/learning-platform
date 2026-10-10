<?php

namespace App\Modules\Interview\Application;

use App\Contracts\Ai\InterviewDraftWriter;
use App\Modules\Interview\Domain\Models\InterviewAiDraft;
use App\Modules\Interview\Domain\Models\InterviewPracticeSession;
use App\Modules\Interview\Domain\Models\InterviewTopic;
use Illuminate\Support\Facades\Validator;

final class InterviewDraftService implements InterviewDraftWriter
{
    public function questionDraft(int $conversationId, int $userId, array $proposal): int
    {
        $session = InterviewPracticeSession::query()->where('agent_conversation_id', $conversationId)
            ->where('user_id', $userId)->firstOrFail();
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
        $draft = InterviewAiDraft::query()->create(['user_id' => $userId, 'kind' => 'question', 'payload' => $data]);

        return $draft->id;
    }
}
