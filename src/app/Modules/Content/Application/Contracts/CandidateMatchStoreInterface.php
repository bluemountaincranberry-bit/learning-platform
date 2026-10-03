<?php

namespace App\Modules\Content\Application\Contracts;

interface CandidateMatchStoreInterface
{
    /** @return array<int, array{id:int, normalized_lemma:?string, normalized_text:string, lemma:?string, text:string}> */
    public function lexemeCandidates(int $runId): array;

    /** @return array<int, array{id:int, title:string, summary:?string}> */
    public function grammarCandidates(int $runId): array;

    public function exactLexemeId(string $normalizedLemma, string $language): ?int;

    /** @return array<int, int> */
    public function lexemeIdsForLanguage(string $language): array;

    public function updateLexemeMatch(int $candidateId, ?int $lexemeId, ?float $score): void;

    public function updateGrammarMatch(int $candidateId, ?int $ruleId, ?float $score): void;
}
