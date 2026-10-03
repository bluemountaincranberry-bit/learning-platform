<?php

namespace App\Modules\Content\Application\Contracts;

interface GrammarConfidenceRecorderInterface
{
    public function recordCalculated(int $userId, int $grammarRuleId, float $confidence): void;
}
