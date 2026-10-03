<?php

namespace App\Modules\User\Application\Contracts;

interface LearningStatsReaderInterface
{
    public function getTodayLearnedCount(int $userId, ?string $timezone): int;

    public function getStreakDays(int $userId, ?string $timezone): int;
}
