import { ref, watch } from 'vue';

export type AnswerStyle = 'reveal' | 'type' | 'tap-letters' | 'choose-word';

const STORAGE_KEY = 'trainer.answer-style';
const VALID: AnswerStyle[] = ['reveal', 'type', 'tap-letters', 'choose-word'];

function readStored(): AnswerStyle {
    const stored = typeof window !== 'undefined' ? window.localStorage.getItem(STORAGE_KEY) : null;
    return VALID.includes(stored as AnswerStyle) ? (stored as AnswerStyle) : 'type';
}

const answerStyle = ref<AnswerStyle>(readStored());

watch(answerStyle, (value) => {
    if (typeof window !== 'undefined') window.localStorage.setItem(STORAGE_KEY, value);
});

/**
 * How the user prefers to answer recall cards (Review's "what's the word"
 * step, and Cloze/Listening's production step): read the translation and
 * judge yourself ('reveal', the old default), type the word, tap it together
 * from a jumbled letter bank, or (Cloze only, when enough distractor words
 * are available) tap the whole word from a small set of choices, Clozemaster-
 * style. Review/Listening fall back to 'type' when this is 'choose-word' —
 * see WordCard's `productionStyle`. A single, shared module-level ref — the
 * choice is a cross-session UI preference, not per-component state.
 */
export function useAnswerStylePreference() {
    return { answerStyle };
}
