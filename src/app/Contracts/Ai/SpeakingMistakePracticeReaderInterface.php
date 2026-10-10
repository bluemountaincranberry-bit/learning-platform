<?php

namespace App\Contracts\Ai;

interface SpeakingMistakePracticeReaderInterface
{
    /** @return list<array<string, mixed>> */
    public function cardsForUser(int $userId, array $mistakeIds, int $count): array;
}
