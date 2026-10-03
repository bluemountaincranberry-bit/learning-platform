<?php

namespace App\Modules\Content\Application\Transcript;

use App\Exceptions\AiClientException;
use App\Contracts\Ai\LexemeTranslationCapability;
use App\Contracts\Ai\ManualLexemeCandidateCapability;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\Content\Domain\Models\LexemeTranslation;
use App\Modules\Content\Domain\Models\TranscriptSegment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Backs the "select any word or phrase" transcript interaction: every word
 * (or, task 10.6, multi-word selection) in a transcript segment is
 * selectable, not just the ones the async AI candidate pipeline already
 * matched to a ContentLexeme. A selection with no existing ContentLexeme for
 * this content (case-insensitive, so re-selecting the same word doesn't
 * create duplicates) goes through the exact same enrichment pipeline as a
 * batch-extracted candidate — lemma, part of speech, sense, translation,
 * example, level, grammar features, and (via CanonicalLexemeSyncService)
 * typed related-word auto-enrichment for a brand-new lemma — rather than a
 * separate, thinner path that used to produce only a bare translation.
 */
class TranscriptLexemeLookupService
{
    public function __construct(
        private readonly ManualLexemeCandidateCapability $manualCandidates,
        private readonly LexemeTranslationCapability $translationCapability,
    ) {}

    /**
     * @return array{content_lexeme_id: int, text: string, translation: string}
     *
     * @throws AiClientException
     */
    public function lookup(Content $content, TranscriptSegment $segment, string $text, int $startOffset, int $endOffset, string $nativeLanguage): array
    {
        $text = trim($text);
        if ($text === '') {
            throw new \InvalidArgumentException('Cannot look up an empty word.');
        }

        $contentLexeme = ContentLexeme::query()
            ->where('content_id', $content->id)
            ->whereRaw('LOWER(text) = ?', [Str::lower($text)])
            ->first();

        if ($contentLexeme === null) {
            $contentLexeme = $this->createEnrichedOccurrence($content, $segment, $text);
        }

        $segment->lexemes()->syncWithoutDetaching([
            $contentLexeme->id => [
                'start_offset' => $startOffset,
                'end_offset' => $endOffset,
                'surface_text' => $text,
                'match_type' => 'manual',
                'confidence' => 1,
            ],
        ]);

        $translation = $this->resolveTranslation($content, $contentLexeme, $nativeLanguage);

        return [
            'content_lexeme_id' => $contentLexeme->id,
            'text' => $contentLexeme->text,
            'translation' => $translation,
        ];
    }

    /**
     * Task 10.6: routes a brand-new manual selection through the exact same
     * candidate application path a batch-extracted candidate uses. Builds
     * one throwaway analysis run carrying a single accepted candidate, matches it against the
     * existing catalog (so a word already known under this exact lemma
     * links to it instead of duplicating), then applies it — no confidence
     * gate: the learner's own selection is the confirmation a batch
     * candidate would otherwise need AiCandidateAutoApplyService for.
     *
     * @throws AiClientException
     */
    private function createEnrichedOccurrence(Content $content, TranscriptSegment $segment, string $text): ContentLexeme
    {
        $sourceLanguage = $content->language ?? 'en';
        $translationLanguage = config('ai.analysis.translation_language', 'ru');
        $occurrenceId = $this->manualCandidates->analyzeAndApply($content->id, $text, $segment->text, $sourceLanguage, $translationLanguage);

        return ContentLexeme::query()->findOrFail($occurrenceId);
    }

    /**
     * @throws AiClientException
     */
    private function resolveTranslation(Content $content, ContentLexeme $contentLexeme, string $nativeLanguage): string
    {
        $contentLexeme->loadMissing('canonicalLexeme.translations');
        $canonicalLexeme = $contentLexeme->canonicalLexeme;

        $existing = $canonicalLexeme !== null
            ? Lexeme::pickPrimaryTranslation($canonicalLexeme->translations, $content->id, $nativeLanguage)
            : null;
        if ($existing !== null) {
            return $existing->translation;
        }

        $translation = $this->translationCapability->translate($contentLexeme->text, $content->language ?? 'en', $nativeLanguage);

        if ($canonicalLexeme !== null && $translation !== '') {
            DB::transaction(function () use ($canonicalLexeme, $nativeLanguage, $translation): void {
                LexemeTranslation::query()->create([
                    'lexeme_id' => $canonicalLexeme->id,
                    'content_id' => null,
                    'language' => $nativeLanguage,
                    'translation' => $translation,
                    'is_primary' => true,
                ]);
            });
        }

        return $translation;
    }
}
