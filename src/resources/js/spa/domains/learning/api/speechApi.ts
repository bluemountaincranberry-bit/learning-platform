import axios from 'axios';

export type SpeechProvider = 'openai' | 'local_whisper';
export type SpeechLanguage = 'en' | 'ru';

export interface SpeechProviderOption {
    id: SpeechProvider;
    label: string;
}

export const speechApi = {
    providers(): Promise<{ providers: SpeechProviderOption[] }> {
        return axios.get('/api/learning/speech/providers').then((response) => response.data);
    },

    async transcribe(audio: Blob, provider: SpeechProvider, language: SpeechLanguage): Promise<{ text: string; provider: string }> {
        const form = new FormData();
        const extension = audio.type.includes('mp4') ? 'm4a' : audio.type.includes('ogg') ? 'ogg' : 'webm';
        const filename = audio instanceof File ? audio.name : `recording.${extension}`;
        form.append('audio', audio, filename);
        form.append('provider', provider);
        form.append('language', language);
        const response = await axios.post('/api/learning/speech/transcribe', form);
        return response.data.transcription;
    },

    pin(messageId: number, pinned: boolean): Promise<{ pinned: boolean; expires_at: string | null }> {
        return axios.post(`/api/ai/voice-recordings/${messageId}/pin`, { pinned }).then((response) => response.data);
    },
};
