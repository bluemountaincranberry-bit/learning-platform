import type { GrammarRule } from './GrammarRule';

/**
 * Response of GET /api/content/:id/grammar-rules.
 */
export interface ContentGrammarRulesResponse {
    rules: GrammarRule[];
}
