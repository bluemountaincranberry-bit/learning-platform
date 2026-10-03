import axios from 'axios';
import type { ProgressStatsResponse } from '../../../types/api/ProgressStatsResponse';

export const progressStatsApi = {
    getStats(): Promise<ProgressStatsResponse> {
        return axios.get('/api/me/stats').then((r) => r.data);
    },
};
