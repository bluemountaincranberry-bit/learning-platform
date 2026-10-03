<?php

namespace App\Modules\Content\Application\Contracts;

use App\Modules\Content\Domain\Models\GrammarRule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface GrammarProgressServiceInterface
{
    public function startLearningById(int $grammarRuleId, int $userId): void;

    public function startLearning(GrammarRule $rule, int $userId): void;

    public function markLearned(GrammarRule $rule, int $userId): void;

    public function unmarkLearned(GrammarRule $rule, int $userId): void;

    /**
     * Sets the learner's own self-rated confidence for this rule (0-100,
     * null to clear). Independent of confidence_calculated — see the
     * migration's docblock for why the two are kept separate.
     */
    public function setManualConfidence(GrammarRule $rule, int $userId, ?float $confidence): void;

    /**
     * @param  array{status?: string, per_page?: int, page?: int}  $filters
     */
    public function getPaginated(int $userId, array $filters = []): LengthAwarePaginator;

    /**
     * Rule-id sets/maps for annotating GrammarRuleResource with
     * in_my_list/learned/confidence_* fields, mirroring how
     * SrsService::getInReviewItemKeys() is used for lexemes.
     *
     * @return array{in_my_list: \Illuminate\Support\Collection<int, int>, learned: \Illuminate\Support\Collection<int, int>, confidence_manual: \Illuminate\Support\Collection<int, float|null>, confidence_calculated: \Illuminate\Support\Collection<int, float|null>}
     */
    public function getFlagsForUser(int $userId): array;
}
