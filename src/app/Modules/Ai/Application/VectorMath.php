<?php

namespace App\Modules\Ai\Application;

/**
 * Task 4.14: generalized `cosineSimilarity()` (and a small `centroid()`
 * helper) extracted from `CandidateMatchingService`, which had its own
 * private copy — this is the one place any embedding-based ranking in the
 * app does vector math, instead of a second implementation drifting from
 * the first. `CandidateMatchingService` and `RecommendationService` both
 * use this now.
 */
final class VectorMath
{
    /**
     * @param  array<int, float>  $a
     * @param  array<int, float>  $b
     */
    public static function cosineSimilarity(array $a, array $b): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        $length = min(count($a), count($b));

        for ($i = 0; $i < $length; $i++) {
            $dot += $a[$i] * $b[$i];
            $normA += $a[$i] ** 2;
            $normB += $b[$i] ** 2;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }

    /**
     * Mean vector across $vectors — used to represent "roughly what this
     * user already knows" as one point instead of comparing a candidate
     * against every learned word individually (`RecommendationService`,
     * task 4.14).
     *
     * @param  array<int, array<int, float>>  $vectors
     * @return array<int, float>
     */
    public static function centroid(array $vectors): array
    {
        if ($vectors === []) {
            return [];
        }

        $dimensions = count($vectors[array_key_first($vectors)]);
        $sums = array_fill(0, $dimensions, 0.0);

        foreach ($vectors as $vector) {
            for ($i = 0; $i < $dimensions; $i++) {
                $sums[$i] += $vector[$i] ?? 0.0;
            }
        }

        $count = count($vectors);

        return array_map(fn (float $sum) => $sum / $count, $sums);
    }
}
