import type { GrammarRule } from './GrammarRule';

/**
 * Response of GET /api/grammar-rules/:id.
 */
export interface GrammarRuleOneResponse {
    rule: GrammarRule;
}
