import axios from 'axios';
import type {
    ContentListParams,
    ContentListResponse,
    ContentOneResponse,
    ContentCategoriesResponse,
    ContentLexemesResponse,
    SubmitYoutubePayload,
    SubmitYoutubeResponse,
    MySubmissionsResponse,
    BulkLexemeActionResponse,
    AiSuggestionsResponse,
    AcceptAiSuggestionsPayload,
    AcceptAiSuggestionsResponse,
    TranscriptResponse,
    TranscriptTranslationsResponse,
    CreateTranscriptLexemeResponse,
} from '../../../types';

export const contentApi = {
    getList(params: ContentListParams = {}): Promise<ContentListResponse> {
        return axios.get('/api/content', { params }).then((r) => r.data);
    },

    getOne(id: string | number): Promise<ContentOneResponse> {
        return axios.get(`/api/content/${id}`).then((r) => r.data);
    },

    getTranscript(contentId: string | number, params: { from_ms?: number; to_ms?: number } = {}): Promise<TranscriptResponse> {
        return axios.get(`/api/content/${contentId}/transcript`, { params }).then((r) => r.data);
    },

    getTranscriptTranslations(contentId: string | number): Promise<TranscriptTranslationsResponse> {
        return axios.get(`/api/content/${contentId}/transcript/translations`).then((r) => r.data);
    },

    /**
     * A learner tapped a transcript word with no ContentLexeme yet. Creates
     * (or reuses) it, links it to this exact occurrence, and returns its
     * translation (AI). Returns 503 when AI feature is disabled.
     */
    createTranscriptLexeme(
        contentId: string | number,
        payload: { transcript_segment_id: number; text: string; start_offset: number; end_offset: number },
    ): Promise<CreateTranscriptLexemeResponse> {
        return axios.post(`/api/content/${contentId}/transcript/lexemes`, payload).then((r) => r.data);
    },

    getCategories(): Promise<ContentCategoriesResponse> {
        return axios.get('/api/content/categories').then((r) => r.data);
    },

    getLexemes(contentId: string | number): Promise<ContentLexemesResponse> {
        return axios.get(`/api/content/${contentId}/lexemes`).then((r) => r.data);
    },

    markLexemeLearned(lexemeId: number): Promise<unknown> {
        return axios.post(`/api/content/lexemes/${lexemeId}/mark-learned`);
    },

    /** Remove a word from the user's learned list (undoes markLexemeLearned). */
    unmarkLexemeLearned(lexemeId: number): Promise<unknown> {
        return axios.post(`/api/content/lexemes/${lexemeId}/unmark-learned`);
    },

    /** Add a lexeme to the user's spaced-repetition review queue (creates an SrsCard). */
    startLexemeLearning(lexemeId: number): Promise<unknown> {
        return axios.post(`/api/content/lexemes/${lexemeId}/start-learning`);
    },

    /** Remove a lexeme from the user's spaced-repetition review queue (undoes startLexemeLearning). */
    stopLexemeLearning(lexemeId: number): Promise<unknown> {
        return axios.post(`/api/content/lexemes/${lexemeId}/stop-learning`);
    },

    /** Mark a word "not interested" — hides it from the learner's own word list. */
    skipLexeme(lexemeId: number): Promise<unknown> {
        return axios.post(`/api/content/lexemes/${lexemeId}/skip`);
    },

    /** Undo skipLexeme. */
    unskipLexeme(lexemeId: number): Promise<unknown> {
        return axios.post(`/api/content/lexemes/${lexemeId}/unskip`);
    },

    /** Bulk counterpart of markLexemeLearned (task 7.5). Never all-or-nothing — see BulkLexemeActionResponse. */
    bulkMarkLexemesLearned(ids: number[]): Promise<BulkLexemeActionResponse> {
        return axios.post('/api/content/lexemes/bulk-mark-learned', { ids }).then((r) => r.data);
    },

    /** Bulk counterpart of startLexemeLearning (task 7.5). */
    bulkStartLexemesLearning(ids: number[]): Promise<BulkLexemeActionResponse> {
        return axios.post('/api/content/lexemes/bulk-start-learning', { ids }).then((r) => r.data);
    },

    submitYoutube(payload: SubmitYoutubePayload): Promise<SubmitYoutubeResponse> {
        return axios.post('/api/content/submit-youtube', payload).then((r) => r.data);
    },

    getMySubmissions(): Promise<MySubmissionsResponse> {
        return axios.get('/api/content/my-submissions').then((r) => r.data);
    },

    /** Explain lexeme (AI). Returns 503 when AI feature is disabled. */
    explainLexeme(lexemeId: number): Promise<{ explanation: string }> {
        return axios.post(`/api/lexemes/${lexemeId}/explain`).then((r) => r.data);
    },

    /**
     * On-demand single example sentence for context practice (AI) — topic-steered
     * by the content the word belongs to, translated into the learner's own
     * `translation_language`. Returns 503 when AI feature is disabled.
     */
    generateSentence(lexemeId: number, force = false): Promise<{ sentence: string; target_form: string; translation: string; distractors?: string[] }> {
        return axios.post(`/api/lexemes/${lexemeId}/generate-sentence`, undefined, { params: force ? { force: 1 } : undefined }).then((r) => r.data);
    },

    prepareClozeExamples(contentLexemeIds: number[], variants = 3): Promise<{ prepared: number; total: number }> {
        return axios.post('/api/cloze-examples/prepare', { content_lexeme_ids: contentLexemeIds, variants }).then((r) => r.data);
    },

    /** Task 9.2: pending AI candidates for the content's own submitter. 403 for non-owners. */
    getAiSuggestions(contentId: string | number): Promise<AiSuggestionsResponse> {
        return axios.get(`/api/content/${contentId}/ai-suggestions`).then((r) => r.data);
    },

    /** Task 9.3: accept (and apply) pending AI candidates. 403 for non-owners. */
    acceptAiSuggestions(contentId: string | number, payload: AcceptAiSuggestionsPayload): Promise<AcceptAiSuggestionsResponse> {
        return axios.post(`/api/content/${contentId}/ai-suggestions/accept`, payload).then((r) => r.data);
    },

    /**
     * Manually re-triggers AI extraction for this content's own submitter —
     * same guards as the automatic post-processing dispatch (no duplicate
     * concurrent run, same daily quota). 403 for non-owners, 409 if a run is
     * already in progress, 429 if the daily quota is exhausted, 503 if AI is
     * disabled.
     */
    reanalyze(contentId: string | number): Promise<{ status: string }> {
        return axios.post(`/api/content/${contentId}/reanalyze`).then((r) => r.data);
    },
};
