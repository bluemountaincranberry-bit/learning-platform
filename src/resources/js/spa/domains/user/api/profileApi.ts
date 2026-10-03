import axios from 'axios';
import type { ProfileResponse } from '../../../types';

export const profileApi = {
    getProfile(): Promise<ProfileResponse> {
        return axios.get('/api/profile').then((r) => r.data);
    },

    updateProfile(payload: {
        daily_goal?: number | null;
        translation_language?: string | null;
        current_level?: string | null;
        learning_goal?: 'conversation' | 'work' | 'travel' | 'exam' | 'general' | null;
        ai_extraction_thoroughness?: string | null;
    }): Promise<{ user: ProfileResponse['user'] }> {
        return axios.put('/api/profile', payload).then((r) => r.data);
    },
};
