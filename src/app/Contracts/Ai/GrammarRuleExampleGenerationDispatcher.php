<?php

namespace App\Contracts\Ai;

interface GrammarRuleExampleGenerationDispatcher
{
    /** Queues the AI batch recorded as grammar_rule_example_generations.id = $generationId. */
    public function dispatch(int $generationId): void;
}
