<?php

namespace App\Modules\Ai\Application;

use App\Contracts\Ai\LessonAssistant;
use App\Modules\Ai\Application\Agent\LessonAgentService;
use App\Modules\Ai\Domain\Models\AgentConversation;
use App\Modules\Ai\Domain\Models\AgentMessage;
use App\Modules\Ai\Interfaces\Jobs\RunAgentTurnJob;
use App\Modules\Ai\Interfaces\Jobs\RunLessonAnalysisJob;

class LessonAssistantService implements LessonAssistant
{
    public function createConversation(int $lessonId, int $userId): int
    {
        return AgentConversation::query()->create([
            'created_by' => $userId, 'lesson_id' => $lessonId,
            'status' => AgentConversation::STATUS_ACTIVE, 'agent_type' => LessonAgentService::AGENT_TYPE,
        ])->id;
    }

    public function conversationId(int $lessonId): ?int
    {
        return AgentConversation::query()->where('lesson_id', $lessonId)->value('id');
    }

    public function messages(int $lessonId): array
    {
        $conversation = AgentConversation::query()->where('lesson_id', $lessonId)->first();
        $messages = $conversation?->messages()->where('role', '!=', AgentMessage::ROLE_TOOL)
            ->orderBy('created_at')->get(['id', 'role', 'content', 'attachment_name', 'created_at']) ?? collect();

        return [
            'messages' => $messages->toArray(),
            'is_waiting' => $messages->isNotEmpty() && $messages->last()->role === AgentMessage::ROLE_USER,
        ];
    }

    public function sendMessage(int $lessonId, string $content, ?string $attachmentPath, ?string $attachmentName): void
    {
        $conversation = AgentConversation::query()->where('lesson_id', $lessonId)->firstOrFail();
        $conversation->messages()->create([
            'role' => AgentMessage::ROLE_USER, 'content' => $content !== '' ? $content : null,
            'attachment_path' => $attachmentPath, 'attachment_name' => $attachmentName,
        ]);
        RunAgentTurnJob::dispatch($conversation->id);
    }

    public function dispatchAnalysis(int $runId): void
    {
        RunLessonAnalysisJob::dispatch($runId);
    }
}
