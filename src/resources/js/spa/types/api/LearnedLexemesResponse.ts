import type { LexemeAssociationItem, LexemeExampleItem } from '../lexeme';

/**
 * One item from GET /api/me/learned-lexemes.
 */
export interface LearnedLexemeItem {
    id: number;
    content_lexeme_id: number;
    /** Canonical dictionary entry id (Lexeme), when this word went through AI candidates + Apply. Links to the word detail page. */
    lexeme_id?: number | null;
    lexeme: string;
    learned_at: string;
    content_id: number;
    content_title: string | null;
    language: string | null;
    level: string | null;
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
}

export interface LearnedLexemesParams {
    language?: string;
    content_id?: number;
    date_from?: string;
    date_to?: string;
    per_page?: number;
    page?: number;
}

export interface LearnedLexemesResponse {
    data: LearnedLexemeItem[];
    meta: {
        current_page: number;
        per_page: number;
        total: number;
        last_page?: number;
    };
    links?: Record<string, string | null>;
}
