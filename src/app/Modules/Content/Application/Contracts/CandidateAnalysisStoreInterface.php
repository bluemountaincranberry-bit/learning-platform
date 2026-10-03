<?php

namespace App\Modules\Content\Application\Contracts;

interface CandidateAnalysisStoreInterface
{
    /** @param array<string, mixed> $attributes */
    public function createLexeme(int $runId, array $attributes): void;

    /** @param array<string, mixed> $attributes */
    public function createGrammar(int $runId, array $attributes): void;

    /** @return array<int, string> */
    public function normalizedLexemeTexts(int $runId): array;

    /** @return array{lexemes: array<int, array<string, mixed>>, grammar: array<int, array<string, mixed>>} */
    public function listingForRun(int $runId): array;

    public function deleteForRun(int $runId): void;
}
