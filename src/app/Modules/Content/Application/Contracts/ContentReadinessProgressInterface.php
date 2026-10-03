<?php

namespace App\Modules\Content\Application\Contracts;

interface ContentReadinessProgressInterface
{
    /**
     * @param  list<int>  $lexemeIds
     * @param  list<int>  $grammarRuleIds
     * @return array{words_learned: int, grammar_learned: int}
     */
    public function learnedCounts(int $userId, array $lexemeIds, array $grammarRuleIds): array;
}
