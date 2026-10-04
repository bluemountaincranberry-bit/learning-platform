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
    explain(id: string | number): Promise<{ explanation: string }> {
        return axios.post(`/api/dictionary/${id}/explain`).then((r) => r.data);
    },
};
