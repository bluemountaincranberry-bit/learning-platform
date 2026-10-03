import type { GrammarRule } from './GrammarRule';

/**
 * Response of GET /api/grammar-rules (Laravel paginated collection).
 */
export interface GrammarRuleListResponse {
    data: GrammarRule[];
    links?: Record<string, unknown>;
    meta?: Record<string, unknown>;
}
