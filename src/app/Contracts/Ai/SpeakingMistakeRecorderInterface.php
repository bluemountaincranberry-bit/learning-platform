<?php

namespace App\Contracts\Ai;

interface SpeakingMistakeRecorderInterface
{
    /** @return array<string, mixed>|null */
    public function recordWrongAnswer(int $userId, array $data): ?array;

    public function recordPracticeOutcome(int $userId, int $mistakeId, bool $correct): ?array;
}
