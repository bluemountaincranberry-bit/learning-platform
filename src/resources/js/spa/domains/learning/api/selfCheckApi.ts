import axios from 'axios';
import type { LexemeExampleItem } from '../../../types';

export interface SelfCheckItem {
    content_lexeme_id: number;
    lexeme_display: string;
    part_of_speech?: string | null;
    level?: string | null;
    translation: string | null;
    example: string | null;
    examples: LexemeExampleItem[];
    adaptive_activity?: 'encounter' | 'recognition' | 'recall' | 'cloze' | 'listening' | 'shadowing' | 'quick-check';
    target_dimension?: 'recognition' | 'recall' | 'production' | 'listening' | 'speaking';
    learning_stage?: string;
    difficulty?: number;
    selection_reason?: string;
    retry_id?: number | null;
}

export interface SelfCheckStartResponse {
    items: SelfCheckItem[];
}

export interface SelfCheckAnswer {
    content_lexeme_id: number;
    known: boolean;
    hint_used?: boolean;
    error_type?: string | null;
    transcript_segment_id?: number | null;
    retry_id?: number | null;
    exercise_type?: string | null;
}

export interface SelfCheckSubmitPayload {
    content_id: number;
    operation_id?: string;
    answers: SelfCheckAnswer[];
}

export interface SelfCheckSubmitResponse {
    total: number;
    correct: number;
    score_pct: number;
}

export const selfCheckApi = {
    start(contentId: number, limit = 10): Promise<SelfCheckStartResponse> {
        return axios.get('/api/self-check/start', { params: { content_id: contentId, limit } }).then((r) => r.data);
    },

    submit(payload: SelfCheckSubmitPayload): Promise<SelfCheckSubmitResponse> {
        return axios.post('/api/self-check/submit', payload).then((r) => r.data);
    },
};
