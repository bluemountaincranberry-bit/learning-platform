<?php

namespace App\Modules\Learning\Application\GrammarPractice;

use Illuminate\Contracts\Cache\Repository;

/**
 * What the server has seen of each exercise in the learner's current round:
 * wrong tries so far and, once settled, the outcome and the answer given.
 * The outcome saved at the end of a round comes from here, not from the
 * client, so a shown answer can't be resubmitted as "first try" and a
 * repeated "attempt 1" can't buy more hints. Short-lived round state lives
 * in the cache (ADR-005: Redis is fast state, the attempt row is the record).
 */
final class GrammarPracticeLedger
{
    private const TTL_SECONDS = 6 * 3600;

    public function __construct(private readonly Repository $cache) {}

    /** @return array{wrong: int, outcome: ?GrammarPracticeOutcome, given: ?string} */
    public function get(int $userId, int $exerciseId): array
    {
        $state = $this->cache->get($this->key($userId, $exerciseId));

        return [
            'wrong' => (int) ($state['wrong'] ?? 0),
            'outcome' => isset($state['outcome']) ? GrammarPracticeOutcome::tryFrom($state['outcome']) : null,
            'given' => $state['given'] ?? null,
        ];
    }

    public function recordWrong(int $userId, int $exerciseId): void
    {
        $state = $this->get($userId, $exerciseId);
        $this->put($userId, $exerciseId, $state['wrong'] + 1, null, null);
    }

    public function settle(int $userId, int $exerciseId, GrammarPracticeOutcome $outcome, ?string $given): void
    {
        $state = $this->get($userId, $exerciseId);
        $this->put($userId, $exerciseId, $state['wrong'], $outcome, $given);
    }

    /** @param  list<int>  $exerciseIds */
    public function forget(int $userId, array $exerciseIds): void
    {
        foreach ($exerciseIds as $exerciseId) {
            $this->cache->forget($this->key($userId, $exerciseId));
        }
    }

    private function put(int $userId, int $exerciseId, int $wrong, ?GrammarPracticeOutcome $outcome, ?string $given): void
    {
        $this->cache->put($this->key($userId, $exerciseId), [
            'wrong' => $wrong,
            'outcome' => $outcome?->value,
            'given' => $given,
        ], self::TTL_SECONDS);
    }

    private function key(int $userId, int $exerciseId): string
    {
        return "grammar-practice:{$userId}:{$exerciseId}";
    }
}
