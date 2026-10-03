<?php

namespace App\Modules\Learning\Interfaces\Listeners;

use App\Modules\Content\Application\Contracts\GrammarAttemptLogInterface;
use App\Modules\Content\Application\Contracts\GrammarProgressStoreInterface;
use App\Modules\Learning\Domain\Events\GrammarPracticeCompleted;

/**
 * Practicing a rule puts it in My grammar (as learning, never as learned —
 * that stays the learner's call) and refreshes its calculated confidence.
 * Runs synchronously so the result screen can show confidence before → after.
 */
final class AddPracticedRuleToMyGrammar
{
    public function __construct(
        private readonly GrammarProgressStoreInterface $progress,
        private readonly GrammarAttemptLogInterface $attempts,
    ) {}

    public function handle(GrammarPracticeCompleted $event): void
    {
        $this->progress->startLearning($event->userId, $event->grammarRuleId);
        $this->attempts->recalculateConfidence($event->userId, $event->grammarRuleId);
    }
}
