<?php

namespace App\Modules\Ai\Application;

use App\Modules\Ai\Application\Data\RecommendationLearner;
use App\Modules\Ai\Domain\Models\CanonicalLexemeEmbedding;
use App\Modules\Content\Application\Contracts\ContentServiceInterface;
use App\Modules\Content\Application\Contracts\RecommendationCatalogInterface;
use App\Modules\Content\Application\Data\ContentLearnerContext;
use App\Modules\Srs\Application\Contracts\ReviewScheduleReaderInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Task 4.14: ranks by similarity to the user's already-learned vocabulary
 * (embeddings, via `VectorMath` — the same math `CandidateMatchingService`
 * uses, generalized in this task) plus a bonus for content/words tied to
 * spaced-repetition cards that are currently overdue, instead of
 * `inRandomOrder()`.
 *
 * Both signals degrade gracefully to the previous behavior when there's
 * nothing to rank by yet — a brand new user with no learned words (no
 * centroid) or a catalog with no computed embeddings gets `$score = 0` for
 * everything, i.e. whatever order the DB pool query returned, same as
 * before. This mirrors how RAG/embeddings are treated everywhere else in
 * the app (an enhancement layered on top of a working baseline, never a
 * hard requirement) — see `RagRetrievalService`'s docblock for the same
 * pattern.
 *
 * Ranking happens in PHP over a bounded candidate pool (not a full
 * DB-side vector ranking query) — deliberately simple: there is no
 * production traffic yet to justify a smarter/streaming ranking query
 * (engineering-principles.md: don't carry complexity ahead of a real
 * need). If the catalog grows large enough for this to matter, the next
 * step is Elasticsearch kNN (already used for RAG) over a
 * recommendations-specific index, not a bigger PHP sort.
 */
class RecommendationService
{
    private const POOL_MULTIPLIER = 5;

    private const POOL_MINIMUM = 50;

    private const STALENESS_BONUS = 1.0;

    /**
     * Full bonus for a content whose curated CEFR level exactly matches the
     * user's self-reported `current_level`; halves per level of distance,
     * zero from two levels away. Same "degrade to 0 when data is missing"
     * shape as the embedding/staleness signals above — most content still
     * has no `level` and most users haven't set `current_level` yet, so
     * this must never become a hard filter.
     */
    private const LEVEL_MATCH_BONUS = 0.5;

