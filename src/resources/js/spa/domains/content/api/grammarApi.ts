import axios from 'axios';
import type {
    GrammarRuleListParams,
    GrammarRuleListResponse,
    GrammarRuleOneResponse,
    ContentGrammarRulesResponse,
    GrammarRuleExercisesResponse,
    GrammarRuleExamplesResponse,
    GrammarRuleExampleRequestStatus,
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

    getExamples(ruleId: string | number): Promise<GrammarRuleExamplesResponse> {
        return axios.get(`/api/grammar-rules/${ruleId}/examples`).then((r) => r.data);
    },

    /** Queues AI examples. Resolves with the server's status; 429/503 come back as `limited`/`unavailable`, not as errors. */
    generateExamples(ruleId: string | number): Promise<{ status: GrammarRuleExampleRequestStatus }> {
        return axios
            .post(`/api/grammar-rules/${ruleId}/examples/generate`, {}, { validateStatus: (s) => [200, 202, 429, 503].includes(s) })
            .then((r) => (r.status === 429 && !r.data?.status ? { status: 'limited' as const } : r.data));
    },

    hideExample(ruleId: string | number, exampleId: number): Promise<void> {
        return axios.post(`/api/grammar-rules/${ruleId}/examples/${exampleId}/hide`).then(() => undefined);
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
