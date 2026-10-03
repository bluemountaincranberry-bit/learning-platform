<?php

namespace App\Modules\Learning\Application;

use App\Modules\Content\Application\Contracts\GrammarConfidenceRecorderInterface;
use App\Modules\Learning\Domain\Models\UserGrammarRule;

class GrammarConfidenceRecorder implements GrammarConfidenceRecorderInterface
{
    public function recordCalculated(int $userId, int $grammarRuleId, float $confidence): void
    {
        $progress = UserGrammarRule::query()->firstOrNew([
            'user_id' => $userId,
            'grammar_rule_id' => $grammarRuleId,
        ]);

        if (! $progress->exists) {
            $progress->status = UserGrammarRule::STATUS_LEARNING;
            $progress->started_at = now();
        }

        $progress->confidence_calculated = $confidence;
        $progress->confidence_calculated_at = now();
        $progress->save();
    }
}
