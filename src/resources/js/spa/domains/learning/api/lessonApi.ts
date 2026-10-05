import axios from 'axios';

/**
 * "Мои занятия" — backed by LessonController/LessonAgentService. Unlike
 * tutorApi's streamMessage(), sending a message here is fire-and-forget:
 * the turn runs in a queued job (RunAgentTurnJob), not the request, because
 * this agent can call extract_pdf_text (not the "one fast SQL query" case
 * that justifies TutorConversationController's synchronous SSE approach).
 * The SPA polls listMessages() while a turn is in flight, the same way
 * ContentAgentChat (Filament) polls via Livewire.
 */

export interface LessonSummary {
    id: number;
    title: string | null;
    tutor: string | null;
    status: 'active' | 'archived';
    updated_at: string;
    lexeme_count: number;
    grammar_count: number;
}

export interface LessonMessage {
    id: number;
    role: 'user' | 'assistant';
    content: string | null;
    attachment_name: string | null;
    created_at: string;
}

export interface LessonLexemeCandidate {
    language: string;
    id: number;
    text: string;
    type: string;
    level: string | null;
    translation: string | null;
    example: string | null;
    example_translation: string | null;
    status: 'pending' | 'matched' | 'new';
    matched_lexeme_id: number | null;
}

export interface LessonGrammarCandidate {
    id: number;
    title: string;
    summary: string | null;
    example: string | null;
    example_translation: string | null;
    status: 'pending' | 'linked' | 'new';
    matched_grammar_rule_id: number | null;
}

export interface LessonDetail {
    id: number;
    title: string | null;
    tutor: string | null;
    status: 'active' | 'archived';
    conversation_id: number | null;
    analysis_status: 'pending' | 'running' | 'completed' | 'failed' | null;
    lexemes: LessonLexemeCandidate[];
    grammar: LessonGrammarCandidate[];
}

export const lessonApi = {
    list(page = 1): Promise<{ data: LessonSummary[]; meta: { current_page: number; per_page: number; total: number; last_page?: number } }> {
        return axios.get('/api/lessons', { params: { page } }).then((r) => r.data);
    },

    create(): Promise<{ lesson_id: number; conversation_id: number }> {
        return axios.post('/api/lessons').then((r) => r.data);
    },

    get(lessonId: number): Promise<LessonDetail> {
        return axios.get(`/api/lessons/${lessonId}`).then((r) => r.data);
    },

    listMessages(lessonId: number): Promise<{ messages: LessonMessage[]; is_waiting: boolean }> {
        return axios.get(`/api/lessons/${lessonId}/messages`).then((r) => r.data);
    },

    sendMessage(lessonId: number, content: string, attachment?: File | null): Promise<void> {
        const form = new FormData();
        if (content) form.append('content', content);
        if (attachment) form.append('attachment', attachment);

        return axios.post(`/api/lessons/${lessonId}/messages`, form).then(() => undefined);
    },

    analyze(lessonId: number): Promise<{ run_id: number; status: string }> {
        return axios.post(`/api/lessons/${lessonId}/analyze`).then((r) => r.data);
    },
};
