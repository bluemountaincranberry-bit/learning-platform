import axios from 'axios';
import type {
    GrammarRuleListParams,
    GrammarRuleListResponse,
    GrammarRuleOneResponse,
    ContentGrammarRulesResponse,
    GrammarRuleExercisesResponse,
} from '../../../types';

export const grammarApi = {
    getList(params: GrammarRuleListParams = {}): Promise<GrammarRuleListResponse> {
        return axios.get('/api/grammar-rules', { params }).then((r) => r.data);
    },

    getOne(id: string | number): Promise<GrammarRuleOneResponse> {
        return axios.get(`/api/grammar-rules/${id}`).then((r) => r.data);
    },

    getForContent(contentId: string | number): Promise<ContentGrammarRulesResponse> {
        return axios.get(`/api/content/${contentId}/grammar-rules`).then((r) => r.data);
    },

    getExercises(ruleId: string | number): Promise<GrammarRuleExercisesResponse> {
        return axios.get(`/api/grammar-rules/${ruleId}/exercises`).then((r) => r.data);
    },

    startLearning(ruleId: string | number): Promise<{ ok: true }> {
        return axios.post(`/api/grammar-rules/${ruleId}/start-learning`).then((r) => r.data);
    },

    markLearned(ruleId: string | number): Promise<{ ok: true }> {
        return axios.post(`/api/grammar-rules/${ruleId}/mark-learned`).then((r) => r.data);
    },

    unmarkLearned(ruleId: string | number): Promise<{ ok: true }> {
        return axios.post(`/api/grammar-rules/${ruleId}/unmark-learned`).then((r) => r.data);
    },

    /** confidence: 0-100, or null to clear a previous self-rating. */
    setConfidence(ruleId: string | number, confidence: number | null): Promise<{ ok: true }> {
        return axios.post(`/api/grammar-rules/${ruleId}/confidence`, { confidence }).then((r) => r.data);
    },
};
