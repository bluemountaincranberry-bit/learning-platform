<?php

namespace App\Modules\Content\Application;

use App\Modules\Content\Application\Contracts\AcceptedCandidateWriterInterface;
use App\Modules\Content\Application\Contracts\GrammarCatalogServiceInterface;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentGrammarCandidate;
use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\Content\Domain\Models\ContentLexemeCandidate;
use App\Modules\Content\Domain\Models\ContentRuleLink;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarRuleExample;
use App\Modules\Content\Domain\Models\GrammarTopic;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\Content\Domain\Models\LexemeExample;
use App\Modules\Content\Domain\Models\LexemeTranslation;
use Illuminate\Support\Facades\DB;

class AcceptedCandidateWriter implements AcceptedCandidateWriterInterface
{
    public function __construct(
        private readonly GrammarCatalogServiceInterface $grammarCatalogService,
        private readonly CanonicalLexemeSyncService $canonicalLexemeSyncService,
        private readonly LexemeSenseSyncService $lexemeSenseSyncService,
    ) {}

    public function apply(int $runId, int $contentId, string $translationLanguage, \Closure $onGrammarRuleCreated): array
    {
        return DB::transaction(function () use ($runId, $contentId, $translationLanguage, $onGrammarRuleCreated): array {
            $content = Content::query()->findOrFail($contentId);
            $lexemeCandidates = ContentLexemeCandidate::query()
                ->where('ai_analysis_run_id', $runId)
                ->where('status', ContentLexemeCandidate::STATUS_ACCEPTED)
                ->get();

            foreach ($lexemeCandidates as $candidate) {
                $this->applyLexemeCandidate($content, $candidate, $translationLanguage);
            }

            $grammarCandidates = ContentGrammarCandidate::query()
                ->where('ai_analysis_run_id', $runId)
                ->where('status', ContentGrammarCandidate::STATUS_ACCEPTED)
                ->get();

            foreach ($grammarCandidates as $candidate) {
                $this->applyGrammarCandidate($content, $candidate, $translationLanguage, $onGrammarRuleCreated);
            }

            return [
                'lexemes' => $lexemeCandidates->count(),
                'grammar' => $grammarCandidates->count(),
            ];
        });
    }

    private function applyLexemeCandidate(Content $content, ContentLexemeCandidate $candidate, string $translationLanguage): void
    {
        $language = $content->language ?? 'en';

        if ($candidate->matched_lexeme_id !== null) {
            $lexeme = Lexeme::query()->findOrFail($candidate->matched_lexeme_id);
        } else {
            // No match: resolve/create the canonical lexeme from the
            // candidate's own lemma *before* creating the occurrence row
            // (task 10.1) — "ran" must not become the lemma of its own
            // Lexeme just because it's also the occurrence's displayed text.
            $lexeme = $this->canonicalLexemeSyncService
                ->syncLemma($language, $candidate->lemma ?? $candidate->text, $candidate->part_of_speech);
        }
        $lexemeId = $lexeme->id;

        // Task 10.3: resolves-or-creates the specific meaning this occurrence
        // uses, within the already-disambiguated lexeme above. Null when the
        // candidate carries no `sense` gloss — the occurrence/translation/
        // example then stay sense-less, same as before this feature existed.
        $senseId = $this->lexemeSenseSyncService->sync($lexeme, $candidate->sense, $candidate->part_of_speech)?->id;

        // Create the occurrence with lexeme_id already set — ContentLexeme::
        // booted() skips its auto-sync hook when lexeme_id is provided, so
        // this can't create a duplicate/incorrectly-lemmatized Lexeme via
        // that hook's own (lemma-unaware) sync().
        $content->lexemes()->create([
            'type' => $candidate->type,
            'text' => $candidate->text,
            'sort_order' => 0,
            'frequency' => $candidate->frequency,
            'lexeme_id' => $lexemeId,
            'lexeme_sense_id' => $senseId,
            'grammar_features' => $candidate->grammar_features,
            'origin' => ContentLexeme::ORIGIN_AI,
        ]);

        // Backfill level/part_of_speech opportunistically — never overwrite a
        // value an admin (or an earlier Apply) already curated. Covers the
        // matched-lexeme branch too (syncLemma() above only sets
        // part_of_speech at creation, for a brand-new lexeme).
        if ($candidate->level !== null) {
            Lexeme::query()->whereKey($lexemeId)->whereNull('level')->update(['level' => $candidate->level]);
        }
        if ($candidate->part_of_speech !== null) {
            Lexeme::query()->whereKey($lexemeId)->whereNull('part_of_speech')->update(['part_of_speech' => $candidate->part_of_speech]);
        }

        $this->attachLexemeExample($lexemeId, $senseId, $content->id, $language, $translationLanguage, $candidate);
        $this->attachLexemeTranslation($lexemeId, $senseId, $content->id, $translationLanguage, $candidate);

        $candidate->update(['status' => ContentLexemeCandidate::STATUS_APPLIED]);
    }

