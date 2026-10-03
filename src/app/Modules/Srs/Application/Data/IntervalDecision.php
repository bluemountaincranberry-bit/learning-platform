<?php

namespace App\Modules\Srs\Application\Data;

final readonly class IntervalDecision
{
    public function __construct(
        public int $intervalDays,
        public float $easeFactor,
    ) {}
}
