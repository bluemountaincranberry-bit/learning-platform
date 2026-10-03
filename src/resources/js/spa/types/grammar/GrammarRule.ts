/**
 * Grammar rule entity as returned by the public API (GrammarRuleResource).
 * Learner-facing shape — no admin-only fields like coverage_state/counts.
 */
export interface GrammarRuleExample {
    example: string;
    translation: string | null;
}

export interface GrammarRuleTopic {
    id: number;
    name: string;
}

export interface GrammarRule {
    id: number;
    slug: string;
    title: string;
    language: string;
    level: string | null;
    summary: string | null;
    body: string | null;
    topic?: GrammarRuleTopic;
    examples?: GrammarRuleExample[];
    /** Present only when the request is authenticated. */
    in_my_list?: boolean;
    /** Present only when the request is authenticated. */
    learned?: boolean;
    /** Present only when the request is authenticated. The learner's own self-rating (0-100), null if never set. */
    confidence_manual?: number | null;
    /** Present only when the request is authenticated. Derived by GrammarConfidenceService from practice, null until there's any signal. */
    confidence_calculated?: number | null;
}
