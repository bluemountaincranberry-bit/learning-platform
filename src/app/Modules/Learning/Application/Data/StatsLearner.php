<?php

namespace App\Modules\Learning\Application\Data;

final readonly class StatsLearner
{
    public function __construct(
        public int $userId,
        public ?string $timezone,
        public ?int $dailyGoal,
    ) {}
}