    private const CEFR_LEVELS = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];

    public function __construct(
        private ContentServiceInterface $contentService,
        private RecommendationCatalogInterface $catalog,
        private ReviewScheduleReaderInterface $reviewSchedule,
    ) {}

    /**
     * @return Collection<int, object>
     */
    public function getRecommendedContents(RecommendationLearner $learner, int $limit = 10): Collection
    {
        $pool = $this->catalog->contentCandidates($learner->userId, $learner->uiLanguage, max($limit * self::POOL_MULTIPLIER, self::POOL_MINIMUM));

        $centroid = VectorMath::centroid($this->learnedEmbeddings($learner->userId));
        $similarityByContent = $this->unlearnedLexemeSimilarityByContent($pool, $learner->userId, $centroid);
        $overdueContentIds = collect($this->reviewSchedule->overdueContentIds($learner->userId));

        $contents = $pool
            ->map(function ($content) use ($similarityByContent, $overdueContentIds, $learner) {
                $reasons = [];
                $content->recommendation_score = ($similarityByContent[$content->id] ?? 0.0)
                    + ($overdueContentIds->contains($content->id) ? self::STALENESS_BONUS : 0.0)
                    + $this->levelMatchScore($content->level, $learner->currentLevel);
                if (($similarityByContent[$content->id] ?? 0.0) > 0) {
                    $reasons[] = 'similar_to_known_vocabulary';
                }
                if ($overdueContentIds->contains($content->id)) {
                    $reasons[] = 'has_due_reviews';
                }
                if ($content->level !== null && $content->level === $learner->currentLevel) {
                    $reasons[] = 'matches_level';
                }
                if ($learner->learningGoal === 'conversation' && in_array($content->type, ['youtube', 'movie', 'song'], true)) {
                    $reasons[] = 'supports_conversation_goal';
                }
                $content->recommendation_reasons = $reasons !== [] ? $reasons : ['new_learning_opportunity'];

                return $content;
            })
            ->sortByDesc('recommendation_score')
            ->values()
            ->take($limit);

        $progress = $this->contentService->getProgressForContents(
            $contents,
            new ContentLearnerContext(
                userId: $learner->userId,
                translationLanguage: $learner->translationLanguage,
                learningGoal: $learner->learningGoal,
                currentLevel: $learner->currentLevel,
            ),
        );
        foreach ($contents as $content) {
            $p = $progress[$content->id] ?? ['learned_count' => 0, 'total_lexemes' => 0, 'progress_pct' => 0.0];
            $content->learned_count = $p['learned_count'];
            $content->total_lexemes = $p['total_lexemes'];
            $content->progress_pct = $p['progress_pct'];
        }

        return $contents;
    }

    /**
     * @return Collection<int, object>
     */
    public function getRecommendedLexemes(RecommendationLearner $learner, int $limit = 20): Collection
    {
        $pool = $this->catalog->lexemeCandidates($learner->userId, $learner->uiLanguage, max($limit * self::POOL_MULTIPLIER, self::POOL_MINIMUM));

        $centroid = VectorMath::centroid($this->learnedEmbeddings($learner->userId));
        $embeddingsByLexemeId = $this->canonicalEmbeddingsFor($pool->pluck('lexeme_id')->filter()->unique()->all());
        $overdueContentIds = collect($this->reviewSchedule->overdueContentIds($learner->userId));

        return $pool
            ->map(function ($lexeme) use ($centroid, $embeddingsByLexemeId, $overdueContentIds, $learner) {
                $similarity = ($centroid !== [] && $lexeme->lexeme_id !== null && isset($embeddingsByLexemeId[$lexeme->lexeme_id]))
                    ? VectorMath::cosineSimilarity($centroid, $embeddingsByLexemeId[$lexeme->lexeme_id])
                    : 0.0;

                $lexeme->recommendation_score = $similarity
                    + ($overdueContentIds->contains($lexeme->content_id) ? self::STALENESS_BONUS : 0.0);
                $lexeme->recommendation_reasons = $overdueContentIds->contains($lexeme->content_id)
                    ? ['has_due_reviews']
                    : ['similarity_or_new_content'];
                if ($learner->learningGoal === 'conversation' && $lexeme->type === 'phrase') {
                    $lexeme->recommendation_score += 0.25;
                    $lexeme->recommendation_reasons[] = 'matches_conversation_goal';
                }

                return $lexeme;
            })
            ->sortByDesc('recommendation_score')
            ->values()
            ->take($limit);
    }

    /**
     * How well a content's curated CEFR level fits the user's self-reported
     * one: full bonus on an exact match, tapering to 0 two levels away.
     * Either side missing (most content and, so far, every user) yields 0 —
     * no penalty, just no signal, same as the embedding centroid above.
     */
    private function levelMatchScore(?string $contentLevel, ?string $userLevel): float
    {
        if ($contentLevel === null || $userLevel === null) {
            return 0.0;
        }

        $contentIndex = array_search($contentLevel, self::CEFR_LEVELS, true);
        $userIndex = array_search($userLevel, self::CEFR_LEVELS, true);

        if ($contentIndex === false || $userIndex === false) {
            return 0.0;
        }

        $distance = abs($contentIndex - $userIndex);

        return max(0.0, 1.0 - $distance / 2) * self::LEVEL_MATCH_BONUS;
    }

    /**
     * The user's learned lexemes' canonical embeddings — the basis for
     * `VectorMath::centroid()`, representing "roughly what this user
     * already knows".
     *
     * @return array<int, array<int, float>>
     */
    private function learnedEmbeddings(int $userId): array
    {
        $learnedLexemeIds = DB::table('user_lexeme_progress')
            ->where('user_id', $userId)
            ->whereNotNull('lexeme_id')
            ->pluck('lexeme_id')
            ->unique()
            ->all();

        if ($learnedLexemeIds === []) {
            return [];
        }

        return array_values($this->canonicalEmbeddingsFor($learnedLexemeIds));
    }

    /**
     * @param  array<int, int>  $lexemeIds
     * @return array<int, array<int, float>> Keyed by lexeme_id.
     */
    private function canonicalEmbeddingsFor(array $lexemeIds): array
    {
        if ($lexemeIds === []) {
            return [];
        }

        $modelVersion = config('ai.embeddings.model', 'text-embedding-3-small');

        return CanonicalLexemeEmbedding::query()
            ->whereIn('lexeme_id', $lexemeIds)
            ->where('model_version', $modelVersion)
            ->get()
            ->keyBy('lexeme_id')
            ->map(fn (CanonicalLexemeEmbedding $row) => $row->embedding)
            ->all();
    }

    /**
     * Average similarity, to the learned-word centroid, of each pooled
     * content's own not-yet-learned lexemes — one batched query instead of
     * one per content.
     *
     * @param  Collection<int, object>  $pool
     * @param  array<int, float>  $centroid
     * @return array<int, float> Keyed by content_id.
     */
    private function unlearnedLexemeSimilarityByContent(Collection $pool, int $userId, array $centroid): array
    {
        if ($centroid === [] || $pool->isEmpty()) {
            return [];
        }

        $modelVersion = config('ai.embeddings.model', 'text-embedding-3-small');

        $rows = DB::table('content_lexemes')
            ->join('canonical_lexeme_embeddings', function ($join) use ($modelVersion): void {
                $join->on('canonical_lexeme_embeddings.lexeme_id', '=', 'content_lexemes.lexeme_id')
                    ->where('canonical_lexeme_embeddings.model_version', $modelVersion);
            })
            ->whereIn('content_lexemes.content_id', $pool->pluck('id'))
            ->whereNotExists(function ($subquery) use ($userId): void {
                $subquery->selectRaw('1')
                    ->from('user_lexeme_progress')
                    ->whereColumn('user_lexeme_progress.lexeme_id', 'content_lexemes.lexeme_id')
                    ->where('user_lexeme_progress.user_id', $userId);
            })
            ->select('content_lexemes.content_id', 'canonical_lexeme_embeddings.embedding')
            ->get();

        $scores = [];
        foreach ($rows->groupBy('content_id') as $contentId => $group) {
            $similarities = $group->map(function ($row) use ($centroid) {
                $embedding = is_string($row->embedding) ? json_decode($row->embedding, true) : $row->embedding;

                return VectorMath::cosineSimilarity($centroid, is_array($embedding) ? $embedding : []);
            });

            $scores[$contentId] = (float) $similarities->avg();
        }

        return $scores;
    }

    /**
     * content_ids the user has at least one currently-overdue
     * spaced-repetition card for — the "review staleness" signal.
     *
     * @return Collection<int, int>
     */
}
