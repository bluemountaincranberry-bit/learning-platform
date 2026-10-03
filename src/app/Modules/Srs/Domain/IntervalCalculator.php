<?php

namespace App\Modules\Srs\Domain;

use App\Modules\Srs\Application\Data\IntervalDecision;

class IntervalCalculator
{
    public function calculate(int $previousInterval, float $easeFactor, int $grade): IntervalDecision
    {
        if (! ReviewGradeRules::isValid($grade)) {
            throw new \InvalidArgumentException('Review grade must be between 0 and 5.');
        }

        $nextEase = $this->nextEase($easeFactor, $grade);

        return new IntervalDecision(
            intervalDays: $this->nextInterval($previousInterval, $nextEase, $grade),
            easeFactor: $nextEase,
        );
    }

    public function nextInterval(int $previousInterval, float $easeFactor, int $grade): int
    {
        if (ReviewGradeRules::isFailing($grade)) {
            return 1;
        }

        $base = max(1, $previousInterval);
        $multiplier = $grade >= 4 ? $easeFactor : 1.3;

        return (int) max(1, round($base * $multiplier));
    }

    public function nextEase(float $easeFactor, int $grade): float
    {
        $delta = match (true) {
            $grade >= 5 => 0.15,
            $grade === 4 => 0.05,
            $grade === 3 => -0.1,
            default => -0.2,
        };

        return round(max(1.3, min(2.8, $easeFactor + $delta)), 2);
    }
}
