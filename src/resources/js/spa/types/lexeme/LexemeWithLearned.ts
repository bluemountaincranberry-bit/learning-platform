/** One example sentence for a lexeme, with its translation. */
export interface LexemeExampleItem {
    example: string;
    translation: string | null;
    is_primary: boolean;
}

/** A related word (synonym/antonym/related/collocation) linked from the admin catalog. */
export interface LexemeAssociationItem {
    lemma: string;
    type: string;
}

export interface LexemeConfidence {
    recognition: number;
    recall: number;
    production: number;
    listening: number;
    speaking: number;
}

/**
 * Lexeme with learned flag for current user (content lexemes API).
 */
export interface LexemeWithLearned {
    id: number;
    /** Canonical dictionary entry id (Lexeme), when this word went through AI candidates + Apply. Links to the word detail page. */
    lexeme_id?: number | null;
    type: string;
    text: string;
    sort_order: number;
    /** CEFR level (A1-C2) of the canonical dictionary entry, when known. Null until curated by an admin or suggested by AI (see SuggestLexemeLevelJob). */
    level?: string | null;
    /** Part of speech from the canonical dictionary entry, when known. */
    part_of_speech?: string | null;
    learned: boolean;
    /** True once the learner marked this word "not interested" — hidden from their default word list. */
    skipped?: boolean;
    /** Precomputed translation for this word, when it went through AI candidates + Apply. */
    translation?: string | null;
    /** Example sentence backing the translation, if any. */
    example?: string | null;
    /** Up to a few example sentences: content-scoped first, then globally curated. */
    examples?: LexemeExampleItem[];
    /** Related words (synonym/antonym/related/collocation), grouped by type via groupAssociationsByType(). */
    associations?: LexemeAssociationItem[];
    /** True once this word already has an SrsCard (added to spaced-repetition reviews). */
    in_review?: boolean;
    /** True when the learner's last self-check on this word (quick-check/cloze/listening or context practice) was graded "Needs work" — used by useContextPracticeSession to prioritize retrying it. */
    needs_context_review?: boolean;
    /**
     * Task 9.5: true for a plain tokenizer leftover not covered by the
     * content's latest completed AI analysis run (reuses that run's
     * persisted uncovered_words list, see ContentService::getLexemesWithLearnedFlags()).
     * Always false before any run completes — "not yet analyzed" is
     * meaningless with nothing to compare against.
     */
    not_analyzed?: boolean;
    /**
     * 'ai' — enriched via AiCandidateApplyService (translation/level/examples).
     * 'tokenizer' — ProcessContentJob's bare whole-transcript split, no
     * enrichment. 'manual' — a learner selected this word/phrase directly in
     * the transcript (task 10.6, same enrichment as 'ai' underneath). Null
     * for rows created before this column existed and not yet backfilled.
     * See WordListToolbar's origin filter.
     */
    origin?: 'ai' | 'tokenizer' | 'manual' | null;
    frequency?: number | null;
    learning_category?: 'essential' | 'useful_phrase' | 'grammar_pattern' | 'known' | 'noise' | 'rare' | 'recommended';
    learning_score?: number;
    learning_reasons?: string[];
    confidence?: LexemeConfidence | null;
    /**
     * Task 10.5: this specific occurrence's grammar tags (e.g. {"tense":
     * "past"} for "ran") — a property of the occurrence, not the lemma.
     * Null/undefined when not tagged (tokenizer/manual-before-10.6 rows, or
     * a candidate the model didn't tag).
     */
    grammar_features?: Record<string, string | boolean> | null;
    /** Task 10.5: which meaning of the lemma this occurrence uses, when tagged. Null for sense-less rows. */
    sense_gloss?: string | null;
}
