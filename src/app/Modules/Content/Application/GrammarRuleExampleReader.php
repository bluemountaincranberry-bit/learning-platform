<?php

namespace App\Modules\Content\Application;

use App\Modules\Content\Application\Contracts\GrammarRuleExampleReaderInterface;
use App\Modules\Content\Domain\Models\GrammarRuleExample;
use App\Modules\Content\Domain\Models\GrammarRuleExampleHide;
use Illuminate\Support\Collection;

final class GrammarRuleExampleReader implements GrammarRuleExampleReaderInterface
{
    public function forLearner(int $ruleId, ?int $userId): Collection
    {
        return GrammarRuleExample::query()
            ->where('grammar_rule_id', $ruleId)
            ->whereNull('archived_at')
            ->when($userId !== null, fn ($query) => $query->whereNotIn(
                'id',
                GrammarRuleExampleHide::query()->where('user_id', $userId)->select('grammar_rule_example_id')
            ))
            ->orderByRaw('content_id is null')
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function hide(int $ruleId, int $exampleId, int $userId): bool
    {
        $belongs = GrammarRuleExample::query()->whereKey($exampleId)->where('grammar_rule_id', $ruleId)->whereNull('archived_at')->exists();
        if (! $belongs) {
            return false;
        }

        GrammarRuleExampleHide::query()->firstOrCreate([
            'user_id' => $userId,
            'grammar_rule_example_id' => $exampleId,
        ]);

        return true;
    }
}
