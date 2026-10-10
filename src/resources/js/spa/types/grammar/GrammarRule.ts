/**
 * Grammar rule entity as returned by the public API (GrammarRuleResource).
 * Learner-facing shape — no admin-only fields like coverage_state/counts.
 */
export type GrammarRuleExampleKind = 'affirmative' | 'negative' | 'question' | 'mistake';

export interface GrammarRuleExample {
    id: number;
    example: string;
    translation: string | null;
    kind: GrammarRuleExampleKind | null;
    /** For kind = mistake: the typical wrong sentence learners write. */
    mistake: string | null;
    /** The grammar form inside `example`: [start, end) offsets in characters (code points). Null when not marked. */
    target_spans: [number, number][] | null;
    origin: 'admin' | 'ai' | 'content' | 'lesson';
    /** Taken from a video/lesson (real context). */
    from_content: boolean;
}

export type GrammarRuleExampleGenerationStatus = 'idle' | 'queued' | 'running' | 'done' | 'failed';

export interface GrammarRuleExamplesResponse {
    examples: GrammarRuleExample[];
    generation: { status: GrammarRuleExampleGenerationStatus };
}

/** queued/active = examples are on the way; limited = daily batches used up; unavailable = AI off. */
export type GrammarRuleExampleRequestStatus = 'queued' | 'active' | 'limited' | 'unavailable';

export interface GrammarRuleTopic {
    id: number;
    name: string;
}

export interface GrammarRule {
    id: number;
    /** Present on public grammar API responses. */
    editor_version?: number;
    slug: string;
    title: string;
    language: string;
    level: string | null;
    summary: string | null;
    body: string | null;
    is_personal?: boolean;
    source_lesson?: { id: number | null; title: string | null };
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
