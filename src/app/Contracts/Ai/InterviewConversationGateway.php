<?php

namespace App\Contracts\Ai;

interface InterviewConversationGateway
{
    public function create(int $userId, string $title): int;

    public function enqueueMessage(int $conversationId, int $userId, string $content): int|false;

    /** @return array<int, array{id: int, role: string, content: string}> */
    public function history(int $conversationId, int $userId): array;
}
