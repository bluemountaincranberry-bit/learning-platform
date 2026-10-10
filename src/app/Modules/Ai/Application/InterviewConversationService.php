<?php

namespace App\Modules\Ai\Application;

use App\Contracts\Ai\InterviewConversationGateway;
use App\Modules\Ai\Application\Agent\InterviewAgentService;
use App\Modules\Ai\Domain\Models\AgentConversation;
use App\Modules\Ai\Domain\Models\AgentMessage;
use App\Modules\Ai\Interfaces\Jobs\RunAgentTurnJob;
use Illuminate\Support\Facades\Cache;

final class InterviewConversationService implements InterviewConversationGateway
{
    public function create(int $userId, string $title): int
    {
        return AgentConversation::query()->create([
            'created_by' => $userId,
            'title' => $title,
            'status' => AgentConversation::STATUS_ACTIVE,
            'agent_type' => InterviewAgentService::AGENT_TYPE,
        ])->id;
    }

    public function enqueueMessage(int $conversationId, int $userId, string $content): int|false
    {
        $conversation = AgentConversation::query()->where('created_by', $userId)
            ->where('agent_type', InterviewAgentService::AGENT_TYPE)->findOrFail($conversationId);
        if (! $this->consumeRateLimit($userId)) {
            return false;
        }
        $message = $conversation->messages()->create(['role' => AgentMessage::ROLE_USER, 'content' => trim($content)]);
        RunAgentTurnJob::dispatch($conversation->id);

        return $message->id;
    }

    private function consumeRateLimit(int $userId): bool
    {
        $limit = (int) config('ai.agent.turns_per_day', 0);
        if ($limit <= 0) {
            return true;
        }
        $key = 'ai:agent:rate_limit:interview:'.$userId.':'.now()->format('Y-m-d');
        $count = (int) Cache::get($key, 0);
        if ($count >= $limit) {
            return false;
        }
        Cache::put($key, $count + 1, now()->endOfDay()->addSecond());

        return true;
    }

    public function history(int $conversationId, int $userId): array
    {
        return AgentConversation::query()->where('created_by', $userId)
            ->where('agent_type', InterviewAgentService::AGENT_TYPE)->findOrFail($conversationId)
            ->messages()->whereIn('role', [AgentMessage::ROLE_USER, AgentMessage::ROLE_ASSISTANT])->orderBy('id')
            ->get(['id', 'role', 'content'])->map(fn (AgentMessage $message) => [
                'id' => $message->id, 'role' => $message->role, 'content' => (string) $message->content,
            ])->all();
    }
}
