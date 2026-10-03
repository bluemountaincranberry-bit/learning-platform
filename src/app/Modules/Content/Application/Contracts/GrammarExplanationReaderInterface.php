<?php

namespace App\Modules\Content\Application\Contracts;

use App\Modules\Content\Application\Data\GrammarExplanationSource;

interface GrammarExplanationReaderInterface
{
    public function publishedRule(int $ruleId): ?GrammarExplanationSource;
}
