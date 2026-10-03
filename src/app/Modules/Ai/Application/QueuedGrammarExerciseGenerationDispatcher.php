<?php

namespace App\Modules\Ai\Application;

use App\Contracts\Ai\GrammarExerciseGenerationDispatcher;
use App\Modules\Ai\Interfaces\Jobs\GenerateGrammarExercisesJob;

final class QueuedGrammarExerciseGenerationDispatcher implements GrammarExerciseGenerationDispatcher
{
    public function dispatch(int $generationId): void
    {
        GenerateGrammarExercisesJob::dispatch($generationId);
    }
}
