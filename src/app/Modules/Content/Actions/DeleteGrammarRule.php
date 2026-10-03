<?php

namespace App\Modules\Content\Actions;

use App\Modules\Content\Domain\Models\GrammarRule;

final class DeleteGrammarRule
{
    public function execute(GrammarRule $rule): void
    {
        $rule->delete();
    }
}
