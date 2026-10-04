<?php

namespace App\Modules\Content\Application\Contracts;

use App\Modules\Content\Domain\Models\GrammarRuleExample;
use Illuminate\Support\Collection;

/** The examples a learner sees on a rule page, and hiding a bad one. */
interface GrammarRuleExampleReaderInterface
{
    /**
     * Examples from contents first (real context), then primary, then
     * catalog order; without the ones this learner hid.
     *
     * @return Collection<int, GrammarRuleExample>
     */
    public function forLearner(int $ruleId, ?int $userId): Collection;

    /** @return bool false when the example does not belong to the rule */
    public function hide(int $ruleId, int $exampleId, int $userId): bool;
}
