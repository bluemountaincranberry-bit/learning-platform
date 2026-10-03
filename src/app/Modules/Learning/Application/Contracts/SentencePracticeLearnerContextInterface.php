<?php

namespace App\Modules\Learning\Application\Contracts;

interface SentencePracticeLearnerContextInterface
{
    public function nativeLanguage(int $userId): string;

    /** @return list<array{language: ?string, title: ?string}> */
    public function recentGrammar(int $userId, int $limit): array;

    /**
     * @param  list<int>  $contentLexemeIds
     * @return array<int, int> Average skill confidence by content lexeme ID.
     */
    public function lexemeConfidenceAverages(int $userId, array $contentLexemeIds): array;

    /**
     * @param  list<int>  $grammarRuleIds
     * @return array<int, array{status: string, confidence_calculated: float}>
     */
    public function grammarProgress(int $userId, array $grammarRuleIds): array;
}
