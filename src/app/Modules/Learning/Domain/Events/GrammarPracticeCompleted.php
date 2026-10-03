<?php

namespace App\Modules\Learning\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A learner finished a grammar practice round and its attempt row is saved
 * (VIK-31). Listeners do the secondary work: the rule joins My grammar,
 * confidence is recalculated, the exercise pool is topped up. Not fired when
 * a round is closed early.
 */
class GrammarPracticeCompleted
{
    use Dispatchable;

    public function __construct(
        public readonly int $userId,
        public readonly int $grammarRuleId,
        public readonly int $attemptId,
        public readonly string $level,
        public readonly float $scorePct,
    ) {}
}
