<?php

namespace App\Modules\Content\Application;

use App\Contracts\Ai\AiAnalysisRunStatus;
use App\Contracts\VideoTitleFetcherInterface;
use App\Modules\Content\Application\Contracts\ContentLearnerStateReaderInterface;
use App\Modules\Content\Application\Contracts\ContentRepositoryInterface;
use App\Modules\Content\Application\Contracts\ContentReviewScheduleReaderInterface;
use App\Modules\Content\Application\Contracts\ContentServiceInterface;
use App\Modules\Content\Application\Data\ContentLearnerContext;
use App\Modules\Content\Application\Transcript\ImportedYoutubeTranscript;
use App\Modules\Content\Application\Transcript\TranscriptSegmentStore;
use App\Modules\Content\Domain\Events\ContentSubmitted;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentGrammarCandidate;
use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\Content\Domain\Models\ContentLexemeCandidate;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\Content\Domain\Models\LexemeExample;
use App\Modules\Content\Queries\ContentReadinessQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContentService implements ContentServiceInterface
{
    public function __construct(
        private ContentRepositoryInterface $contentRepository,
        private ContentReviewScheduleReaderInterface $reviewSchedule,
        private ContentReadinessQuery $contentReadinessQuery,
        private VideoTitleFetcherInterface $videoTitleFetcher,
        private LexemeLearningSelector $learningSelector,
        private TranscriptSegmentStore $segmentStore,
        private ContentLearnerStateReaderInterface $learnerState,
    ) {}

    /**
     * @param  array{type?: string, language?: string, level?: string, per_page?: int}  $filters
     */
    public function getReadyPaginated(array $filters = []): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);
        $repoFilters = $filters;
        unset($repoFilters['per_page']);

        return $this->contentRepository->getReadyPaginated($repoFilters, $perPage);
    }

    /**
     * @param  array{type?: string, language?: string, level?: string, per_page?: int}  $filters
     */
    public function getReadyPaginatedWithProgress(?ContentLearnerContext $learner, array $filters = []): LengthAwarePaginator
    {
        $paginator = $this->getReadyPaginated($filters);

        if ($learner !== null) {
            $items = $paginator->getCollection();
            $progress = $this->getProgressForContents($items, $learner);
            $readyMap = $this->contentReadinessQuery->readyMapForContents($learner->userId, $items->pluck('id')->all());

            foreach ($items as $content) {
                $p = $progress[$content->id] ?? ['learned_count' => 0, 'in_learning_count' => 0, 'total_lexemes' => 0, 'progress_pct' => 0.0];
                $content->learned_count = $p['learned_count'];
                $content->in_learning_count = $p['in_learning_count'];
                $content->total_lexemes = $p['total_lexemes'];
                $content->progress_pct = $p['progress_pct'];
                $content->ready_to_watch = $readyMap[$content->id] ?? false;
            }

            $paginator->setCollection($items);
        }

        return $paginator;
    }

    public function getLexemesWithLearnedFlags(Content $content, ?ContentLearnerContext $learner): Collection
    {
        $lexemes = $content->lexemes()
            ->with(['canonicalLexeme.examples', 'canonicalLexeme.translations', 'canonicalLexeme.associations.relatedLexeme', 'canonicalLexeme.rules', 'sense'])
            ->orderBy('sort_order')
            ->get();

        $learnerState = $learner !== null
            ? $this->learnerState->lexemeState($learner->userId, $lexemes->pluck('id')->all())
            : ['learned' => [], 'skipped' => [], 'needs_context_review' => [], 'confidence' => []];
        $learnedLexemeIds = collect($learnerState['learned'])->flip();
        $skippedLexemeIds = collect($learnerState['skipped'])->flip();

        // Prioritize-retrying-"Needs work" task: last self-check result per
        // canonical lexeme (see UserLexemeContextCheck's docblock), consumed
        // by useContextPracticeSession's startSession to reorder its queue.
        $needsContextReviewLexemeIds = collect($learnerState['needs_context_review'])->flip();

        $translationLanguage = $learner?->translationLanguage ?? config('ai.analysis.translation_language', 'ru');

        $inReviewLexemeIds = $learner !== null
            ? collect($this->reviewSchedule->lexemeIds($learner->userId))->flip()
            : collect();

        $confidenceByContentLexemeId = collect($learnerState['confidence']);

        $mistakesByContentLexemeId = $learner !== null
            ? DB::table('srs_reviews')->whereIn('content_lexeme_id', $lexemes->pluck('id'))
                ->where('grade', '<=', 2)->select('content_lexeme_id', DB::raw('count(*) as mistakes'))
                ->groupBy('content_lexeme_id')->pluck('mistakes', 'content_lexeme_id')
            : collect();
        $dueContentIds = $learner !== null
            ? collect($this->reviewSchedule->dueContentIds($learner->userId))->flip()
            : collect();

        // Task 9.5: reuses 9.1's persisted uncovered_words list verbatim —
        // the exact transcript words the latest completed analysis run's
        // candidates didn't cover — rather than a separately invented
        // "has no translation/level" heuristic. The tokenizer is no longer
        // exposed as learner-facing rows; this marker remains for legacy
        // occurrences and the persisted coverage report.
        $uncoveredWords = $this->latestUncoveredWords($content);

        return $lexemes->map(function ($lexeme) use ($content, $learner, $learnedLexemeIds, $skippedLexemeIds, $needsContextReviewLexemeIds, $translationLanguage, $inReviewLexemeIds, $uncoveredWords, $confidenceByContentLexemeId, $mistakesByContentLexemeId, $dueContentIds) {
            $canonicalLexeme = $lexeme->canonicalLexeme;
            $primaryExample = $canonicalLexeme !== null
                ? Lexeme::pickPrimaryExample($canonicalLexeme->examples, $content->id)
                : null;
            $primaryTranslation = $canonicalLexeme !== null
                ? Lexeme::pickPrimaryTranslation($canonicalLexeme->translations, $content->id, $translationLanguage)
                : null;
            $examples = $canonicalLexeme !== null
                ? Lexeme::pickExamples($canonicalLexeme->examples, $content->id)
                : collect();
            $associations = $canonicalLexeme !== null
                ? Lexeme::mapAssociations($canonicalLexeme->associations)
                : [];
            $confidence = $confidenceByContentLexemeId->get($lexeme->id);
            $selection = $this->learningSelector->classify($lexeme, $content, $learner?->learningGoal, $learner?->currentLevel, $learnedLexemeIds, [
                'mistakes' => (int) $mistakesByContentLexemeId->get($lexeme->id, 0),
                'confidence' => $confidence,
                'due' => $dueContentIds->has($content->id),
            ]);

            return [
                'id' => $lexeme->id,
                'lexeme_id' => $canonicalLexeme?->id,
                'type' => $lexeme->type,
                'text' => $lexeme->text,
                'sort_order' => $lexeme->sort_order,
                // Null for rows created before this column existed on an
                // install that hasn't backfilled yet — WordListToolbar treats
                // that the same as 'tokenizer' (falls back to "All" rather
                // than hiding words with no confident origin).
                'origin' => $lexeme->origin,
                'level' => $canonicalLexeme?->level,
                'part_of_speech' => $canonicalLexeme?->part_of_speech,
                // Task 10.5: this specific occurrence's grammar tags (e.g.
                // {"tense": "past"} for "ran") and which meaning of the
                // lemma it uses — both null for sense-less/tag-less rows
                // (everything created before task 10.1/10.3, or a candidate
                // the model didn't tag), not an error.
                'grammar_features' => $lexeme->grammar_features,
                'sense_gloss' => $lexeme->sense?->gloss,
                'frequency' => $lexeme->frequency,
                'learning_category' => $selection['category'],
                'learning_score' => $selection['score'],
                'learning_reasons' => $selection['reasons'],
                'confidence' => $confidence,
                'learned' => $canonicalLexeme !== null && $learnedLexemeIds->has($canonicalLexeme->id),
                'skipped' => $canonicalLexeme !== null && $skippedLexemeIds->has($canonicalLexeme->id),
                'translation' => $primaryTranslation?->translation,
                'example' => $primaryExample?->example,
                'examples' => $examples->map(fn (LexemeExample $example): array => [
                    'example' => $example->example,
                    'translation' => $example->translation,
                    'is_primary' => (bool) $example->is_primary,
                ])->values()->all(),
                'associations' => $associations,
                'in_review' => $canonicalLexeme !== null && $inReviewLexemeIds->has($canonicalLexeme->id),
                'needs_context_review' => $canonicalLexeme !== null && $needsContextReviewLexemeIds->has($canonicalLexeme->id),
                'not_analyzed' => $uncoveredWords !== null
                    && $lexeme->type === ContentLexeme::TYPE_WORD
                    && $uncoveredWords->contains(Str::lower($lexeme->text)),
            ];
        });
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>|null Null when the
     *                                                          content has no completed analysis run yet (nothing to compare
     *                                                          against); a set of lowercased words otherwise, possibly empty
     *                                                          (full coverage).
     */
    private function latestUncoveredWords(Content $content): ?Collection
    {
        $run = $content->latestAnalysisRun;

        if ($run === null || $run->status !== AiAnalysisRunStatus::COMPLETED || $run->uncovered_words === null) {
            return null;
        }

        return collect($run->uncovered_words)->map(fn (string $word): string => Str::lower($word));
    }

    /**
     * @param  array{title: string, source_url: string, language: string, level?: string|null}  $data
     */
    public function submitYoutube(array $data, int $userId): Content
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            $title = $this->videoTitleFetcher->fetch($data['source_url']) ?? 'YouTube video';
        }

        $content = $this->contentRepository->create([
            'type' => 'youtube',
            'title' => $title,
            'language' => strtolower($data['language']),
            'level' => $data['level'] ?? null,
            'origin' => 'user-submitted',
            'status' => 'pending',
            'source_url' => $data['source_url'],
            'created_by' => $userId,
        ]);

        ContentSubmitted::dispatch($content->id);

        return $content;
    }

    /** @param array{title?: string|null, source_url: string, language: string, level?: string|null, segments: array<int, array{start_ms: int, end_ms?: int|null, text: string}>} $data */
    public function importYoutubeTranscript(array $data, int $userId): Content
    {
        $segments = array_values(array_filter($data['segments'], static fn (array $segment): bool => trim($segment['text']) !== ''));
        $document = (new ImportedYoutubeTranscript(
            fullText: implode(' ', array_map(static fn (array $segment): string => trim($segment['text']), $segments)),
            segments: $segments,
            language: strtolower($data['language']),
        ))->toDocument();

        $title = trim((string) ($data['title'] ?? '')) ?: 'YouTube video';
        $content = DB::transaction(function () use ($data, $document, $title, $userId): Content {
            $content = $this->contentRepository->create([
                'type' => 'youtube',
                'title' => $title,
                'language' => $document->language,
                'level' => $data['level'] ?? null,
                'origin' => 'user-submitted',
                'status' => 'pending',
                'source_url' => $data['source_url'],
                'source_text' => $document->fullText,
                'created_by' => $userId,
            ]);

            $this->segmentStore->replace($content, $document);

            return $content;
        });

        ContentSubmitted::dispatch($content->id);

        return $content;
    }

    public function getMySubmissions(int $userId): Collection
    {
        $contents = $this->contentRepository->getByUserSubmissions($userId);

        $this->attachPendingAiSuggestionsCounts($contents);

        return $contents;
    }

    /**
     * Task 9.6: "N of your videos have AI suggestions waiting" — a simple
     * count, not a notifications system. Sets a dynamic `pending_ai_
     * suggestions_count` attribute per content (same opt-in pattern
     * ContentResource already uses for learned_count/total_lexemes/
     * progress_pct), computed in two grouped queries regardless of how many
     * contents are in the list, not one query per content.
     *
     * @param  \Illuminate\Support\Collection<int, Content>  $contents
     */
    private function attachPendingAiSuggestionsCounts(Collection $contents): void
    {
        $contents->load('latestAnalysisRun');

        $runIds = $contents->pluck('latestAnalysisRun.id')->filter()->values()->all();

        if ($runIds === []) {
            $contents->each(fn (Content $content) => $content->pending_ai_suggestions_count = 0);

            return;
        }

        $pendingLexemesByRun = ContentLexemeCandidate::query()
            ->whereIn('ai_analysis_run_id', $runIds)
            ->where('status', ContentLexemeCandidate::STATUS_PENDING)
            ->selectRaw('ai_analysis_run_id, count(*) as pending_count')
            ->groupBy('ai_analysis_run_id')
            ->pluck('pending_count', 'ai_analysis_run_id');

        $pendingGrammarByRun = ContentGrammarCandidate::query()
            ->whereIn('ai_analysis_run_id', $runIds)
            ->where('status', ContentGrammarCandidate::STATUS_PENDING)
            ->selectRaw('ai_analysis_run_id, count(*) as pending_count')
            ->groupBy('ai_analysis_run_id')
            ->pluck('pending_count', 'ai_analysis_run_id');

        foreach ($contents as $content) {
            $runId = $content->latestAnalysisRun?->id;
            $content->pending_ai_suggestions_count = $runId === null ? 0 : (int) ($pendingLexemesByRun[$runId] ?? 0) + (int) ($pendingGrammarByRun[$runId] ?? 0);
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Content>  $contents
     * @return array<int, array{learned_count: int, in_learning_count: int, total_lexemes: int, progress_pct: float}>
     */
    public function getProgressForContents(Collection $contents, ?ContentLearnerContext $learner): array
    {
        $contentIds = $contents->pluck('id')->all();
        if ($contentIds === []) {
            return [];
        }

        $totals = ContentLexeme::query()
            ->whereIn('content_id', $contentIds)
            ->selectRaw('content_id, count(*) as total')
            ->groupBy('content_id')
            ->pluck('total', 'content_id')
            ->all();

        $learned = [];
        $inLearning = [];
        if ($learner !== null) {
            $learned = $this->learnerState->learnedCountsByContent($learner->userId, $contentIds);

            $inLearning = DB::table('user_lexeme_sources')
                ->join('srs_cards', function ($join): void {
                    $join->on('srs_cards.user_id', '=', 'user_lexeme_sources.user_id')
                        ->on('srs_cards.lexeme_id', '=', 'user_lexeme_sources.lexeme_id');
                })
                ->join('content_lexemes', 'content_lexemes.id', '=', 'user_lexeme_sources.content_lexeme_id')
                ->where('user_lexeme_sources.user_id', $learner->userId)
                ->where('user_lexeme_sources.source_kind', 'content')
                ->whereNull('srs_cards.deactivated_at')
                ->whereIn('content_lexemes.content_id', $contentIds)
                ->groupBy('content_lexemes.content_id')
                ->selectRaw('content_lexemes.content_id as content_id, count(distinct content_lexemes.id) as in_learning')
                ->pluck('in_learning', 'content_id')
                ->all();
        }

        $result = [];
        foreach ($contentIds as $id) {
            $total = (int) ($totals[$id] ?? 0);
            $learnedCount = (int) ($learned[$id] ?? 0);
            $result[$id] = [
                'learned_count' => $learnedCount,
                'in_learning_count' => (int) ($inLearning[$id] ?? 0),
                'total_lexemes' => $total,
                'progress_pct' => $total > 0 ? round(100.0 * $learnedCount / $total, 1) : 0.0,
            ];
        }

        return $result;
    }

    /**
     * @return array{learned_count: int, in_learning_count: int, total_lexemes: int, progress_pct: float}
     */
    public function getProgressForContent(Content $content, ?ContentLearnerContext $learner): array
    {
        $byContents = $this->getProgressForContents(collect([$content]), $learner);

        return $byContents[$content->id] ?? [
            'learned_count' => 0,
            'in_learning_count' => 0,
            'total_lexemes' => 0,
            'progress_pct' => 0.0,
        ];
    }
}
