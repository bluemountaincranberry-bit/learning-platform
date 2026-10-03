<?php

namespace App\Modules\Learning\Application;

use App\Modules\Content\Application\Contracts\GrammarProgressStoreInterface;
use App\Modules\Learning\Domain\Models\UserGrammarRule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class GrammarProgressStore implements GrammarProgressStoreInterface
{
    public function startLearning(int $userId, int $grammarRuleId): void
    {
        UserGrammarRule::query()->firstOrCreate(
            ['user_id' => $userId, 'grammar_rule_id' => $grammarRuleId],
            ['status' => UserGrammarRule::STATUS_LEARNING, 'started_at' => now()],
        );
    }

    public function markLearned(int $userId, int $grammarRuleId): void
    {
        $progress = UserGrammarRule::query()->firstOrNew([
            'user_id' => $userId,
            'grammar_rule_id' => $grammarRuleId,
        ]);

        if (! $progress->exists) {
            $progress->started_at = now();
        }

        $progress->status = UserGrammarRule::STATUS_LEARNED;
        $progress->learned_at = now();
        $progress->save();
    }

    public function remove(int $userId, int $grammarRuleId): void
    {
        UserGrammarRule::query()
            ->where('user_id', $userId)
            ->where('grammar_rule_id', $grammarRuleId)
            ->delete();
    }

    public function setManualConfidence(int $userId, int $grammarRuleId, ?float $confidence): void
    {
        $progress = UserGrammarRule::query()->firstOrNew([
            'user_id' => $userId,
            'grammar_rule_id' => $grammarRuleId,
        ]);

        if (! $progress->exists) {
            $progress->status = UserGrammarRule::STATUS_LEARNING;
            $progress->started_at = now();
        }

        $progress->confidence_manual = $confidence;
        $progress->save();
    }

    public function getPaginated(int $userId, array $filters = []): LengthAwarePaginator
    {
        $query = UserGrammarRule::query()
            ->where('user_id', $userId);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);

        return $query->orderByDesc('started_at')->paginate($perPage);
    }

    public function flags(int $userId): array
    {
        $rows = UserGrammarRule::query()->where('user_id', $userId)
            ->get(['grammar_rule_id', 'status', 'confidence_manual', 'confidence_calculated']);

        return [
            'in_my_list' => $rows->pluck('grammar_rule_id')->flip(),
            'learned' => $rows->where('status', UserGrammarRule::STATUS_LEARNED)->pluck('grammar_rule_id')->flip(),
            'confidence_manual' => $rows->pluck('confidence_manual', 'grammar_rule_id'),
            'confidence_calculated' => $rows->pluck('confidence_calculated', 'grammar_rule_id'),
        ];
    }
}
