<?php

namespace App\Modules\Ai\Application;

use App\Modules\Ai\Domain\Models\GrammarRuleEmbedding;
use App\Modules\Content\Application\Contracts\GrammarRuleMergeParticipant;

/**
 * VIK-16: a merged-away (archived) rule must not stay matchable by
 * embedding, or analysis would keep linking new content to it. The kept
 * rule already has its own embedding, so the duplicate's is dropped
 * (derived data, recomputable).
 */
final class GrammarRuleEmbeddingMergeParticipant implements GrammarRuleMergeParticipant
{
    public function reassignGrammarRule(int $fromRuleId, int $toRuleId): void
    {
        GrammarRuleEmbedding::query()->where('grammar_rule_id', $fromRuleId)->delete();
    }
}
