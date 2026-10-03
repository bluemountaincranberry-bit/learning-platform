import axios from 'axios';
import type {
    GrammarPracticeCheckResponse,
    GrammarPracticeExercise,
    GrammarPracticeLevel,
    GrammarPracticeOverview,
    GrammarPracticeResult,
    GrammarPracticeResultItem,
    GrammarPracticeRoundResponse,
} from '../../../types';

export const grammarPracticeApi = {
    getOverview(ruleId: number | string): Promise<GrammarPracticeOverview> {
        return axios.get(`/api/grammar-rules/${ruleId}/practice`).then((r) => r.data);
    },

    /** 202 (preparing) and 503 (unavailable) are answers here, not errors. */
    startRound(
        ruleId: number | string,
        payload: { level: GrammarPracticeLevel; count: number; exercise_ids?: number[] },
    ): Promise<GrammarPracticeRoundResponse> {
        return axios
            .post(`/api/grammar-rules/${ruleId}/practice/rounds`, payload, {
                validateStatus: (status) => status === 200 || status === 202 || status === 503,
            })
            .then((r) => r.data);
    },

    /** The server counts tries and decides the outcome; the client only sends what was answered. */
    check(
        exerciseId: number,
        payload: { given: string | null; show_answer?: boolean },
    ): Promise<GrammarPracticeCheckResponse> {
        return axios.post(`/api/grammar-exercises/${exerciseId}/check`, payload).then((r) => r.data);
    },

    report(
        exerciseId: number,
        payload: { level: GrammarPracticeLevel; round_exercise_ids: number[]; reason?: string | null },
    ): Promise<{ replacement: GrammarPracticeExercise | null }> {
        return axios.post(`/api/grammar-exercises/${exerciseId}/report`, payload).then((r) => r.data);
    },

    complete(
        ruleId: number | string,
        payload: { level: GrammarPracticeLevel; items: GrammarPracticeResultItem[]; content_id?: number | null; replay?: boolean },
    ): Promise<GrammarPracticeResult> {
        return axios.post(`/api/grammar-rules/${ruleId}/practice/complete`, payload).then((r) => r.data);
    },
};
