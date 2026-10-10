<?php

namespace App\Modules\Interview\Application\Contracts;

interface InterviewSessionContextReader
{
    /** @return array<string, mixed> */
    public function forConversation(int $conversationId, int $userId): array;
}
