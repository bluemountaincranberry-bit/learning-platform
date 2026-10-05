<?php

namespace App\Modules\Ai\Application;

use App\Contracts\Ai\EmbeddingsClientInterface;
use App\Modules\Ai\Domain\Models\AiAnalysisRun;
use App\Modules\Ai\Domain\Models\CanonicalLexemeEmbedding;
use App\Modules\Ai\Domain\Models\GrammarRuleEmbedding;
use App\Modules\Content\Application\Contracts\CandidateMatchStoreInterface;
use App\Modules\Content\Application\Contracts\ContentAnalysisSourceReaderInterface;
use App\Modules\Content\Domain\Models\GrammarRule;
use Illuminate\Support\Collection;

class CandidateMatchingService
{
    public function __construct(
        private readonly EmbeddingsClientInterface $embeddings,
        ?CandidateMatchStoreInterface $candidateStore = null,
        ?ContentAnalysisSourceReaderInterface $contentSources = null,
    ) {
        $this->candidateStore = $candidateStore ?? app(CandidateMatchStoreInterface::class);
        $this->contentSources = $contentSources ?? app(ContentAnalysisSourceReaderInterface::class);
    }

    private readonly CandidateMatchStoreInterface $candidateStore;

    private readonly ContentAnalysisSourceReaderInterface $contentSources;

    /**
     * Task 4.9: batches every candidate that needs an embedding-fallback
     * match into one `embedBatch()` call each for lexemes and grammar,
     * instead of one `embed()` HTTP round-trip per candidate
     * (`foreach ($run->lexemeCandidates as $candidate) { ...embed()... }`
     * previously) — see `EmbeddingsClientInterface::embedBatch()`'s
     * docblock for why a single batched request was chosen over
     * `Http::pool()`-style client-side concurrency here.
     */
    public function matchRun(AiAnalysisRun $run): void
    {
        $language = $this->contentSources->get((int) $run->content_id)?->language ?? 'en';

        $this->matchLexemeCandidates($this->candidateStore->lexemeCandidates((int) $run->id), $language);
        $this->matchGrammarCandidates($this->candidateStore->grammarCandidates((int) $run->id), $language);
    }

    /**
     * @param  array<int, array{id:int, normalized_lemma:?string, normalized_text:string, lemma:?string, text:string}>  $candidates
     */
    private function matchLexemeCandidates(array $candidates, string $language): void
    {
        $needsEmbedding = [];

        foreach ($candidates as $candidate) {
            // Match on the lemma, not the raw occurrence text — "ran" and
            // "run" must resolve to the same canonical Lexeme (task 10.1).
            // normalized_lemma falls back to normalized_text for candidates
            // created before this column existed.
            $normalizedLemma = $candidate['normalized_lemma'] ?? $candidate['normalized_text'];
            $exact = $this->exactLexemeMatch($normalizedLemma, $language);

            if ($exact !== null) {
                $this->candidateStore->updateLexemeMatch($candidate['id'], $exact, 1.0);

                continue;
            }

            $needsEmbedding[] = $candidate;
        }

        if ($needsEmbedding === []) {
            return;
        }

        $rows = $this->canonicalLexemeEmbeddings($language);

        if ($rows->isEmpty()) {
            foreach ($needsEmbedding as $candidate) {
                $this->candidateStore->updateLexemeMatch($candidate['id'], null, null);
            }

            return;
        }

        $vectors = $this->embeddings->embedBatch(array_map(fn (array $candidate) => $candidate['lemma'] ?? $candidate['text'], $needsEmbedding));
        $threshold = (float) config('ai.analysis.match_threshold', 0.85);

        foreach ($needsEmbedding as $i => $candidate) {
            [$bestId, $bestScore] = $this->bestMatch($vectors[$i] ?? [], $rows, fn ($row) => $row->lexeme_id);

            $this->candidateStore->updateLexemeMatch(
                $candidate['id'],
                $bestScore >= $threshold ? $bestId : null,
                round($bestScore, 3),
            );
        }
    }

    /**
     * VIK-16: an exact title match (same language, case/spacing ignored)
     * wins before any embedding call. The title+summary embedding alone
     * missed same-titled rules whenever the AI wrote a new summary
     * ("Passive Voice" scored 0.758 against "Passive Voice"), and matched
     * nothing at all for rules whose embedding was not computed yet.
     *
     * @param  array<int, array{id:int, title:string, summary:?string}>  $candidates
     */
    private function matchGrammarCandidates(array $candidates, string $language): void
    {
        $needsEmbedding = [];

        foreach ($candidates as $candidate) {
            $exact = $this->candidateStore->exactGrammarRuleId($candidate['title'], $language);

            if ($exact !== null) {
                $this->candidateStore->updateGrammarMatch($candidate['id'], $exact, 1.0);

                continue;
            }

            $needsEmbedding[] = $candidate;
        }

        if ($needsEmbedding === []) {
            return;
        }

        $modelVersion = config('ai.embeddings.model', 'text-embedding-3-small');
        $rows = GrammarRuleEmbedding::query()
            ->where('model_version', $modelVersion)
            ->whereHas('grammarRule', fn ($query) => $query->whereNull('owner_user_id')->where('status', '!=', GrammarRule::STATUS_ARCHIVED))
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $vectors = $this->embeddings->embedBatch(
            array_map(fn (array $candidate) => trim($candidate['title'].' '.$candidate['summary']), $needsEmbedding)
        );
        $threshold = (float) config('ai.analysis.match_threshold', 0.85);

        foreach ($needsEmbedding as $i => $candidate) {
            [$bestId, $bestScore] = $this->bestMatch($vectors[$i] ?? [], $rows, fn ($row) => $row->grammar_rule_id);

            $this->candidateStore->updateGrammarMatch(
                $candidate['id'],
                $bestScore >= $threshold ? $bestId : null,
                round($bestScore, 3),
            );
        }
    }

