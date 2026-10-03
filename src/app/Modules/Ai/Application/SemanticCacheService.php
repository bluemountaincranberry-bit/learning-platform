<?php

namespace App\Modules\Ai\Application;

use App\Modules\Ai\Domain\Models\AiSemanticCacheEntry;
use App\Contracts\Ai\EmbeddingsClientInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Task 5.4: `Cache::remember()`-shaped API, but matched by embedding
 * similarity instead of an exact key — extends the exact-key cache pattern
 * already used by `AiExplainLexemeService` (`Cache::put`/`Cache::get` with a
 * TTL, task keyed on `content_lexeme_id`) to free-text questions, where
 * "the same question" is rarely worded identically twice.
 *
 * `scope` namespaces independent callers sharing the one
 * `ai_semantic_cache_entries` table (currently only `ChatContextAiService`)
 * — matching never crosses scopes.
 *
 * Embeds the query exactly once per `remember()` call (not once for the
 * lookup and again to store a miss) — the vector from the lookup is reused
 * for the write.
 */
class SemanticCacheService
{
    public function __construct(
        private readonly EmbeddingsClientInterface $embeddings,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('ai.semantic_cache.enabled', true);
    }

    /**
     * Returns the cached answer for a question similar enough to `$query`
     * within `$scope`, or computes and caches `$compute()`'s result.
     * `$compute` is only ever invoked on a cache miss (including when the
     * cache is disabled or `$query` is empty).
     *
     * The cache is strictly best-effort: any failure looking up or storing
     * (embeddings API down, DB hiccup, ...) is logged and swallowed rather
     * than propagated — a broken cache must never be able to break the
     * underlying chat reply, which is the whole point of it being a cache
     * and not a required dependency.
     */
    public function remember(string $scope, string $query, callable $compute): string
    {
        $query = trim($query);

        if (! $this->enabled() || $query === '') {
            return $compute();
        }

        $vector = null;

        try {
            $vector = $this->embeddings->embed($query);
            $cached = $this->findSimilar($scope, $vector);

            if ($cached !== null) {
                return $cached;
            }
        } catch (Throwable $e) {
            Log::warning('SemanticCacheService: lookup failed, falling back to a direct call', [
                'scope' => $scope,
                'message' => $e->getMessage(),
            ]);
            $vector = null;
        }

        $answer = $compute();

        if ($vector !== null && trim($answer) !== '') {
            try {
                $this->store($scope, $query, $vector, $answer);
            } catch (Throwable $e) {
                Log::warning('SemanticCacheService: store failed', [
                    'scope' => $scope,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $answer;
    }

    /**
     * @param  array<int, float>  $vector
     */
    private function findSimilar(string $scope, array $vector): ?string
    {
        $threshold = (float) config('ai.semantic_cache.similarity_threshold', 0.92);
        $maxCandidates = (int) config('ai.semantic_cache.max_candidates', 500);

        $candidates = AiSemanticCacheEntry::query()
            ->where('scope', $scope)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', Carbon::now());
            })
            ->orderByDesc('created_at')
            ->limit($maxCandidates)
            ->get(['answer', 'embedding']);

        $best = null;
        $bestScore = 0.0;

        foreach ($candidates as $candidate) {
            $score = VectorMath::cosineSimilarity($vector, $candidate->embedding);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $candidate;
            }
        }

        return ($best !== null && $bestScore >= $threshold) ? $best->answer : null;
    }

    /**
     * @param  array<int, float>  $vector
     */
    private function store(string $scope, string $query, array $vector, string $answer): void
    {
        $ttl = (int) config('ai.semantic_cache.ttl_seconds', 604800);

        AiSemanticCacheEntry::query()->create([
            'scope' => $scope,
            'question' => $query,
            'embedding' => $vector,
            'model_version' => config('ai.embeddings.model', 'text-embedding-3-small'),
            'answer' => $answer,
            'expires_at' => $ttl > 0 ? Carbon::now()->addSeconds($ttl) : null,
        ]);
    }
}
