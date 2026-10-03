/** One example sentence for an AI lexeme candidate (task 9.9). */
export interface AiSuggestionExample {
    text: string;
    translation: string | null;
    /** 'context' = grounded in this content's actual transcript; 'generated' = a natural sentence the model wrote. */
    source: 'context' | 'generated';
}

/** One pending AI lexeme candidate awaiting the content owner's review (task 9.2). */
export interface AiSuggestionLexemeCandidate {
    id: number;
    text: string;
    type: string;
    level: string | null;
    translation: string | null;
    /** Backward-compat mirror of the primary (context-preferred) example. */
    example: string | null;
    example_translation: string | null;
    /** Full multi-example set (task 9.9), when present. */
    examples: AiSuggestionExample[] | null;
    note: string | null;
    confidence: number | null;
}

/** One pending AI grammar candidate awaiting the content owner's review (task 9.2). */
export interface AiSuggestionGrammarCandidate {
    id: number;
    title: string;
    summary: string | null;
    example: string | null;
    example_translation: string | null;
    note: string | null;
    confidence: number | null;
}

/**
 * Response of GET /api/content/:id/ai-suggestions.
 *
 * Task 9.8: candidates auto-apply with no human confirmation, so in normal
 * operation `lexeme_candidates`/`grammar_candidates` are empty by the time
 * anyone loads this — they're a legacy/fallback surface now (e.g. a run
 * from before 9.8 shipped). `applied_*_count` is the primary signal for
 * the SPA's read-only "AI just added..." notice (task 9.4).
 */
export interface AiSuggestionsResponse {
    run_id: number | null;
    lexeme_candidates: AiSuggestionLexemeCandidate[];
    grammar_candidates: AiSuggestionGrammarCandidate[];
    applied_lexeme_count: number;
    applied_grammar_count: number;
}

/** Payload of POST /api/content/:id/ai-suggestions/accept. */
export interface AcceptAiSuggestionsPayload {
    accept_all?: boolean;
    lexeme_candidate_ids?: number[];
    grammar_candidate_ids?: number[];
}

/** Response of POST /api/content/:id/ai-suggestions/accept. */
export interface AcceptAiSuggestionsResponse {
    applied: { lexemes: number; grammar: number };
}
