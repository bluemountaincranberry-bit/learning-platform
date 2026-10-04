import type { LexemeAssociationItem, LexemeExampleItem } from './LexemeWithLearned';

/** A word-level gloss in the viewer's language. */
export interface LexemeTranslationItem {
    translation: string;
    is_primary: boolean;
}

/**
 * Task 10.5: a distinct meaning of the lemma (e.g. "run" = move on foot vs.
 * "run" = manage a business), with its own translations/examples — separate
 * from the flat `translations`/`examples` on LexemeDetail below, which stay
 * sense-less (words that predate this feature, or whose candidate never
 * carried a `sense` gloss).
 */
export interface LexemeSenseItem {
    id: number;
    part_of_speech: string | null;
    gloss: string;
    translations: LexemeTranslationItem[];
    examples: LexemeExampleItem[];
}

/** Task 10.5: a distinct surface form actually seen in content under this lemma (e.g. "ran" for "run") — not a generated conjugation table. */
export interface LexemeFormItem {
    text: string;
    grammar_features: Record<string, string | boolean> | null;
}

/** One saved AI explanation variant with its source content (null = generic, from this page). */
export interface LexemeDetailExplanation {
    id: number;
    explanation: string;
    content: { id: number; title: string } | null;
}

/** Full canonical dictionary entry, as returned by GET /api/dictionary/{id}. */
export interface LexemeDetail {
    id: number;
    slug: string;
    lemma: string;
    language: string;
    part_of_speech: string | null;
    level: string | null;
    translations: LexemeTranslationItem[];
    examples: LexemeExampleItem[];
    senses: LexemeSenseItem[];
    forms: LexemeFormItem[];
    associations: LexemeAssociationItem[];
    /** Saved AI explanations — one per content-context (plus a generic one), each with its source. */
    explanations: LexemeDetailExplanation[];
}

export interface LexemeDetailResponse {
    lexeme: LexemeDetail;
}

/** Response of POST /api/dictionary/{id}/more-examples — fresh, unsaved AI examples. */
export interface MoreExamplesResponse {
    examples: Array<{ example: string; translation: string | null }>;
}
