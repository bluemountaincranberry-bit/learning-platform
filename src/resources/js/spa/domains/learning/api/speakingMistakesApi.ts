import axios from 'axios';

export interface SpeakingMistake {
    id: number;
    language: string;
    original_text: string;
    corrected_text: string;
    prompt_text: string | null;
    explanation: string | null;
    category: string;
    source_type: string;
    source_id: number | null;
    status: 'active' | 'mastered' | 'hidden';
    confidence: 'clear' | 'uncertain';
    consecutive_correct: number;
    last_seen_at: string | null;
    created_at: string;
}

export interface SpeakingMistakesReport {
    mistakes: SpeakingMistake[];
    summary: { active: number; mastered: number; hidden: number };
    weekly_trend: Array<{ week: string; count: number }>;
    by_category: Array<{ category: string; count: number }>;
}

export const speakingMistakesApi = {
    report(status: 'active' | 'mastered' | 'hidden' | 'all' = 'active'): Promise<SpeakingMistakesReport> {
        return axios.get('/api/learning/speaking-mistakes', { params: { status } }).then((response) => response.data);
    },

    setStatus(id: number, status: SpeakingMistake['status']): Promise<{ mistake: SpeakingMistake }> {
        return axios.patch(`/api/learning/speaking-mistakes/${id}`, { status }).then((response) => response.data);
    },
};
