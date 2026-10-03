import axios from 'axios';
import type { SentencePracticeCard } from '../../../shared/types/SentencePracticeCard';

export interface ReadinessStep {
    total: number;
    learned: number;
    complete: boolean;
}

export interface ContentExamAttempt {
    id: number;
    user_id: number;
    content_id: number;
    total_cards: number;
    correct_count: number;
    score_pct: number;
    passed: boolean;
    pass_threshold_pct: number;
    items: ExamResultItem[];
    completed_at: string;
}

export interface ContentReadiness {
    steps: {
        words: ReadinessStep;
        grammar: ReadinessStep;
        exam_unlocked: boolean;
    };
    latest_attempt: ContentExamAttempt | null;
    ready: boolean;
    pass_threshold_pct: number;
}

export interface ExamResultItem {
    prompt_sentence: string;
    prompt_language: string;
    answer_language: string;
    answer: string;
    correct: boolean;
    model_answer: string;
    hint_words: string[];
}

export const contentReadinessApi = {
    show(contentId: number): Promise<ContentReadiness> {
        return axios.get(`/api/content/${contentId}/readiness`).then((r) => r.data);
    },

    startExam(contentId: number): Promise<{ cards: SentencePracticeCard[] }> {
        return axios.post(`/api/content/${contentId}/readiness/exam/start`).then((r) => r.data);
    },

    completeExam(contentId: number, results: ExamResultItem[]): Promise<ContentExamAttempt> {
        return axios.post(`/api/content/${contentId}/readiness/exam/complete`, { results }).then((r) => r.data);
    },
};
