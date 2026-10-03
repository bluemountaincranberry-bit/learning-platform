import { ref, watch } from 'vue';

export interface TrainerSettings {
    /** Review/Listening: show the translation panel once revealed. Off = pure recall, no safety net. */
    translationVisible: boolean;
    /** Speak the target word aloud automatically once it's revealed/answered. */
    autoplayPronunciation: boolean;
    /** Listen-recognize cards: reveal the native-language translation on tap, or automatically after `autoRevealSeconds`. */
    listeningReveal: 'tap' | 'auto';
    /** Listen-recognize cards: delay before auto-reveal when `listeningReveal` is 'auto'. Tapping the card early always reveals immediately regardless. */
    autoRevealSeconds: number;
    /** speechSynthesis playback speed for every TTS call in the trainer (1 = normal). */
    speechRate: number;
    /** `SpeechSynthesisVoice.voiceURI` to prefer, when set — null lets the browser pick its default voice for the utterance's language. */
    speechVoiceURI: string | null;
}

const STORAGE_KEY = 'trainer.settings';

const DEFAULTS: TrainerSettings = {
    translationVisible: true,
    autoplayPronunciation: false,
    listeningReveal: 'tap',
    autoRevealSeconds: 4,
    speechRate: 1,
    speechVoiceURI: null,
};

function readStored(): TrainerSettings {
    if (typeof window === 'undefined') return { ...DEFAULTS };
    try {
        const stored = JSON.parse(window.localStorage.getItem(STORAGE_KEY) ?? '{}');
        return { ...DEFAULTS, ...stored };
    } catch {
        return { ...DEFAULTS };
    }
}

const settings = ref<TrainerSettings>(readStored());

watch(
    settings,
    (value) => {
        if (typeof window !== 'undefined') window.localStorage.setItem(STORAGE_KEY, JSON.stringify(value));
    },
    { deep: true },
);

/**
 * Trainer-wide display/behavior toggles, separate from `useAnswerStylePreference`
 * (which is specifically "how do I produce the answer"). A single shared
 * module-level ref persisted to localStorage — a cross-session UI preference,
 * not per-component state, matching the existing answer-style precedent.
 */
export function useTrainerSettings() {
    return { settings };
}
