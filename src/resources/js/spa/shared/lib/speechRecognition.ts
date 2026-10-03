import { resolveSpeechLang } from './speech';

/** Minimal shape of the (non-standard, vendor-prefixed on Safari/iOS) Web Speech recognition API — not part of TS's DOM lib, so declared locally rather than pulling in a dependency for a handful of fields. */
interface MinimalSpeechRecognition {
    lang: string;
    interimResults: boolean;
    maxAlternatives: number;
    start(): void;
    stop(): void;
    onresult: ((event: { results: { [index: number]: { [index: number]: { transcript: string } } } }) => void) | null;
    onerror: ((event: { error: string }) => void) | null;
    onend: (() => void) | null;
}

function getRecognitionCtor(): (new () => MinimalSpeechRecognition) | null {
    if (typeof window === 'undefined') return null;
    const w = window as unknown as { SpeechRecognition?: new () => MinimalSpeechRecognition; webkitSpeechRecognition?: new () => MinimalSpeechRecognition };
    return w.SpeechRecognition ?? w.webkitSpeechRecognition ?? null;
}

/** No backend speech-to-text exists — same free client-side stand-in approach as speech.ts's TTS. */
export function isSpeechRecognitionSupported(): boolean {
    return getRecognitionCtor() !== null;
}

/** Listens for a single utterance in `language` (ISO 639-1 or BCP-47) and resolves with the transcript. Rejects if unsupported, denied, or no speech detected. */
export function listenOnce(language?: string | null): Promise<string> {
    const Ctor = getRecognitionCtor();
    if (!Ctor) return Promise.reject(new Error('Speech recognition not supported'));

    return new Promise((resolve, reject) => {
        const recognition = new Ctor();
        recognition.lang = language ? resolveSpeechLang(language) : '';
        recognition.interimResults = false;
        recognition.maxAlternatives = 1;
        recognition.onresult = (event) => resolve(event.results[0][0].transcript);
        recognition.onerror = (event) => reject(new Error(event.error));
        recognition.start();
    });
}
