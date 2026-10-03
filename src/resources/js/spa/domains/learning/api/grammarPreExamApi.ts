import axios from 'axios';

export type GrammarPreExamType = 'pre' | 'post';

export interface GrammarPreExamCard {
    prompt_sentence: string;
    prompt_language: string;
    answer_language: string;
    hint_words: string[];
    grammar_rule_id: number;
}

export interface GrammarPreExamResultItem {
    grammar_rule_id: number;
    prompt_sentence: string;
    prompt_language: string;
    answer_language: string;
    answer: string;
    correct: boolean;
    model_answer: string;
    hint_words: string[];
}

export interface GrammarExamAttempt {
    id: number;
    grammar_rule_id: number;
    content_id: number | null;
    type: GrammarPreExamType;
    total_cards: number;
    correct_count: number;
    score_pct: number;
    items: GrammarPreExamResultItem[];
    completed_at: string;
}

export interface GrammarPreExamAttemptGroup {
    grammar_rule_id: number;
    attempt: GrammarExamAttempt;
    confidence_calculated: number | null;
}

/**
 * The grammar warm-up: diagnostic (`pre`) or recap (`post`) practice scoped
 * to whichever of a content's own grammar rules the learner picks — unlike
 * contentReadinessApi's gated "Ready to watch" exam, available any time and
 * scored per topic rather than as one aggregate.
 */
export const grammarPreExamApi = {
    start(contentId: number, grammarRuleIds: number[], count?: number): Promise<{ cards: GrammarPreExamCard[] }> {
        return axios
            .post(`/api/content/${contentId}/grammar-warmup/start`, { grammar_rule_ids: grammarRuleIds, count })
            .then((r) => r.data);
    },

    complete(contentId: number, type: GrammarPreExamType, results: GrammarPreExamResultItem[]): Promise<{ attempts: GrammarPreExamAttemptGroup[] }> {
        return axios.post(`/api/content/${contentId}/grammar-warmup/complete`, { type, results }).then((r) => r.data);
    },
};
