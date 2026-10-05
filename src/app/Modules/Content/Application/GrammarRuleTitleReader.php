<?php

namespace App\Modules\Content\Application;

use App\Modules\Content\Application\Contracts\GrammarRuleTitleReaderInterface;
use App\Modules\Content\Domain\Models\GrammarRule;

final class GrammarRuleTitleReader implements GrammarRuleTitleReaderInterface
{
    public function titlesForIds(array $ruleIds): array
    {
        return GrammarRule::query()->whereNull('owner_user_id')->whereIn('id', $ruleIds)->pluck('title')->all();
    }
}
