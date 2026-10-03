<?php

namespace App\Modules\Content\Application\Contracts;

interface ManualLexemeCandidateStoreInterface
{
    /** @param array<string, mixed> $analysis */
    public function create(int $runId, string $text, array $analysis): int;

    public function accept(int $candidateId): void;

    public function markManualOccurrence(int $contentId, string $text): int;
}
