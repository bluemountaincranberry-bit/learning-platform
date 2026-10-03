import axios from 'axios';
import type { SrsDueResponse } from '../../../types/api/SrsDueResponse';

export const srsApi = {
    getDue(): Promise<SrsDueResponse> {
        return axios.get('/api/srs/due').then((r) => r.data);
    },

    review(cardId: number, grade: number, context: {
        content_lexeme_id?: number;
        exercise_type?: string;
        error_type?: string | null;
        hint_used?: boolean;
    } = {}): Promise<{ card: SrsDueResponse['items'][0] }> {
        return axios.post('/api/srs/review', { card_id: cardId, grade, ...context }).then((r) => r.data);
    },
};
