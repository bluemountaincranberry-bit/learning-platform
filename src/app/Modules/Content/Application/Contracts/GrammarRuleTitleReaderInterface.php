<?php

namespace App\Modules\Content\Application\Contracts;

interface GrammarRuleTitleReaderInterface
{
    /** @param array<int, int> $ruleIds
     * @return array<int, string>
     */
    public function titlesForIds(array $ruleIds): array;
}
