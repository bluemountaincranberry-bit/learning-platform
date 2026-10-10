<?php

namespace App\Modules\Interview\Application;

use App\Contracts\Ai\InterviewConversationGateway;
use App\Modules\Interview\Domain\Models\InterviewPracticeSession;
use App\Modules\Interview\Domain\Models\InterviewQuestion;
use App\Modules\Interview\Domain\Models\InterviewTopic;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class InterviewPracticeService
{
    public function __construct(private readonly InterviewConversationGateway $conversations) {}

    /** @param array<string, mixed> $data */
    public function start(int $userId, array $data): InterviewPracticeSession
    {
        $questionIds = array_values($data['question_ids'] ?? []);
        abort_if($questionIds !== [] && InterviewQuestion::query()->where('user_id', $userId)->whereIn('id', $questionIds)->count() !== count($questionIds), 404);
        $count = $data['question_count'] ?? max(1, count($questionIds));
        if ($questionIds === []) {
            $questionQuery = InterviewQuestion::query()->where('user_id', $userId)->where('preparation_state', '!=', 'confident');
            if (! empty($data['topic_id'])) {
                $topicIds = $this->topicSubtreeIds((int) $data['topic_id'], $userId);
                $questionQuery->whereIn('topic_id', $topicIds);
            }
            $questionIds = $questionQuery
                ->orderBy('id')->limit($count)->pluck('id')->all();
        }

        return DB::transaction(function () use ($userId, $data, $questionIds, $count): InterviewPracticeSession {
            $title = $data['mode'] === 'mock' ? 'Mock interview' : 'Coached interview practice';
            $conversationId = $this->conversations->create($userId, $title);

            return InterviewPracticeSession::query()->create([
                'user_id' => $userId,
                'agent_conversation_id' => $conversationId,
                'mode' => $data['mode'],
                'status' => 'active',
                'question_ids' => $questionIds,
                'question_count' => $count,
                'focus' => $data['focus'] ?? null,
            ]);
        });
    }

    public function ownedSession(int $sessionId, int $userId): InterviewPracticeSession
    {
        return InterviewPracticeSession::query()->where('user_id', $userId)->findOrFail($sessionId);
    }

    public function complete(InterviewPracticeSession $session): InterviewPracticeSession
    {
        abort_if($session->status !== 'active', 409, 'Only an active practice session can be completed.');
        $session->update(['status' => 'completed']);

        return $session->fresh();
    }

    /** @param array<string, mixed>|null $voice */
    public function sendMessage(InterviewPracticeSession $session, int $userId, string $content, ?array $voice = null): int|false
    {
        abort_if($session->status !== 'active', 409, 'This practice session is complete.');

        return $this->conversations->enqueueMessage($session->agent_conversation_id, $userId, $content, $voice);
    }

    /** @return array<int, array{id: int, role: string, content: string}> */
    public function history(InterviewPracticeSession $session): array
    {
        return $this->conversations->history($session->agent_conversation_id, $session->user_id);
    }

    /** @return Collection<int, InterviewQuestion> */
    public function questionsForSession(InterviewPracticeSession $session): Collection
    {
        $questionIds = array_map('intval', $session->question_ids);

        return InterviewQuestion::query()->where('user_id', $session->user_id)->whereIn('id', $questionIds)
            ->with(['topic', 'tags', 'answers'])->get()
            ->sortBy(fn (InterviewQuestion $question) => array_search($question->id, $questionIds, true))->values();
    }

    /** @return array<int, int> */
    private function topicSubtreeIds(int $topicId, int $userId): array
    {
        $topic = InterviewTopic::query()->where('user_id', $userId)->findOrFail($topicId);
        $ids = [$topic->id];
        $frontier = [$topic->id];
        while ($frontier !== []) {
            $children = InterviewTopic::query()->where('user_id', $userId)->whereIn('parent_id', $frontier)->pluck('id')->all();
            $ids = [...$ids, ...$children];
            $frontier = $children;
        }

        return $ids;
    }
}