    /**
     * Stores the candidate's example sentence(s) (and their translations) as
     * content-scoped (`content_id` set) — evidence sourced from this specific
     * content, not a vetted general-purpose fact about the lexeme. Globally
     * curated examples (`content_id` null) are left to admin editing via
     * `GrammarCatalogService`.
     *
     * Task 9.9: a candidate can now carry 2-3 examples (`examples`, JSON —
     * see AiContentAnalysisService), each tagged `source: 'context'|
     * 'generated'`. One `LexemeExample` row is created per entry; the
     * context-sourced one (grounded in this content's actual transcript) is
     * preferred as `is_primary` when the lexeme (or, task 10.3, the specific
     * sense) doesn't already have a primary example. Falls back to the
     * legacy singular example/example_translation columns for any candidate
     * created before this column existed.
     *
     * `$senseId` scopes the "already has a primary" check and the created
     * rows to one meaning of the lexeme — null keeps the pre-10.3 behavior
     * (scoped to the lexeme's own sense-less examples) so an unrelated
     * sense's primary doesn't block this one from getting its own.
     */
    private function attachLexemeExample(int $lexemeId, ?int $senseId, int $contentId, string $language, string $translationLanguage, ContentLexemeCandidate $candidate): void
    {
        $examples = $this->resolveExamples($candidate);

        if ($examples === []) {
            return;
        }

        $hasPrimary = LexemeExample::query()
            ->where('lexeme_id', $lexemeId)
            ->when($senseId !== null, fn ($q) => $q->where('lexeme_sense_id', $senseId), fn ($q) => $q->whereNull('lexeme_sense_id'))
            ->where('is_primary', true)
            ->exists();

        $primaryIndex = $hasPrimary ? null : $this->primaryExampleIndex($examples);

        foreach ($examples as $index => $example) {
            LexemeExample::query()->create([
                'lexeme_id' => $lexemeId,
                'lexeme_sense_id' => $senseId,
                'content_id' => $contentId,
                'language' => $language,
                'example' => $example['text'] !== '' ? $example['text'] : $candidate->text,
                'translation' => $example['translation'],
                'translation_language' => $example['translation'] !== null ? $translationLanguage : null,
                'is_primary' => $primaryIndex === $index,
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * @return array<int, array{text: string, translation: ?string, source: string}>
     */
    private function resolveExamples(ContentLexemeCandidate $candidate): array
    {
        if (is_array($candidate->examples) && $candidate->examples !== []) {
            return $candidate->examples;
        }

        if (blank($candidate->example) && blank($candidate->example_translation)) {
            return [];
        }

        return [[
            'text' => (string) $candidate->example,
            'translation' => $candidate->example_translation,
            'source' => 'context',
        ]];
    }

    /**
     * @param  array<int, array{text: string, translation: ?string, source: string}>  $examples
     */
    private function primaryExampleIndex(array $examples): int
    {
        foreach ($examples as $index => $example) {
            if (($example['source'] ?? null) === 'context') {
                return $index;
            }
        }

        return 0;
    }

    /**
     * Word/phrase gloss — distinct from the example's translation above.
     * Separate table because a lexeme can have a gloss per target language,
     * and it shouldn't be duplicated across every example row.
     *
     * `$senseId` (task 10.3) scopes which meaning this gloss belongs to —
     * null keeps the pre-10.3 behavior of one gloss per (lexeme, content,
     * language), now specifically among sense-less rows.
     */
    private function attachLexemeTranslation(int $lexemeId, ?int $senseId, int $contentId, string $translationLanguage, ContentLexemeCandidate $candidate): void
    {
        if (blank($candidate->translation)) {
            return;
        }

        $scope = fn () => LexemeTranslation::query()
            ->where('lexeme_id', $lexemeId)
            ->where('content_id', $contentId)
            ->where('language', $translationLanguage)
            ->when($senseId !== null, fn ($q) => $q->where('lexeme_sense_id', $senseId), fn ($q) => $q->whereNull('lexeme_sense_id'));

        $scope()->update(['is_primary' => false]);

        $translation = $scope()->latest('id')->first();

        if ($translation !== null) {
            $translation->update([
                'translation' => trim((string) $candidate->translation),
                'is_primary' => true,
                'sort_order' => 0,
            ]);

            return;
        }

        LexemeTranslation::query()->create([
            'lexeme_id' => $lexemeId,
            'lexeme_sense_id' => $senseId,
            'content_id' => $contentId,
            'language' => $translationLanguage,
            'translation' => trim((string) $candidate->translation),
            'is_primary' => true,
            'sort_order' => 0,
        ]);
    }

    private function applyGrammarCandidate(Content $content, ContentGrammarCandidate $candidate, string $translationLanguage, \Closure $onGrammarRuleCreated): void
    {
        $ruleId = $candidate->matched_grammar_rule_id;

        if ($ruleId === null) {
            $language = $content->language ?? 'en';

            // AI-proposed grammar has no topic of its own; new rules land in a
            // shared "AI Suggested" bucket per language, published immediately
            // (same no-human-confirmation policy as lexemes, task 9.8) — an
            // admin can still re-file it under a curated topic later without
            // that blocking learner visibility in the meantime.
            $topic = GrammarTopic::query()->firstOrCreate(
                ['slug' => 'ai-suggested-'.$language],
                [
                    'language' => $language,
                    'name' => 'AI Suggested',
                    'status' => GrammarTopic::STATUS_ACTIVE,
                ]
            );

            $rule = $this->grammarCatalogService->createRule([
                'topic_id' => $topic->id,
                'title' => $candidate->title,
                'language' => $language,
                'status' => GrammarRule::STATUS_PUBLISHED,
                'summary' => $candidate->summary,
                'body' => $candidate->body,
            ]);

            $ruleId = $rule->id;

            // Without an embedding, this rule can never be matched by a later
            // candidate (CandidateMatchingService only compares against rows
            // in grammar_rule_embeddings) — every future video would create
            // its own duplicate instead of linking here. Dispatched inline
            // rather than left to the periodic embeddings command so the very
            // next analysis run downstream can already match against it.
            $onGrammarRuleCreated($ruleId);
        }

        ContentRuleLink::query()->updateOrCreate(
            [
                'content_id' => $content->id,
                'grammar_rule_id' => $ruleId,
            ],
            ['status' => 'linked']
        );

        $this->attachGrammarRuleExample($ruleId, $content->id, $content->language ?? 'en', $translationLanguage, $candidate);

        $candidate->update(['status' => ContentGrammarCandidate::STATUS_APPLIED]);
    }

    /**
     * Same content-scoping rationale as attachLexemeExample() above.
     */
    private function attachGrammarRuleExample(int $ruleId, int $contentId, string $language, string $translationLanguage, ContentGrammarCandidate $candidate): void
    {
        if (blank($candidate->example)) {
            return;
        }

        $hasPrimary = GrammarRuleExample::query()
            ->where('grammar_rule_id', $ruleId)
            ->where('is_primary', true)
            ->exists();

        GrammarRuleExample::query()->create([
            'grammar_rule_id' => $ruleId,
            'content_id' => $contentId,
            'language' => $language,
            'example' => $candidate->example,
            'translation' => $candidate->example_translation,
            'translation_language' => $candidate->example_translation !== null ? $translationLanguage : null,
            'is_primary' => ! $hasPrimary,
            'sort_order' => 0,
        ]);
    }
}