    private function exactLexemeMatch(string $normalizedText, string $language): ?int
    {
        return $this->candidateStore->exactLexemeId($normalizedText, $language);
    }

    /**
     * @return Collection<int, CanonicalLexemeEmbedding>
     */
    private function canonicalLexemeEmbeddings(string $language): Collection
    {
        $modelVersion = config('ai.embeddings.model', 'text-embedding-3-small');

        return CanonicalLexemeEmbedding::query()
            ->where('model_version', $modelVersion)
            ->whereIn('lexeme_id', $this->candidateStore->lexemeIdsForLanguage($language))
            ->get();
    }

    /**
     * Shared matching core: exact normalized-lemma match first, falling back
     * to embedding cosine-similarity against canonical lexemes in the same
     * language. Used both by LexemeEnrichmentService for AI-proposed
     * synonyms (a single lemma at a time — batching doesn't apply there)
     * and, historically, by the content-analysis candidate pipeline above;
     * `matchLexemeCandidates()` now batches its embed calls instead of
     * calling this per-candidate, but keeps the exact same exact-match ->
     * cosine-similarity -> threshold logic via `exactLexemeMatch()`/
     * `bestMatch()`.
     *
     * @return array{lexeme_id: ?int, score: ?float}
     */
    public function findBestLexemeMatch(string $normalizedText, string $text, string $language): array
    {
        $exact = $this->exactLexemeMatch($normalizedText, $language);

        if ($exact) {
            return ['lexeme_id' => $exact, 'score' => 1.0];
        }

        $rows = $this->canonicalLexemeEmbeddings($language);

        if ($rows->isEmpty()) {
            return ['lexeme_id' => null, 'score' => null];
        }

        $vector = $this->embeddings->embed($text);
        [$bestLexemeId, $bestScore] = $this->bestMatch($vector, $rows, fn ($row) => $row->lexeme_id);

        $threshold = (float) config('ai.analysis.match_threshold', 0.85);

        return [
            'lexeme_id' => $bestScore >= $threshold ? $bestLexemeId : null,
            'score' => round($bestScore, 3),
        ];
    }

    /**
     * Generic single-item counterpart of `matchGrammarCandidates()`, the
     * same relationship `findBestLexemeMatch()` has to
     * `matchLexemeCandidates()` — lets a caller with no `AiAnalysisRun` of
     * its own (LessonCandidateMatchingService) reuse the exact same
     * embedding-cosine-vs-threshold logic instead of re-fetching
     * `GrammarRuleEmbedding` rows and duplicating `bestMatch()`.
     *
     * @return array{grammar_rule_id: ?int, score: ?float}
     */
    public function findBestGrammarMatch(string $title, string $summary, string $language): array
    {
        $exact = $this->candidateStore->exactGrammarRuleId($title, $language);

        if ($exact !== null) {
            return ['grammar_rule_id' => $exact, 'score' => 1.0];
        }

        $modelVersion = config('ai.embeddings.model', 'text-embedding-3-small');
        $rows = GrammarRuleEmbedding::query()
            ->where('model_version', $modelVersion)
            ->whereHas('grammarRule', fn ($query) => $query->whereNull('owner_user_id')->where('status', '!=', GrammarRule::STATUS_ARCHIVED))
            ->get();

        if ($rows->isEmpty()) {
            return ['grammar_rule_id' => null, 'score' => null];
        }

        $vector = $this->embeddings->embed(trim($title.' '.$summary));
        [$bestGrammarRuleId, $bestScore] = $this->bestMatch($vector, $rows, fn ($row) => $row->grammar_rule_id);

        $threshold = (float) config('ai.analysis.match_threshold', 0.85);

        return [
            'grammar_rule_id' => $bestScore >= $threshold ? $bestGrammarRuleId : null,
            'score' => round($bestScore, 3),
        ];
    }

    /**
     * @param  array<int, float>  $vector
     * @param  iterable<int, mixed>  $rows
     * @param  \Closure(mixed): ?int  $idOf
     * @return array{0: ?int, 1: float}
     */
    private function bestMatch(array $vector, iterable $rows, \Closure $idOf): array
    {
        $bestId = null;
        $bestScore = -1.0;

        foreach ($rows as $row) {
            $score = VectorMath::cosineSimilarity($vector, $row->embedding);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestId = $idOf($row);
            }
        }

        return [$bestId, $bestScore];
    }
}
