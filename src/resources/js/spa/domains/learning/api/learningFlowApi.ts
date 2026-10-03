import axios from 'axios';

export interface LearningFlowPreferences {
    learning_flow_profile_id: number | null;
    session_minutes: number | null;
    daily_new_words: number | null;
    listening_weight: number | null;
    speaking_weight: number | null;
    hint_mode: 'guided' | 'balanced' | 'challenge' | null;
    difficulty_preference: 'easier' | 'balanced' | 'harder' | null;
}

export interface LearningFlowResponse {
    profile: { id: number | null; name: string; slug: string; version: number; source: string };
    config: Record<string, unknown>;
    preferences: LearningFlowPreferences | null;
    available_profiles: Array<{ id: number; name: string; slug: string; description: string | null; version: number }>;
}

export const learningFlowApi = {
    get(): Promise<LearningFlowResponse> {
        return axios.get('/api/learning/flow').then((r) => r.data);
    },
    update(payload: Partial<LearningFlowPreferences>): Promise<{ preferences: LearningFlowPreferences; flow: LearningFlowResponse }> {
        return axios.put('/api/learning/flow/preferences', payload).then((r) => r.data);
    },
};
