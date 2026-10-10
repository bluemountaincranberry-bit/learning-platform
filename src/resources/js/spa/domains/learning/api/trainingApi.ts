import axios from 'axios';
import type { TrainingReviewQueueResponse, TrainingSelectedLexemesResponse, TrainingSelectedCanonicalLexemesResponse } from '../../../types/api/TrainingReviewQueueResponse';

export const trainingApi = {
    getReviewQueue(contentId?: number): Promise<TrainingReviewQueueResponse> {
        return axios
            .get('/api/training/review-queue', { params: contentId ? { content_id: contentId } : {} })
            .then((r) => r.data);
    },

    getSelectedLexemes(lexemeIds: number[]): Promise<TrainingSelectedLexemesResponse> {
        return axios
            .get('/api/training/selected-lexemes', { params: { lexeme_ids: lexemeIds.join(',') } })
            .then((r) => r.data);
    },

    getSelectedCanonicalLexemes(lexemeIds: number[]): Promise<TrainingSelectedCanonicalLexemesResponse> {
        return axios
            .get('/api/training/selected-canonical-lexemes', { params: { lexeme_ids: lexemeIds.join(',') } })
            .then((r) => r.data);
    },
};
