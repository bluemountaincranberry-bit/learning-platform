<?php

namespace App\Contracts\Ai;

interface InterviewDraftWriter
{
    /** @param array<string, mixed> $proposal */
    public function questionDraft(int $conversationId, int $userId, array $proposal): int;

    /** @param array<string, mixed> $proposal */
    public function profileDraft(int $conversationId, int $userId, array $proposal): int;
}
