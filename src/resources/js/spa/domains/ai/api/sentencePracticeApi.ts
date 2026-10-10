import axios from 'axios';
import type { SentencePracticeCard } from '../../../shared/types/SentencePracticeCard';
export type { SentencePracticeCard } from '../../../shared/types/SentencePracticeCard';

export type SentencePracticeDirection = 'to_target' | 'to_native';
export type SentencePracticeCheckMode = 'flexible' | 'exact';

export interface SentencePracticeStartResponse {
    cards: SentencePracticeCard[];
    note: string | null;
}

export interface SentencePracticeCheckPayload {
    prompt_sentence: string;
    prompt_language: string;
    answer_language: string;
    answer: string;
    check_mode?: SentencePracticeCheckMode;
    mistake_id?: number;
}

export interface SentencePracticeCheckResponse {
    correct: boolean;
    feedback: string;
    model_answer: string;
    mistake?: { id: number; status: string; saved_automatically?: boolean; consecutive_correct?: number } | null;
}

export const sentencePracticeApi = {
    /** contentId scopes generation to that content's own words/grammar ("Reinforce") instead of the learner's global recent-study pool. */
    start(direction: SentencePracticeDirection, count = 5, contentId?: number, mistakeIds?: number[]): Promise<SentencePracticeStartResponse> {
        return axios.post('/api/practice/sentences/start', { direction, count, content_id: contentId, mistake_ids: mistakeIds }).then((r) => r.data);
    },

    check(payload: SentencePracticeCheckPayload): Promise<SentencePracticeCheckResponse> {
        return axios.post('/api/practice/sentences/check', payload).then((r) => r.data);
    },
};
