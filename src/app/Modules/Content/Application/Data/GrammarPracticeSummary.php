<?php

namespace App\Modules\Content\Application\Data;

use Carbon\CarbonImmutable;

/** The learner's last finished practice round on a rule. */
final readonly class GrammarPracticeSummary
{
    public function __construct(
        public float $scorePct,
        public int $correctCount,
        public int $scoredCount,
        public ?string $level,
        public CarbonImmutable $completedAt,
    ) {}
}
