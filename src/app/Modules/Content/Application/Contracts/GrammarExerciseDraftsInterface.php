<?php

namespace App\Modules\Content\Application\Contracts;

use App\Modules\Content\Application\Data\GrammarExerciseSource;

interface GrammarExerciseDraftsInterface
{
    public function source(int $ruleId): GrammarExerciseSource;

    /** @param array<int, mixed> $items */
    public function createDrafts(int $ruleId, array $items): int;
}
