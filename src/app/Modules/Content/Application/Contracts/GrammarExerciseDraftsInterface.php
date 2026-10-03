<?php

namespace App\Modules\Content\Application\Contracts;

use App\Modules\Content\Application\Data\GrammarExerciseSource;

interface GrammarExerciseDraftsInterface
{
    public function source(int $ruleId): GrammarExerciseSource;

    /**
     * Validates generated items and stores the usable ones as drafts.
     * Items whose prompt already exists for the rule are dropped.
     *
     * @param  array<int, mixed>  $items
     * @param  string  $origin  GrammarRuleExercise::ORIGIN_* — `ai` items are shown to learners without review
     */
    public function createDrafts(int $ruleId, array $items, string $origin = 'admin'): int;

    /**
     * Prompts already in the rule's pool, newest first, for a "do not repeat" instruction.
     *
     * @return list<string>
     */
    public function existingPrompts(int $ruleId, int $limit = 60): array;
}
