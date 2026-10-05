import axios from 'axios';
import type { MyWordsParams, MyWordsResponse } from '../../../types/api/MyWordsResponse';

export const myWordsApi = {
    getList(params: MyWordsParams = {}): Promise<MyWordsResponse> {
        const query: Record<string, string | number> = {};
        if (params.status != null && params.status !== 'all') query.status = params.status;
        if (params.language != null) query.language = params.language;
        if (params.level != null) query.level = params.level;
        if (params.content_id != null) query.content_id = params.content_id;
        if (params.search != null && params.search.trim() !== '') query.search = params.search.trim();
        if (params.per_page != null) query.per_page = params.per_page;
        if (params.page != null) query.page = params.page;
        return axios.get('/api/me/words', { params: query }).then((r) => r.data);
    },
    addWord(input: { lemma: string; language: string }): Promise<{ lexeme: { id: number; lemma: string; language: string; is_personal: boolean } }> {
        return axios.post('/api/me/words', input).then((r) => r.data);
    },
    startLearning(lexemeId: number): Promise<void> {
        return axios.post(`/api/me/words/${lexemeId}/start-learning`).then(() => undefined);
    },
    stopLearning(lexemeId: number): Promise<void> {
        return axios.post(`/api/me/words/${lexemeId}/stop-learning`).then(() => undefined);
    },
    markKnown(lexemeId: number): Promise<void> {
        return axios.post(`/api/me/words/${lexemeId}/mark-known`).then(() => undefined);
    },
    unmarkKnown(lexemeId: number): Promise<void> {
        return axios.delete(`/api/me/words/${lexemeId}/mark-known`).then(() => undefined);
    },
};
