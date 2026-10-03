<?php

namespace App\Modules\Content\Application;

use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\Content\Domain\Models\LexemeSense;
use Illuminate\Support\Str;

/**
 * Resolves-or-creates a LexemeSense within an already-known Lexeme (task
 * 10.3). Dedup is a cheap exact match on the normalized gloss *within that
 * one lexeme* — not an embedding/similarity search — since the lemma is
 * already disambiguated by the time this runs; two AI candidates for "run"
 * both glossed "move quickly on foot" (any casing/whitespace) resolve to the
 * same sense, while a genuinely different gloss creates a new one.
 */
class LexemeSenseSyncService
{
    /**
     * Returns null when no gloss is given — the caller (occurrence,
     * translation, example) then stays sense-less, exactly like every row
     * created before this feature existed.
     */
    public function sync(Lexeme $lexeme, ?string $gloss, ?string $partOfSpeech = null): ?LexemeSense
    {
        $gloss = trim((string) $gloss);

        if ($gloss === '') {
            return null;
        }

        $normalized = Str::lower($gloss);

        $sense = LexemeSense::query()->firstOrCreate(
            [
                'lexeme_id' => $lexeme->id,
                'normalized_gloss' => $normalized,
            ],
            [
                'gloss' => $gloss,
                'part_of_speech' => $partOfSpeech,
                'sort_order' => 0,
            ]
        );

        // Same "never overwrite curated/already-set data" guard used for
        // Lexeme::level/part_of_speech — a later candidate with no POS info
        // must not blank out one an earlier candidate already supplied.
        if ($sense->part_of_speech === null && $partOfSpeech !== null) {
            $sense->update(['part_of_speech' => $partOfSpeech]);
        }

        return $sense;
    }
}
