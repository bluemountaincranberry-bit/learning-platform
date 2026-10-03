<?php

namespace App\Modules\Learning\Interfaces\Listeners;

use App\Modules\Content\Application\Contracts\GrammarAttemptLogInterface;
use App\Modules\Content\Application\Contracts\GrammarExerciseGenerationsInterface;
use App\Modules\Content\Application\Contracts\GrammarExercisePoolInterface;
use App\Modules\Learning\Domain\Events\GrammarPracticeCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * After a round, queue another AI batch in the background when the learner
 * has few unseen exercises left for the rule, so the next round is fresh.
 * Subject to the same per-rule, per-day batch limit as on-demand generation.
 * Queued: secondary work that must never fail the request that saved the round.
 */
final class TopUpGrammarExercisePool implements ShouldQueue
{
    public function __construct(
        private readonly GrammarExercisePoolInterface $pool,
        private readonly GrammarAttemptLogInterface $attempts,
        private readonly GrammarExerciseGenerationsInterface $generations,
    ) {}

    public function handle(GrammarPracticeCompleted $event): void
    {
        $seen = $this->attempts->exerciseHistory($event->userId, $event->grammarRuleId);
        $unseen = 0;
        foreach ($this->pool->available($event->grammarRuleId, $event->userId) as $exercise) {
            $unseen += isset($seen[$exercise->id]) ? 0 : 1;
        }

        if ($unseen < (int) config('ai.exercises.practice.top_up_below', 10)) {
            $this->generations->request($event->grammarRuleId, $event->userId, (int) config('ai.exercises.practice.top_up_batch', 10));
        }
    }
}
