<?php

namespace App\Modules\Ai\Application\Data;

/**
 * Result of `GraphDefinitionTestRunService::testRun()` — a real run of a
 * draft graph version against admin-supplied test input, executed inside
 * a DB transaction that is always rolled back afterward (see that
 * service's docblock), so `lexemeCandidates`/`grammarCandidates` here are
 * a snapshot read before rollback, not rows that still exist anywhere.
 */
final readonly class GraphTestRunResult
{
    /**
     * @param  array<int, array<string, mixed>>  $lexemeCandidates
     * @param  array<int, array<string, mixed>>  $grammarCandidates
     */
    public function __construct(
        public string $status,
        public ?string $currentNode,
        public ?string $pauseReason,
        public ?string $failureReason,
        public array $lexemeCandidates,
        public array $grammarCandidates,
    ) {}
}
