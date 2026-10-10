import axios from 'axios';
import type {
    GrammarRuleListParams,
    GrammarRuleListResponse,
    GrammarRuleOneResponse,
    ContentGrammarRulesResponse,
    GrammarRuleExamplesResponse,
    GrammarRuleExampleRequestStatus,
} from '../../../types';
import type {
    GrammarRuleEditorDraft,
    GrammarRuleEditorRevision,
    GrammarRuleEditorRule,
    GrammarRuleEditorTurn,
} from '../../../types/grammar/GrammarRuleEditor';

export const grammarApi = {
    getList(params: GrammarRuleListParams = {}): Promise<GrammarRuleListResponse> {
        return axios.get('/api/grammar-rules', { params }).then((r) => r.data);
    },

    getOne(id: string | number): Promise<GrammarRuleOneResponse> {
        return axios.get(`/api/grammar-rules/${id}`).then((r) => r.data);
    },

    getEditorRule(id: string | number): Promise<{ rule: GrammarRuleEditorRule }> {
        return axios.get(`/api/grammar-rules/${id}/editor`).then((r) => r.data);
    },

    proposeGrammarEdit(
        id: string | number,
        payload: { instruction: string; conversation: GrammarRuleEditorTurn[]; draft: GrammarRuleEditorDraft },
    ): Promise<{ proposal: GrammarRuleEditorDraft; message?: string | null }> {
        return axios.post(`/api/grammar-rules/${id}/editor/proposals`, payload).then((r) => r.data);
    },

    applyGrammarEdit(
        id: string | number,
        draft: GrammarRuleEditorDraft,
        expectedVersion: number,
    ): Promise<{ rule: GrammarRuleEditorRule }> {
        return axios.put(`/api/grammar-rules/${id}/editor`, { ...draft, expected_version: expectedVersion }).then((r) => r.data);
    },

    getGrammarEditRevisions(id: string | number, page = 1): Promise<{ revisions: GrammarRuleEditorRevision[]; has_more: boolean; next_page: number | null }> {
        return axios.get(`/api/grammar-rules/${id}/editor/revisions`, { params: { page } }).then((r) => r.data);
    },

    restoreGrammarEditRevision(
        id: string | number,
        revisionId: number,
        expectedVersion: number,
    ): Promise<{ rule: GrammarRuleEditorRule }> {
        return axios.post(`/api/grammar-rules/${id}/editor/revisions/${revisionId}/restore`, { expected_version: expectedVersion }).then((r) => r.data);
    },

    getForContent(contentId: string | number): Promise<ContentGrammarRulesResponse> {
        return axios.get(`/api/content/${contentId}/grammar-rules`).then((r) => r.data);
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
