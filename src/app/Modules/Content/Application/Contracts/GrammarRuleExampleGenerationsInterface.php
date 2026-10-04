<?php

namespace App\Modules\Content\Application\Contracts;

use App\Modules\Content\Application\Data\GrammarRuleExampleGenerationRequest;

/**
 * Queued AI batches of rule examples. At most one active batch per rule;
 * a learner gets a limited number of batches per rule per day. System
 * batches (no user, e.g. the backfill command) are not limited.
 */
interface GrammarRuleExampleGenerationsInterface
{
    public function request(int $ruleId, ?int $userId, int $size, ?string $translationLanguage): GrammarRuleExampleGenerationRequest;

    /** @return 'idle'|'queued'|'running'|'done'|'failed' status of the rule's latest batch */
    public function latestStatus(int $ruleId): string;

    /**
     * Marks a queued batch as running.
     *
     * @return array{rule_id: int, size: int, translation_language: ?string}|null null when the batch is gone or already finished
     */
    public function start(int $generationId): ?array;

    public function finish(int $generationId, int $createdCount): void;

    public function fail(int $generationId, string $error): void;

    /**
     * Rules (not archived) with fewer than $minExamples examples.
     *
     * @param  list<int>  $onlyRuleIds  empty = every rule
     * @return array<int, int> rule id => current example count
     */
    public function rulesBelow(int $minExamples, array $onlyRuleIds = []): array;
}
