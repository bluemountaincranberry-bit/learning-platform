<?php

namespace App\Modules\Content\Interfaces\Http\Controllers;

use App\Events\LexemeExplanationRequested;
use App\Exceptions\AiClientException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\BulkLexemeIdsRequest;
use App\Contracts\Ai\ContextSentenceGenerationCapability;
use App\Contracts\Ai\LexemeEnrichmentCapability;
use App\Contracts\Ai\LexemeExplanationCapability;
use App\Modules\Content\Application\Contracts\LexemeServiceInterface;
use App\Modules\Content\Domain\Models\ClozeExample;
use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\Content\Domain\Models\LexemeExample;
use App\Modules\Content\Domain\Models\LexemeExplanation;
use App\Modules\Content\Domain\Models\LexemeSense;
use App\Modules\Content\Domain\Models\LexemeTranslation;
use App\Support\AiConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LexemeController extends Controller
{
    public function __construct(
        private LexemeServiceInterface $lexemeService,
        private LexemeExplanationCapability $explanationCapability,
        private ContextSentenceGenerationCapability $sentenceCapability,
        private LexemeEnrichmentCapability $enrichmentService
    ) {}

    /**
     * Public dictionary page for a single canonical word: all its examples,
     * translations in the viewer's language, and related words grouped by
     * association type. Distinct from the ContentLexeme-bound routes above
     * ({lexeme} there means "word instance inside a piece of content"; here
     * {word} means the canonical dictionary entry).
     */
    public function show(Request $request, Lexeme $word): JsonResponse
    {
        $word->load(['translations', 'examples', 'associations.relatedLexeme', 'senses.translations', 'senses.examples', 'contentLinks', 'explanations']);

        $translationLanguage = $request->user()?->translation_language ?? config('ai.analysis.translation_language', 'ru');

        $translations = $word->translations
            ->where('language', $translationLanguage)
            ->sortByDesc('is_primary')
            ->values();

        return response()->json([
            'lexeme' => [
                'id' => $word->id,
                'slug' => $word->slug,
                'lemma' => $word->lemma,
                'language' => $word->language,
                'part_of_speech' => $word->part_of_speech,
                'level' => $word->level,
                'translations' => $translations->map(fn (LexemeTranslation $translation): array => [
                    'translation' => $translation->translation,
                    'is_primary' => (bool) $translation->is_primary,
                ])->values()->all(),
                'examples' => $word->examples->sortBy('sort_order')->map(fn (LexemeExample $example): array => [
                    'example' => $example->example,
                    'translation' => $example->translation,
                    'is_primary' => (bool) $example->is_primary,
                ])->values()->all(),
                // Task 10.3: distinct meanings, each with its own translations/
                // examples — alongside the flat arrays above (sense-less rows,
                // kept for backward compatibility with words that predate this
                // feature or whose candidates never carried a `sense` gloss).
                'senses' => $word->senses->map(fn (LexemeSense $sense): array => [
                    'id' => $sense->id,
                    'part_of_speech' => $sense->part_of_speech,
                    'gloss' => $sense->gloss,
                    'translations' => $sense->translations
                        ->where('language', $translationLanguage)
                        ->sortByDesc('is_primary')
                        ->map(fn (LexemeTranslation $translation): array => [
                            'translation' => $translation->translation,
                            'is_primary' => (bool) $translation->is_primary,
                        ])->values()->all(),
                    'examples' => $sense->examples->sortBy('sort_order')->map(fn (LexemeExample $example): array => [
                        'example' => $example->example,
                        'translation' => $example->translation,
                        'is_primary' => (bool) $example->is_primary,
                    ])->values()->all(),
                ])->values()->all(),
                // Task 10.5: distinct surface forms actually seen in content
                // under this lemma (e.g. "ran"/"running" for "run") — only
                // what was observed, not a generated conjugation table.
                'forms' => $word->contentLinks
                    ->unique(fn (ContentLexeme $occurrence): string => mb_strtolower($occurrence->text))
                    ->map(fn (ContentLexeme $occurrence): array => [
                        'text' => $occurrence->text,
                        'grammar_features' => $occurrence->grammar_features,
                    ])->values()->all(),
                'associations' => Lexeme::mapAssociations($word->associations, 50),
                // Durable counterpart of the cached per-content explanation:
                // generated once from any content (or this page), shown here.
                'explanation' => $word->explanations->firstWhere('language', $word->language)?->explanation,
            ],
        ]);
    }

    /**
     * On-demand extra example sentences for a canonical word, for the
     * learner's own study — not the admin's Filament "AI: Enrich" flow.
     * Read-only: LexemeEnrichmentCapability::propose() only calls AI, it never
     * writes to the catalog, so this never needs an accept/apply step.
     */
    public function moreExamples(Request $request, Lexeme $word): JsonResponse
    {
        if (! AiConfig::isEnabled()) {
            return response()->json(['message' => 'AI feature is disabled.'], 503);
        }

        try {
            $proposal = $this->enrichmentService->propose($word->id);
        } catch (AiClientException $e) {
            return response()->json(['message' => 'AI service unavailable.'], 503);
        }

        return response()->json(['examples' => $proposal['examples']]);
    }

    public function markLearned(Request $request, ContentLexeme $lexeme): JsonResponse
    {
        $this->lexemeService->markLearned($lexeme, $request->user()->id);

        return response()->json(['ok' => true]);
    }

    public function unmarkLearned(Request $request, ContentLexeme $lexeme): JsonResponse
    {
        $this->lexemeService->unmarkLearned($lexeme, $request->user()->id);

        return response()->json(['ok' => true]);
    }

    public function startLearning(Request $request, ContentLexeme $lexeme): JsonResponse
    {
        $this->lexemeService->startLearning($lexeme, $request->user()->id);

        return response()->json(['ok' => true]);
    }

    public function stopLearning(Request $request, ContentLexeme $lexeme): JsonResponse
    {
        $this->lexemeService->stopLearning($lexeme, $request->user()->id);

        return response()->json(['ok' => true]);
    }

    public function skip(Request $request, ContentLexeme $lexeme): JsonResponse
    {
        $this->lexemeService->skip($lexeme, $request->user()->id);

        return response()->json(['ok' => true]);
    }

    public function unskip(Request $request, ContentLexeme $lexeme): JsonResponse
    {
        $this->lexemeService->unskip($lexeme, $request->user()->id);

        return response()->json(['ok' => true]);
    }

    /**
     * Bulk counterpart of markLearned() (task 7.5) — same service call,
     * looped over a batch of ContentLexeme ids instead of one route-bound
     * model. Per-id try/catch so one failing id can't lose the rest of the
     * batch; see bulkApply().
     */
    public function bulkMarkLearned(BulkLexemeIdsRequest $request): JsonResponse
    {
        return response()->json(
            $this->bulkApply(
                $request->validated('ids'),
                $request->user()->id,
                fn (ContentLexeme $lexeme, int $userId) => $this->lexemeService->markLearned($lexeme, $userId)
            )
        );
    }

    /**
     * Bulk counterpart of startLearning() (task 7.5).
     */
    public function bulkStartLearning(BulkLexemeIdsRequest $request): JsonResponse
    {
        return response()->json(
            $this->bulkApply(
                $request->validated('ids'),
                $request->user()->id,
                fn (ContentLexeme $lexeme, int $userId) => $this->lexemeService->startLearning($lexeme, $userId)
            )
        );
    }

    /**
     * Applies $action to each ContentLexeme id independently — a failure on
     * one id (e.g. a transient error inside the service) is caught and
     * reported per-id, it never aborts or rolls back the rest of the batch.
     * Response shape lets the SPA show "8 of 10 marked, 2 failed" instead
     * of an all-or-nothing result.
     *
     * @param  array<int, int>  $ids
     * @return array{results: array<int, array{id: int, ok: bool, message?: string}>, succeeded: int, failed: int}
     */
    private function bulkApply(array $ids, int $userId, callable $action): array
    {
        $results = [];

        foreach ($ids as $id) {
            try {
                $lexeme = ContentLexeme::query()->find($id);
                if ($lexeme === null) {
                    $results[] = ['id' => $id, 'ok' => false, 'message' => 'Not found.'];

                    continue;
                }

                $action($lexeme, $userId);
                $results[] = ['id' => $id, 'ok' => true];
            } catch (\Throwable $e) {
                Log::warning('Bulk lexeme action failed for one id', [
                    'lexeme_id' => $id,
                    'message' => $e->getMessage(),
                ]);
                $results[] = ['id' => $id, 'ok' => false, 'message' => 'Failed to process.'];
            }
        }

        $succeeded = count(array_filter($results, fn (array $r) => $r['ok']));

        return [
            'results' => $results,
            'succeeded' => $succeeded,
            'failed' => count($results) - $succeeded,
        ];
    }

    public function explain(Request $request, ContentLexeme $lexeme): JsonResponse
    {
        if (! AiConfig::isEnabled()) {
            return response()->json(['message' => 'AI feature is disabled.'], 503);
        }

        $this->authorize('view', $lexeme->content);

        $language = $lexeme->content->language ?? null;

        // Durable read-through: an explanation generated earlier (from any
        // content, or the word page) is reused without spending AI budget.
        $stored = $lexeme->lexeme_id !== null
            ? LexemeExplanation::query()
                ->where('lexeme_id', $lexeme->lexeme_id)
                ->where('language', $language ?? '')
                ->first()
            : null;
        if ($stored !== null) {
            LexemeExplanationRequested::dispatch($request->user()->id, $lexeme->id, 'study_screen');

            return response()->json(['explanation' => $stored->explanation]);
        }

        try {
            $explanation = $this->explanationCapability->explain($lexeme->text, $language, $lexeme->id);
        } catch (AiClientException $e) {
            return response()->json(['message' => 'AI service unavailable.'], 503);
        }

        if ($lexeme->lexeme_id !== null) {
            LexemeExplanation::updateOrCreate(
                ['lexeme_id' => $lexeme->lexeme_id, 'language' => $language ?? ''],
                ['explanation' => $explanation],
            );
        }

        LexemeExplanationRequested::dispatch(
            $request->user()->id,
            $lexeme->id,
            'study_screen'
        );

        return response()->json(['explanation' => $explanation]);
    }

    /**
     * Explain a canonical dictionary word straight from its own page — same
     * durable store as the per-content explain() above, so either side
     * reuses what the other generated.
     */
    public function explainWord(Request $request, Lexeme $word): JsonResponse
    {
        if (! AiConfig::isEnabled()) {
            return response()->json(['message' => 'AI feature is disabled.'], 503);
        }

        $language = $word->language ?? '';

        $stored = LexemeExplanation::query()
            ->where('lexeme_id', $word->id)
            ->where('language', $language)
            ->first();
        if ($stored !== null) {
            LexemeExplanationRequested::dispatch($request->user()->id, $word->id, 'word_page');

            return response()->json(['explanation' => $stored->explanation]);
        }

        try {
            $explanation = $this->explanationCapability->explain($word->lemma, $word->language);
        } catch (AiClientException $e) {
            return response()->json(['message' => 'AI service unavailable.'], 503);
        }

        LexemeExplanation::updateOrCreate(
            ['lexeme_id' => $word->id, 'language' => $language],
            ['explanation' => $explanation],
        );

        LexemeExplanationRequested::dispatch($request->user()->id, $word->id, 'word_page');

        return response()->json(['explanation' => $explanation]);
    }

    /**
     * On-demand example sentence for context practice (ContextSentenceCard's
     * "Generate a new sentence") — used when a word has no usable stored
     * example, or the learner wants a different one; the reused-examples
     * flow (useContextPracticeSession's default) stays the default path.
     * Same synchronous single-call shape as explain() above, and shares its
     * 'explain' rate-limit bucket rather than adding a new one (see routes).
     */
    public function generateSentence(Request $request, ContentLexeme $lexeme): JsonResponse
    {
        if (! AiConfig::isEnabled()) {
            return response()->json(['message' => 'AI feature is disabled.'], 503);
        }

        $this->authorize('view', $lexeme->content);

        $targetLanguage = $lexeme->content->language ?? 'en';
        $nativeLanguage = $request->user()->translation_language ?? config('ai.analysis.translation_language', 'ru');

        if (! $request->boolean('force')) {
            $cached = ClozeExample::query()
                ->where('content_lexeme_id', $lexeme->id)
                ->where('target_language', $targetLanguage)
                ->where('native_language', $nativeLanguage)
                ->orderBy('used_count')
                ->orderBy('last_used_at')
                ->get()
                ->first(fn (ClozeExample $example): bool => count($example->distractors ?? []) === 3);
            if ($cached !== null) {
                $cached->increment('used_count');
                $cached->update(['last_used_at' => now()]);

                return response()->json([
                    'sentence' => $cached->sentence,
                    'target_form' => $cached->target_form,
                    'translation' => $cached->translation,
                    'distractors' => $cached->distractors ?? [],
                    'cached' => true,
                ]);
            }
        }

        try {
            $result = $this->sentenceCapability->generate(
                $lexeme->text,
                $targetLanguage,
                $nativeLanguage,
                $lexeme->content->title ?? null
            );
        } catch (AiClientException $e) {
            return response()->json(['message' => 'AI service unavailable.'], 503);
        }

        ClozeExample::query()->create([
            'content_lexeme_id' => $lexeme->id,
            'sentence' => $result['sentence'],
            'target_form' => $result['target_form'],
            'translation' => $result['translation'],
            'distractors' => $result['distractors'],
            'target_language' => $targetLanguage,
            'native_language' => $nativeLanguage,
        ]);

        return response()->json($result);
    }

    public function prepareClozeExamples(Request $request): JsonResponse
    {
        if (! AiConfig::isEnabled()) {
            return response()->json(['message' => 'AI feature is disabled.'], 503);
        }

        $validated = $request->validate([
            'content_lexeme_ids' => ['required', 'array', 'min:1', 'max:10'],
            'content_lexeme_ids.*' => ['integer', 'distinct', 'min:1'],
            'variants' => ['sometimes', 'integer', 'min:1', 'max:3'],
        ]);
        $variants = (int) ($validated['variants'] ?? 3);
        $translationLanguage = $request->user()->translation_language ?? config('ai.analysis.translation_language', 'ru');
        $lexemes = ContentLexeme::query()->with('content')->whereIn('id', $validated['content_lexeme_ids'])->get();
        $prepared = 0;

        foreach ($lexemes as $lexeme) {
            $this->authorize('view', $lexeme->content);
            $targetLanguage = $lexeme->content->language ?? 'en';
            $existing = ClozeExample::query()
                ->where('content_lexeme_id', $lexeme->id)
                ->where('target_language', $targetLanguage)
                ->where('native_language', $translationLanguage)
                ->count();

            for ($variant = $existing; $variant < $variants; $variant++) {
                try {
                    $result = $this->sentenceCapability->generate(
                        $lexeme->text,
                        $targetLanguage,
                        $translationLanguage,
                        $lexeme->content->title ?? null,
                    );
                } catch (AiClientException) {
                    break;
                }

                ClozeExample::query()->create([
                    'content_lexeme_id' => $lexeme->id,
                    'sentence' => $result['sentence'],
                    'target_form' => $result['target_form'],
                    'translation' => $result['translation'],
                    'distractors' => $result['distractors'],
                    'target_language' => $targetLanguage,
                    'native_language' => $translationLanguage,
                ]);
                $prepared++;
            }
        }

        return response()->json(['prepared' => $prepared, 'total' => $lexemes->count()]);
    }
}
