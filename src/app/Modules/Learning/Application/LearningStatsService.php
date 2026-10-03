<?php

namespace App\Modules\Learning\Application;

use App\Modules\Learning\Domain\Models\UserLexemeProgress;
use App\Modules\User\Application\Contracts\LearningStatsReaderInterface;
use Carbon\Carbon;

class LearningStatsService implements LearningStatsReaderInterface
{
    public function getTodayLearnedCount(int $userId, ?string $timezone): int
    {
        $tz = $timezone ?? config('app.timezone', 'UTC');
        $startOfDay = Carbon::now($tz)->startOfDay();
        $endOfDay = Carbon::now($tz)->endOfDay();

        return UserLexemeProgress::query()
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->count();
    }

    public function getStreakDays(int $userId, ?string $timezone): int
    {
        $tz = $timezone ?? config('app.timezone', 'UTC');
        $today = Carbon::now($tz)->startOfDay()->format('Y-m-d');

        $dates = UserLexemeProgress::query()
            ->where('user_id', $userId)
            ->pluck('created_at')
            ->map(fn ($at) => Carbon::parse($at)->setTimezone($tz)->format('Y-m-d'))
            ->unique()
            ->sort()
            ->values()
            ->all();
        $set = array_flip($dates);

        if (! isset($set[$today])) {
            return 0;
        }

        $streak = 0;
        $cursor = Carbon::parse($today, $tz);
        while (isset($set[$cursor->format('Y-m-d')])) {
            $streak++;
            $cursor->subDay();
        }

        return $streak;
    }
}
