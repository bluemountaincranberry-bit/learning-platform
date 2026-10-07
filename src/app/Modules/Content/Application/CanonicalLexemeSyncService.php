<?php

namespace App\Modules\Content\Application;

use App\Contracts\Ai\LexemeEnrichmentDispatcher;
use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\Content\Interfaces\Jobs\SuggestLexemeLevelJob;
use Illuminate\Support\Str;

class CanonicalLexemeSyncService
{
    public function __construct(private readonly LexemeEnrichmentDispatcher $enrichment) {}

    /**
     * Tokenizer/manual-add path: no separate lemma is known yet, so the
     * occurrence's own displayed text is used as the lemma (unchanged
     * behavior — task 10.6 is what teaches the manual-add path to resolve a
     * real lemma before calling this).
     */
    public function sync(ContentLexeme $contentLexeme): Lexeme
    {
        $content = $contentLexeme->content()->first(['id', 'language']);
        $language = strtolower((string) ($content?->language ?? 'en'));

        $lexeme = $this->syncLemma($language, (string) $contentLexeme->text);

        if ($contentLexeme->lexeme_id !== $lexeme->id) {
            $contentLexeme->update(['lexeme_id' => $lexeme->id]);
        }

        return $lexeme;
    }

    /**
     * Resolves-or-creates the canonical Lexeme for an already-known lemma
     * (task 10.1) — used by the AI-candidate path so the correct dictionary
     * form ("run") is looked up/created *before* the occurrence row
     * ("ran") is created, the same way an exact/embedding-matched candidate
     * already pre-resolves `lexeme_id` to skip `ContentLexeme::booted()`'s
     * auto-sync (which would otherwise use the occurrence's own displayed
     * text as the lemma).
     */
    public function syncLemma(string $language, string $lemma, ?string $partOfSpeech = null): Lexeme
    {
        $lemma = trim($lemma);
        $normalized = Str::lower($lemma);

        if ($normalized === '') {
            throw new \InvalidArgumentException('Cannot sync an empty lemma.');
        }

        $lexeme = Lexeme::query()->firstOrCreate(
            [
                'owner_user_id' => null,
                'language' => $language,
                'normalized_lemma' => $normalized,
            ],
            [
                'owner_user_id' => null,
                'slug' => $this->resolveSlug($language, $normalized),
                'lemma' => $lemma,
                'part_of_speech' => $partOfSpeech,
                'status' => Lexeme::STATUS_REVIEW,
                'level' => null,
                'notes' => null,
            ]
        );

        // wasRecentlyCreated guards both jobs below against re-dispatching
        // every time an existing lexeme is looked up — only a genuinely new
        // catalog entry needs either.
        if ($lexeme->wasRecentlyCreated) {
            // A brand-new lexeme never has a level or part_of_speech yet (see
            // the firstOrCreate defaults above) — dispatch the AI suggestion
            // asynchronously (task 7.2 + task 9.7, one job/call for both
            // fields) rather than calling the LLM here, so tokenization
            // (ProcessContentJob, which calls sync() via
            // ContentLexeme::booted()) stays fast.
            if ($lexeme->level === null || $lexeme->part_of_speech === null) {
                SuggestLexemeLevelJob::dispatch($lexeme->id);
            }

            // Task 10.4: "everything related gets added too" — a new word
            // entering the catalog also gets typed related words proposed
            // and auto-persisted, regardless of which path created it.
            $this->enrichment->dispatchFor($lexeme->id);
        }

        return $lexeme;
    }

    private function resolveSlug(string $language, string $normalized): string
    {
        $base = Str::slug($language.'-'.$normalized);

        if ($base === '') {
            $base = $language.'-lexeme-'.Str::random(8);
        }

        // Str::slug() is lossy (e.g. "we're" and "were" both become "en-were"),
        // so two distinct normalized_lemma values can collide on the same base
        // slug. Disambiguate rather than letting the unique constraint fail.
        $slug = $base;
        $suffix = 2;
        while (Lexeme::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
