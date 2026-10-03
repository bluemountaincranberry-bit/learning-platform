import axios from 'axios';
import type { LearnedLexemesParams, LearnedLexemesResponse } from '../../../types/api/LearnedLexemesResponse';

export const learnedLexemesApi = {
    getList(params: LearnedLexemesParams = {}): Promise<LearnedLexemesResponse> {
        const query: Record<string, string | number> = {};
        if (params.language != null) query.language = params.language;
        if (params.content_id != null) query.content_id = params.content_id;
        if (params.date_from != null) query.date_from = params.date_from;
        if (params.date_to != null) query.date_to = params.date_to;
        if (params.per_page != null) query.per_page = params.per_page;
        if (params.page != null) query.page = params.page;
        return axios.get('/api/me/learned-lexemes', { params: query }).then((r) => r.data);
    },
};
