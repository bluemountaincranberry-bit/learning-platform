import axios from 'axios';
import type { ExerciseAttemptResponse } from '../../../types';

export const exerciseAttemptsApi = {
    create(payload: FormData): Promise<ExerciseAttemptResponse> {
        return axios.post('/api/learning/exercise-attempts', payload).then((response) => response.data);
    },

    get(id: number): Promise<ExerciseAttemptResponse> {
        return axios.get(`/api/learning/exercise-attempts/${id}`).then((response) => response.data);
    },

    async waitForCompletion(id: number): Promise<ExerciseAttemptResponse> {
        for (let attempt = 0; attempt < 30; attempt += 1) {
            const response = await this.get(id);
            if (response.attempt.status === 'completed' || response.attempt.status === 'failed') return response;
            await new Promise((resolve) => window.setTimeout(resolve, 1000));
        }
        return this.get(id);
    },
};
