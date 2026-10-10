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
            ->orderBy('created_at')->get([
                'id', 'role', 'content', 'attachment_name', 'created_at', 'voice_audio_path',
                'voice_audio_pinned', 'voice_audio_expires_at',
            ])->map(fn (AgentMessage $message): array => [
                'id' => $message->id,
                'role' => $message->role,
                'content' => $message->content,
                'attachment_name' => $message->attachment_name,
                'created_at' => $message->created_at,
                'voice_audio_url' => $message->voice_audio_path !== null ? "/api/ai/voice-recordings/{$message->id}/audio" : null,
                'voice_audio_pinned' => (bool) $message->voice_audio_pinned,
                'voice_audio_expires_at' => $message->voice_audio_expires_at,
            ]) ?? collect();

        return [
            'messages' => $messages->toArray(),
            'is_waiting' => $messages->isNotEmpty() && $messages->last()['role'] === AgentMessage::ROLE_USER,
        ];
    }

    public function sendMessage(int $lessonId, string $content, ?string $attachmentPath, ?string $attachmentName, array $voice = []): void
    {
        $conversation = AgentConversation::query()->where('lesson_id', $lessonId)->firstOrFail();
        $conversation->messages()->create([
            'role' => AgentMessage::ROLE_USER, 'content' => $content !== '' ? $content : null,
            'attachment_path' => $attachmentPath, 'attachment_name' => $attachmentName,
            'voice_audio_disk' => $voice['disk'] ?? null,
            'voice_audio_path' => $voice['path'] ?? null,
            'voice_audio_pinned' => $voice['pinned'] ?? false,
            'voice_audio_expires_at' => $voice['expires_at'] ?? null,
            'transcription_provider' => $voice['provider'] ?? null,
            'transcription_language' => $voice['language'] ?? null,
        ]);
        RunAgentTurnJob::dispatch($conversation->id);
    }

    public function dispatchAnalysis(int $runId): void
    {
        RunLessonAnalysisJob::dispatch($runId);
    }
}
