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

function mapObjectKeys(value: unknown, mapKey: (key: string) => string): unknown {
    if (Array.isArray(value)) return value.map((item) => mapObjectKeys(item, mapKey));
    if (value === null || typeof value !== 'object') return value;

    return Object.fromEntries(Object.entries(value).map(([key, child]) => [mapKey(key), mapObjectKeys(child, mapKey)]));
}

function toDomain<T>(wire: unknown): T {
    return mapObjectKeys(wire, (key) => key.replace(/_([a-z])/g, (_, letter: string) => letter.toUpperCase())) as T;
}

function toWire(value: unknown): unknown {
    return mapObjectKeys(value, (key) => key.replace(/[A-Z]/g, (letter) => `_${letter.toLowerCase()}`));
}

export const grammarPracticeApi = {
    getOverview(ruleId: number | string): Promise<GrammarPracticeOverview> {
        return axios.get(`/api/grammar-rules/${ruleId}/practice`).then((r) => toDomain<GrammarPracticeOverview>(r.data));
    },

    /** 202 (preparing) and 503 (unavailable) are answers here, not errors. */
    startRound(
        ruleId: number | string,
        payload: { level: GrammarPracticeLevel; count: number; exerciseIds?: number[] },
    ): Promise<GrammarPracticeRoundResponse> {
        return axios
            .post(`/api/grammar-rules/${ruleId}/practice/rounds`, toWire(payload), {
                validateStatus: (status) => status === 200 || status === 202 || status === 503,
            })
            .then((r) => toDomain<GrammarPracticeRoundResponse>(r.data));
    },

    /** The server counts tries and decides the outcome; the client only sends what was answered. */
    check(
        exerciseId: number,
        payload: { given: string | null; showAnswer?: boolean },
    ): Promise<GrammarPracticeCheckResponse> {
        return axios.post(`/api/grammar-exercises/${exerciseId}/check`, toWire(payload)).then((r) => toDomain<GrammarPracticeCheckResponse>(r.data));
    },

    report(
        exerciseId: number,
        payload: { level: GrammarPracticeLevel; roundExerciseIds: number[]; reason?: string | null },
    ): Promise<{ replacement: GrammarPracticeExercise | null }> {
        return axios
            .post(`/api/grammar-exercises/${exerciseId}/report`, toWire(payload))
            .then((r) => toDomain<{ replacement: GrammarPracticeExercise | null }>(r.data));
    },

    complete(
        ruleId: number | string,
        payload: { level: GrammarPracticeLevel; items: GrammarPracticeResultItem[]; contentId?: number | null; replay?: boolean },
    ): Promise<GrammarPracticeResult> {
        return axios.post(`/api/grammar-rules/${ruleId}/practice/complete`, toWire(payload)).then((r) => toDomain<GrammarPracticeResult>(r.data));
    },
};
