import { useTrainerSettings } from '../../composables/useTrainerSettings';

/** Bare ISO 639-1 codes (as stored in `translation_language` / content `language`, see SettingsPage's language options) mapped to a full BCP-47 tag — some engines (notably iOS/Safari) key installed voices by the full tag and won't match a bare 2-letter code. */
const BCP47_BY_ISO639_1: Record<string, string> = {
    ru: 'ru-RU',
    en: 'en-US',
    es: 'es-ES',
    fr: 'fr-FR',
    de: 'de-DE',
    it: 'it-IT',
    pt: 'pt-PT',
    zh: 'zh-CN',
    ja: 'ja-JP',
    ko: 'ko-KR',
    ar: 'ar-SA',
    tr: 'tr-TR',
    pl: 'pl-PL',
    nl: 'nl-NL',
    uk: 'uk-UA',
};

/** Resolves a stored language code to the tag `speechSynthesis` should use — passes through anything already-qualified (e.g. `en-GB`) or unrecognized. */
export function resolveSpeechLang(language: string): string {
    return BCP47_BY_ISO639_1[language.toLowerCase()] ?? language;
}

/** True when the browser exposes the Web Speech API (no backend TTS exists yet — this is a free client-side stand-in). */
export function isSpeechSupported(): boolean {
    return typeof window !== 'undefined' && 'speechSynthesis' in window;
}

/**
 * Best-effort check for whether a voice exists for `language` — `speechSynthesis.speak()`
 * fails silently (no audio, no error) when no matching voice is installed, which would
 * otherwise break an audio-first exercise with no feedback. Voices load asynchronously,
 * so an empty list just means "not loaded yet" and is treated as "assume available"
 * rather than a false negative.
 */
export function hasVoiceFor(language?: string | null): boolean {
    if (!isSpeechSupported() || !language) return false;
    const voices = window.speechSynthesis.getVoices();
    if (voices.length === 0) return true;
    const target = resolveSpeechLang(language).toLowerCase();
    const prefix = target.split('-')[0];
    return voices.some((v) => v.lang.toLowerCase() === target || v.lang.toLowerCase().startsWith(`${prefix}-`));
}

/** Voices installed for the current browser/OS, for a voice picker in settings — empty until the async 'voiceschanged' event fires on first load. */
export function listVoices(): SpeechSynthesisVoice[] {
    if (!isSpeechSupported()) return [];
    return window.speechSynthesis.getVoices();
}

/** Speaks `text` aloud using the browser's built-in speech synthesis, optionally in a given BCP-47-ish or ISO 639-1 language code. Rate and preferred voice come from the trainer's own settings (module-level, so this stays a plain function callable from anywhere, not just inside a component). */
export function speak(text: string, language?: string | null): void {
    if (!isSpeechSupported() || !text) return;

    const { settings } = useTrainerSettings();
    const utterance = new SpeechSynthesisUtterance(text);
    if (language) utterance.lang = resolveSpeechLang(language);
    utterance.rate = settings.value.speechRate;
    if (settings.value.speechVoiceURI) {
        const voice = listVoices().find((v) => v.voiceURI === settings.value.speechVoiceURI);
        if (voice) utterance.voice = voice;
    }

    window.speechSynthesis.cancel();
    window.speechSynthesis.speak(utterance);
}
