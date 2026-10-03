<?php

namespace App\Contracts\Ai;

interface EmbeddingsClientInterface
{
    /**
     * @return array<int, float>
     */
    public function embed(string $text): array;

    /**
     * Embeds several texts in one request instead of $texts one-at-a-time
     * calls to embed() — task 4.9's "cheap fan-out" for
     * CandidateMatchingService::matchRun(), which otherwise made one
     * sequential HTTP round-trip per lexeme/grammar candidate. Preferred
     * over Http::pool()-style client-side concurrency where the provider's
     * API itself accepts a batch of inputs in one call (OpenAI's
     * embeddings endpoint does) — one round-trip beats N concurrent ones,
     * and keeps this interface (not raw Http calls) as the one place a
     * future non-batching provider would need to fall back to sequential
     * embed() calls internally, without CandidateMatchingService knowing
     * the difference. See docs/architecture/agent-framework-roadmap.md,
     * section 12.
     *
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>> Same order as $texts.
     */
    public function embedBatch(array $texts): array;
}
