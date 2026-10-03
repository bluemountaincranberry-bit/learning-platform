<?php

namespace App\Modules\Learning\Application\GrammarPractice;

use App\Modules\Content\Application\Data\GrammarExerciseHistory;
use App\Modules\Content\Application\Data\GrammarPracticeExercise;

/**
 * Picks the exercises for one practice round (docs/product/grammar-exercises-block.md):
 * an even share per type of the level, a short type topped up from the next
 * type at the same level, and inside a type: never seen → missed last time →
 * seen longest ago. The round is ordered easy → hard and never repeats an
 * exercise; it is shorter than asked when the pool is short.
 */
final class GrammarRoundComposer
{
    private const MISSED = 'answer_shown';

    /**
     * @param  list<GrammarPracticeExercise>  $pool
     * @param  array<int, GrammarExerciseHistory>  $history  keyed by exercise id
     * @param  list<int>  $exclude
     * @return list<GrammarPracticeExercise>
     */
    public function compose(array $pool, array $history, GrammarPracticeLevel $level, int $count, array $exclude = []): array
    {
        $types = $level->types();
        $byType = array_fill_keys($types, []);
        foreach ($pool as $exercise) {
            if (isset($byType[$exercise->type]) && ! in_array($exercise->id, $exclude, true)) {
                $byType[$exercise->type][] = $exercise;
            }
        }
        foreach ($byType as $type => $exercises) {
            $byType[$type] = $this->ranked($exercises, $history);
        }

        $picked = array_fill_keys($types, []);
        $quotas = $this->quotas($types, $count);
        $shortfall = 0;
        foreach ($types as $type) {
            $picked[$type] = array_splice($byType[$type], 0, $quotas[$type]);
            $shortfall += $quotas[$type] - count($picked[$type]);
        }

        foreach ($types as $type) {
            if ($shortfall === 0) {
                break;
            }
            $extra = array_splice($byType[$type], 0, $shortfall);
            $picked[$type] = [...$picked[$type], ...$extra];
            $shortfall -= count($extra);
        }

        return array_merge(...array_values($picked));
    }

    /**
     * @param  list<string>  $types
     * @return array<string, int>
     */
    private function quotas(array $types, int $count): array
    {
        $base = intdiv($count, count($types));
        $remainder = $count % count($types);
        $quotas = [];
        foreach ($types as $index => $type) {
            $quotas[$type] = $base + ($index < $remainder ? 1 : 0);
        }

        return $quotas;
    }

    /**
     * @param  list<GrammarPracticeExercise>  $exercises
     * @param  array<int, GrammarExerciseHistory>  $history
     * @return list<GrammarPracticeExercise>
     */
    private function ranked(array $exercises, array $history): array
    {
        usort($exercises, function (GrammarPracticeExercise $a, GrammarPracticeExercise $b) use ($history): int {
            return $this->rankKey($a, $history) <=> $this->rankKey($b, $history);
        });

        return $exercises;
    }

    /**
     * @param  array<int, GrammarExerciseHistory>  $history
     * @return array{int, int, int}
     */
    private function rankKey(GrammarPracticeExercise $exercise, array $history): array
    {
        $seen = $history[$exercise->id] ?? null;

        return match (true) {
            $seen === null => [0, 0, $exercise->id],
            $seen->lastOutcome === self::MISSED => [1, $seen->lastSeenAt->getTimestamp(), $exercise->id],
            default => [2, $seen->lastSeenAt->getTimestamp(), $exercise->id],
        };
    }
}
