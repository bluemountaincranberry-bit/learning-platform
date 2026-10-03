import axios from 'axios';
import type { RecommendedContentsResponse, RecommendedLexemesResponse } from '../../../types';

export const recommendedApi = {
    getContents(limit = 10): Promise<RecommendedContentsResponse> {
        return axios.get('/api/ai/recommended/contents', { params: { limit } }).then((r) => r.data);
    },

    getLexemes(limit = 20): Promise<RecommendedLexemesResponse> {
        return axios.get('/api/ai/recommended/lexemes', { params: { limit } }).then((r) => r.data);
    },
};
