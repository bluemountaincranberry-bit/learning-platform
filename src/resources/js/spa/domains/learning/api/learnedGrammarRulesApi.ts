import axios from 'axios';
import type { LearnedGrammarRulesParams, LearnedGrammarRulesResponse } from '../../../types/api/LearnedGrammarRulesResponse';

export const learnedGrammarRulesApi = {
    getList(params: LearnedGrammarRulesParams = {}): Promise<LearnedGrammarRulesResponse> {
        const query: Record<string, string | number> = {};
        if (params.status != null) query.status = params.status;
        if (params.per_page != null) query.per_page = params.per_page;
        if (params.page != null) query.page = params.page;
        return axios.get('/api/me/grammar-rules', { params: query }).then((r) => r.data);
    },
};
