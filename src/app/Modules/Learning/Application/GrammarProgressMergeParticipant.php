<?php

namespace App\Modules\Learning\Application;

use App\Modules\Content\Application\Contracts\GrammarRuleMergeParticipant;
use App\Modules\Learning\Domain\Models\LessonGrammarCandidate;
use App\Modules\Learning\Domain\Models\UserGrammarRule;

/**
 * VIK-16: moves learners' grammar progress and lesson candidates from a
 * merged-away duplicate rule to the kept rule. When a learner has progress
 * on both, the two rows become one without losing anything: learned wins,
 * the earliest start and learned dates are kept, a manual confidence on
 * the kept rule wins over the duplicate's.
 */
final class GrammarProgressMergeParticipant implements GrammarRuleMergeParticipant
{
    public function reassignGrammarRule(int $fromRuleId, int $toRuleId): void
    {
        foreach (UserGrammarRule::query()->where('grammar_rule_id', $fromRuleId)->get() as $from) {
            $to = UserGrammarRule::query()
                ->where('user_id', $from->user_id)->where('grammar_rule_id', $toRuleId)->first();

            if ($to === null) {
                $from->update(['grammar_rule_id' => $toRuleId]);

                continue;
            }

            $to->fill([
                'status' => in_array(UserGrammarRule::STATUS_LEARNED, [$to->status, $from->status], true)
                    ? UserGrammarRule::STATUS_LEARNED
                    : $to->status,
                'started_at' => $this->earliest($to->started_at, $from->started_at),
                'learned_at' => $this->earliest($to->learned_at, $from->learned_at),
                'confidence_manual' => $to->confidence_manual ?? $from->confidence_manual,
                'confidence_calculated' => $to->confidence_calculated ?? $from->confidence_calculated,
                'confidence_calculated_at' => $to->confidence_calculated_at ?? $from->confidence_calculated_at,
            ])->save();

            $from->delete();
        }

        LessonGrammarCandidate::query()->where('matched_grammar_rule_id', $fromRuleId)
            ->update(['matched_grammar_rule_id' => $toRuleId]);
    }

    private function earliest(mixed $a, mixed $b): mixed
    {
        if ($a === null || $b === null) {
            return $a ?? $b;
        }

        return $a->lessThanOrEqualTo($b) ? $a : $b;
    }
}
