<?php

namespace App\Contracts\Ai;

interface LessonAnalysisStoreInterface
{
    public function getRun(int $runId): ?LessonAnalysisContext;

    public function startRun(int $runId): bool;

    public function completeRun(int $runId): void;

    public function failRun(int $runId, string $reason): void;

    /** @param array<string, mixed> $attributes */
    public function createLexemeCandidate(int $runId, array $attributes): void;

    /** @param array<string, mixed> $attributes */
    public function createGrammarCandidate(int $runId, array $attributes): void;

    /** @return array<int, array{id:int, normalized_text:string, text:string}> */
    public function lexemeCandidates(int $runId): array;

    /** @return array<int, array{id:int, title:string, summary:?string}> */
    public function grammarCandidates(int $runId): array;

    public function updateLexemeMatch(int $candidateId, ?int $lexemeId, ?float $score): void;

    public function updateGrammarMatch(int $candidateId, ?int $ruleId, ?float $score): void;
}
