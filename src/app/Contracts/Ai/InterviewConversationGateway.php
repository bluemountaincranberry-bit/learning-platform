<?php

namespace App\Contracts\Ai;

interface InterviewConversationGateway
{
    public function create(int $userId, string $title): int;

    /** @param array<string, mixed>|null $voice */
    public function enqueueMessage(int $conversationId, int $userId, string $content, ?array $voice = null): int|false;

    /** @return array<int, array{id: int, role: string, content: string, voice_audio_url: ?string, voice_audio_pinned: bool, voice_audio_expires_at: ?string}> */
    public function history(int $conversationId, int $userId): array;
}
