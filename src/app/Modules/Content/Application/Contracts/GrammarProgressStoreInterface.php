<?php

namespace App\Modules\Content\Application\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface GrammarProgressStoreInterface
{
    public function startLearning(int $userId, int $grammarRuleId): void;

    public function markLearned(int $userId, int $grammarRuleId): void;

    public function remove(int $userId, int $grammarRuleId): void;

    public function setManualConfidence(int $userId, int $grammarRuleId, ?float $confidence): void;

    /** @param array{status?: string, per_page?: int, page?: int} $filters */
    public function getPaginated(int $userId, array $filters = []): LengthAwarePaginator;

    /**
     * @return array{in_my_list: \Illuminate\Support\Collection<int, int>, learned: \Illuminate\Support\Collection<int, int>, confidence_manual: \Illuminate\Support\Collection<int, float|null>, confidence_calculated: \Illuminate\Support\Collection<int, float|null>}
     */
    public function flags(int $userId): array;
}
