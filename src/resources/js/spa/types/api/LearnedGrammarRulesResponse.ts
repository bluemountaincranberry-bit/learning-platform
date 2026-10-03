/**
 * One item from GET /api/me/grammar-rules.
 */
export interface LearnedGrammarRuleItem {
    id: number;
    grammar_rule_id: number;
    title: string | null;
    summary: string | null;
    level: string | null;
    topic: { id: number; name: string } | null;
    status: 'learning' | 'learned';
    /** "Practice says X%": from practice rounds and repetition, null before any practice. */
    confidence_calculated: number | null;
    started_at: string;
    learned_at: string | null;
}

export interface LearnedGrammarRulesParams {
    status?: 'learning' | 'learned';
    per_page?: number;
    page?: number;
}

export interface LearnedGrammarRulesResponse {
    data: LearnedGrammarRuleItem[];
    meta: {
        current_page: number;
        per_page: number;
        total: number;
        last_page?: number;
    };
    links?: Record<string, string | null>;
}
