<?php

namespace App\Modules\Content\Application\Contracts;

/**
 * VIK-16: a module that keeps its own rows keyed by a grammar rule moves
 * them when Content merges a duplicate rule into the kept one. Runs inside
 * the merge transaction; implementations must resolve unique-key clashes
 * themselves (e.g. two progress rows for one learner) without losing the
 * learner's state.
 *
 * Implementations are tagged with self::TAG in their module's provider.
 */
interface GrammarRuleMergeParticipant
{
    public const TAG = 'content.grammar-rule-merge-participants';

    public function reassignGrammarRule(int $fromRuleId, int $toRuleId): void;
}
