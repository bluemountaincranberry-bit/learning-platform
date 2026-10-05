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
    lesson_date: string | null;
    teacher: string | null;
    topic: string | null;
    status: 'active' | 'archived';
    updated_at: string;
    lexeme_count: number;
    grammar_count: number;
    correction_count: number;
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
    source: 'ai' | 'manual';
}

export interface LessonGrammarCandidate {
    id: number;
    title: string;
    summary: string | null;
    example: string | null;
    example_translation: string | null;
    status: 'pending' | 'linked' | 'new';
    matched_grammar_rule_id: number | null;
    body?: string | null;
    source: 'ai' | 'manual';
}

export interface LessonCorrection {
    id: number;
    original_text: string;
    corrected_text: string;
    explanation: string | null;
    source: 'ai' | 'manual';
}

export type LessonLexemeInput = Pick<LessonLexemeCandidate, 'text'> & Partial<Pick<LessonLexemeCandidate, 'type' | 'level' | 'translation' | 'example' | 'example_translation'>>;
export type LessonGrammarInput = Pick<LessonGrammarCandidate, 'title'> & Partial<Pick<LessonGrammarCandidate, 'summary' | 'body' | 'example' | 'example_translation'>>;
export type LessonCorrectionInput = Pick<LessonCorrection, 'original_text' | 'corrected_text'> & Partial<Pick<LessonCorrection, 'explanation'>>;
export type LessonItemCollection = 'lexemes' | 'grammar' | 'corrections';

function lessonItemPath(lessonId: number, collection: LessonItemCollection, itemId?: number): string {
    const path = `/api/lessons/${lessonId}/${collection}`;
    return itemId === undefined ? path : `${path}/${itemId}`;
}

export interface LessonDetail {
    id: number;
    title: string | null;
    lesson_date: string | null;
    teacher: string | null;
    topic: string | null;
    language: string;
    tags: string[];
    notes: string | null;
    homework: string | null;
    status: 'active' | 'archived';
    conversation_id: number | null;
    analysis_status: 'pending' | 'running' | 'completed' | 'failed' | null;
    lexemes: LessonLexemeCandidate[];
    grammar: LessonGrammarCandidate[];
    corrections: LessonCorrection[];
}

export type UpdateLessonInput = Pick<LessonDetail, 'title' | 'lesson_date' | 'teacher' | 'topic' | 'language' | 'tags' | 'notes' | 'homework'>;

export const lessonApi = {
    list(page = 1, status?: 'all' | 'active' | 'archived'): Promise<{ data: LessonSummary[]; meta: { current_page: number; per_page: number; total: number; last_page?: number } }> {
        const params: Record<string, string | number> = { page };
        if (status && status !== 'all') params.status = status;
        return axios.get('/api/lessons', { params }).then((r) => r.data);
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

    update(lessonId: number, data: Partial<UpdateLessonInput>): Promise<void> {
        return axios.put(`/api/lessons/${lessonId}`, data).then(() => undefined);
    },

    destroy(lessonId: number): Promise<void> {
        return axios.delete(`/api/lessons/${lessonId}`).then(() => undefined);
    },

    restore(lessonId: number): Promise<void> {
        return axios.post(`/api/lessons/${lessonId}/restore`).then(() => undefined);
    },

    createLexeme(lessonId: number, data: LessonLexemeInput): Promise<LessonLexemeCandidate> {
        return axios.post(lessonItemPath(lessonId, 'lexemes'), data).then((r) => r.data);
    },

    updateLexeme(lessonId: number, itemId: number, data: Partial<LessonLexemeInput>): Promise<LessonLexemeCandidate> {
        return axios.put(lessonItemPath(lessonId, 'lexemes', itemId), data).then((r) => r.data);
    },

    deleteLexeme(lessonId: number, itemId: number): Promise<void> {
        return axios.delete(lessonItemPath(lessonId, 'lexemes', itemId)).then(() => undefined);
    },

    restoreLexeme(lessonId: number, itemId: number): Promise<LessonLexemeCandidate> {
        return axios.post(`${lessonItemPath(lessonId, 'lexemes', itemId)}/restore`).then((r) => r.data);
    },

    createGrammar(lessonId: number, data: LessonGrammarInput): Promise<LessonGrammarCandidate> {
        return axios.post(lessonItemPath(lessonId, 'grammar'), data).then((r) => r.data);
    },

    updateGrammar(lessonId: number, itemId: number, data: Partial<LessonGrammarInput>): Promise<LessonGrammarCandidate> {
        return axios.put(lessonItemPath(lessonId, 'grammar', itemId), data).then((r) => r.data);
    },

    deleteGrammar(lessonId: number, itemId: number): Promise<void> {
        return axios.delete(lessonItemPath(lessonId, 'grammar', itemId)).then(() => undefined);
    },

    restoreGrammar(lessonId: number, itemId: number): Promise<LessonGrammarCandidate> {
        return axios.post(`${lessonItemPath(lessonId, 'grammar', itemId)}/restore`).then((r) => r.data);
    },

    createCorrection(lessonId: number, data: LessonCorrectionInput): Promise<LessonCorrection> {
        return axios.post(lessonItemPath(lessonId, 'corrections'), data).then((r) => r.data);
    },

    updateCorrection(lessonId: number, itemId: number, data: Partial<LessonCorrectionInput>): Promise<LessonCorrection> {
        return axios.put(lessonItemPath(lessonId, 'corrections', itemId), data).then((r) => r.data);
    },

    deleteCorrection(lessonId: number, itemId: number): Promise<void> {
        return axios.delete(lessonItemPath(lessonId, 'corrections', itemId)).then(() => undefined);
    },

    restoreCorrection(lessonId: number, itemId: number): Promise<LessonCorrection> {
        return axios.post(`${lessonItemPath(lessonId, 'corrections', itemId)}/restore`).then((r) => r.data);
    },
};
