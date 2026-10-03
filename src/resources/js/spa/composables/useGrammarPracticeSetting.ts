import { ref, watch } from 'vue';
import type { GrammarPracticeLevel } from '../types';

const STORAGE_KEY = 'grammar-practice.setting';
const LEVELS: GrammarPracticeLevel[] = ['easy', 'medium', 'hard'];
export const GRAMMAR_PRACTICE_COUNTS = [5, 10, 15] as const;

interface Setting {
    level: GrammarPracticeLevel;
    count: number;
}

const DEFAULT: Setting = { level: 'medium', count: 10 };

function readStored(): Setting {
    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);
        const parsed = raw ? (JSON.parse(raw) as Partial<Setting>) : {};
        return {
            level: LEVELS.includes(parsed.level as GrammarPracticeLevel) ? (parsed.level as GrammarPracticeLevel) : DEFAULT.level,
            count: (GRAMMAR_PRACTICE_COUNTS as readonly number[]).includes(Number(parsed.count)) ? Number(parsed.count) : DEFAULT.count,
        };
    } catch {
        return { ...DEFAULT };
    }
}

const setting = ref<Setting>(typeof window === 'undefined' ? { ...DEFAULT } : readStored());

watch(setting, (value) => {
    try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(value));
    } catch {
        // Private mode / blocked storage: the choice just isn't remembered.
    }
}, { deep: true });

/** About 25 seconds per exercise: "Medium · 10 exercises · ~4 min". */
export function grammarPracticeMinutes(count: number): number {
    return Math.max(1, Math.round((count * 25) / 60));
}

/**
 * The learner's Level (Easy/Medium/Hard) and Count (5/10/15) for grammar
 * practice, default Medium · 10, remembered on this device. One shared ref,
 * like useAnswerStylePreference: a UI preference, not per-page state.
 */
export function useGrammarPracticeSetting() {
    return { setting };
}
