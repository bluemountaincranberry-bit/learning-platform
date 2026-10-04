<?php

namespace App\Modules\Ai\Application;

use App\Contracts\Ai\GrammarRuleExampleGenerationDispatcher;
use App\Modules\Ai\Interfaces\Jobs\GenerateGrammarRuleExamplesJob;

final class QueuedGrammarRuleExampleGenerationDispatcher implements GrammarRuleExampleGenerationDispatcher
{
    public function dispatch(int $generationId): void
    {
        GenerateGrammarRuleExamplesJob::dispatch($generationId);
    }
}
