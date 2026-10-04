import axios from 'axios';

export const translateApi = {
    /** Translate a short learner-facing text into the target ISO 639-1 language. */
    translate(text: string, targetLanguage: string): Promise<{ translation: string }> {
        return axios.post('/api/ai/translate', { text, target_language: targetLanguage }).then((r) => r.data);
    },
};
