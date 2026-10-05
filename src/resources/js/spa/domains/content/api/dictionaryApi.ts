import axios from 'axios';
import type { LexemeDetailResponse, MoreExamplesResponse } from '../../../types';

export const dictionaryApi = {
    getOne(id: string | number): Promise<LexemeDetailResponse> {
        return axios.get(`/api/dictionary/${id}`).then((r) => r.data);
    },

    /** Fresh, on-demand AI example sentences for a canonical word — read-only, nothing is saved. */
    moreExamples(id: string | number): Promise<MoreExamplesResponse> {
        return axios.post(`/api/dictionary/${id}/more-examples`).then((r) => r.data);
    },

    /** AI explanation for a canonical word — generated on demand, then saved and reused. */
    explain(id: string | number, refresh = false): Promise<{ explanation: string; explanation_id: number | null }> {
        return axios.post(`/api/dictionary/${id}/explain`, undefined, { params: refresh ? { refresh: 1 } : undefined }).then((r) => r.data);
    },

    /** Delete one stored explanation variant from the word page. */
    deleteExplanation(wordId: string | number, explanationId: number): Promise<{ ok: boolean }> {
        return axios.delete(`/api/dictionary/${wordId}/explanations/${explanationId}`).then((r) => r.data);
    },
};
