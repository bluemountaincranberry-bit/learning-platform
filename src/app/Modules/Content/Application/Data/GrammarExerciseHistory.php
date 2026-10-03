<?php

namespace App\Modules\Content\Application\Data;

use Carbon\CarbonImmutable;

/** When the learner last saw an exercise in practice, and how it went. */
final readonly class GrammarExerciseHistory
{
    public function __construct(
        public int $exerciseId,
        public CarbonImmutable $lastSeenAt,
        public string $lastOutcome,
    ) {}
}
