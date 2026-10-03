<?php

namespace App\Contracts\Ai;

interface GrammarExerciseGenerationDispatcher
{
    /** Queues the AI batch recorded as grammar_exercise_generations.id = $generationId. */
    public function dispatch(int $generationId): void;
}
