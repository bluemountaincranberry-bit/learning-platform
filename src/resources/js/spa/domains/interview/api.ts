import axios from 'axios';
import type { InterviewAnswer, InterviewDraft, InterviewPracticeSession, InterviewPracticeSummary, InterviewProfile, InterviewQuestion, InterviewTopic } from './types';

type WireTopic = { id: number; name: string; parent_id: number | null; sort_order: number };
type WireAnswer = { id: number; en: string | null; ru: string | null; revisions: { id: number; text_en: string | null; text_ru: string | null; created_at: string }[] };
type WireQuestion = { id: number; prompt_en: string; prompt_ru: string | null; preparation_state: InterviewQuestion['preparationState']; topic: WireTopic | null; tags: string[]; answers: Partial<Record<'short' | 'full', WireAnswer>> };
type WireProfile = { id?: number; career_goal: string | null; skills: string[] | null; experience_level: string | null; projects: string[] | null; experience_stories: string[] | null; milestones: { id?: number; title: string; target_date: string | null }[] };
type WireSession = { id: number; conversation_id: number; mode: 'coached' | 'mock'; status: 'active' | 'completed'; question_count: number; focus: string | null; questions: WireQuestion[]; messages: { id: number; role: 'user' | 'assistant'; content: string }[] };
type WireSessionSummary = { id: number; mode: 'coached' | 'mock'; status: 'active' | 'completed'; updated_at: string };
type WireDraft = { id: number; kind: 'question' | 'profile' | 'answer' | 'vocabulary' | 'observation'; payload: Record<string, unknown>; status: 'pending' };

const mapTopic = (topic: WireTopic): InterviewTopic => ({ id: topic.id, name: topic.name, parentId: topic.parent_id, sortOrder: topic.sort_order });
const mapAnswer = (answer: WireAnswer): InterviewAnswer => ({ id: answer.id, en: answer.en, ru: answer.ru, revisions: answer.revisions.map(({ id, text_en, text_ru, created_at }) => ({ id, textEn: text_en, textRu: text_ru, createdAt: created_at })) });
const mapQuestion = (question: WireQuestion): InterviewQuestion => ({
    id: question.id, promptEn: question.prompt_en, promptRu: question.prompt_ru,
    preparationState: question.preparation_state, topic: question.topic ? mapTopic(question.topic) : null,
    tags: question.tags, answers: Object.fromEntries(Object.entries(question.answers).map(([key, value]) => [key, value ? mapAnswer(value) : value])),
});
const mapProfile = (profile: WireProfile): InterviewProfile => ({
    id: profile.id, careerGoal: profile.career_goal, skills: profile.skills, experienceLevel: profile.experience_level,
    projects: profile.projects, experienceStories: profile.experience_stories,
    milestones: profile.milestones.map(({ id, title, target_date }) => ({ id, title, targetDate: target_date })),
});
const profilePayload = (profile: InterviewProfile) => ({
    career_goal: profile.careerGoal, skills: profile.skills, experience_level: profile.experienceLevel,
    projects: profile.projects, experience_stories: profile.experienceStories,
    milestones: profile.milestones.map(({ id, title, targetDate }) => ({ id, title, target_date: targetDate })),
});
const mapSession = (session: WireSession): InterviewPracticeSession => ({
    id: session.id, conversationId: session.conversation_id, mode: session.mode, status: session.status,
    questionCount: session.question_count, focus: session.focus, questions: session.questions.map(mapQuestion),
    messages: session.messages,
});

const draftLabels: Record<string, string> = { career_goal: 'Career goal', skills: 'Skills', experience_level: 'Experience level', projects: 'Projects', experience_stories: 'Experience stories', milestones: 'Milestones' };
const isRecord = (value: unknown): value is Record<string, unknown> => typeof value === 'object' && value !== null && !Array.isArray(value);
const formatDraftValues = (key: string, value: unknown): string[] => {
    if (!Array.isArray(value)) return [value === null || value === '' ? '(empty — clears this value)' : typeof value === 'string' ? value : '—'];
    if (value.length === 0) return [key === 'milestones' ? '(no milestone changes)' : '(empty — clears this list)'];
    return value.flatMap((item) => {
        if (typeof item === 'string') return [item];
        if (!isRecord(item) || typeof item.title !== 'string') return [];
        return [typeof item.target_date === 'string' ? `${item.title} · ${item.target_date}` : item.title];
    });
};

export const interviewApi = {
    async drafts(): Promise<InterviewDraft[]> {
        const data = (await axios.get('/api/interview/drafts')).data.data as WireDraft[];
        return data.map((draft) => {
            if (draft.kind === 'question') return { id: draft.id, kind: 'question', promptEn: typeof draft.payload.prompt_en === 'string' ? draft.payload.prompt_en : '', promptRu: typeof draft.payload.prompt_ru === 'string' ? draft.payload.prompt_ru : null, topicId: typeof draft.payload.topic_id === 'number' ? draft.payload.topic_id : null, tags: Array.isArray(draft.payload.tags) ? draft.payload.tags.filter((tag): tag is string => typeof tag === 'string') : [] };
            if (draft.kind === 'answer') return { id: draft.id, kind: 'answer', questionPromptEn: typeof draft.payload.question_prompt_en === 'string' ? draft.payload.question_prompt_en : '', questionPromptRu: typeof draft.payload.question_prompt_ru === 'string' ? draft.payload.question_prompt_ru : null, variant: draft.payload.variant === 'full' ? 'full' : 'short', textEn: typeof draft.payload.text_en === 'string' ? draft.payload.text_en : null, textRu: typeof draft.payload.text_ru === 'string' ? draft.payload.text_ru : null };
            if (draft.kind === 'vocabulary') return { id: draft.id, kind: 'vocabulary', lemma: typeof draft.payload.lemma === 'string' ? draft.payload.lemma : '', language: 'en' };
            if (draft.kind === 'observation') return { id: draft.id, kind: 'observation', questionPromptEn: typeof draft.payload.question_prompt_en === 'string' ? draft.payload.question_prompt_en : '', questionPromptRu: typeof draft.payload.question_prompt_ru === 'string' ? draft.payload.question_prompt_ru : null, preparationState: draft.payload.preparation_state === 'confident' ? 'confident' : 'needs_practice', evidence: typeof draft.payload.evidence === 'string' ? draft.payload.evidence : '', reason: typeof draft.payload.reason === 'string' ? draft.payload.reason : '' };
            return { id: draft.id, kind: 'profile', changes: Object.entries(draft.payload).flatMap(([key, value]) => formatDraftValues(key, value).map((entry) => ({ label: draftLabels[key] ?? key, value: entry }))) };
        });
    },
    async decideDraft(id: number, decision: 'confirm' | 'reject'): Promise<void> {
        await axios.post(`/api/interview/drafts/${id}/${decision}`);
    },
    async topics(): Promise<InterviewTopic[]> {
        return ((await axios.get('/api/interview/topics')).data.data as WireTopic[]).map(mapTopic);
    },
    async tags(): Promise<string[]> {
        return (await axios.get('/api/interview/tags')).data.data;
    },
    async questions(params: Record<string, string> = {}): Promise<{ items: InterviewQuestion[]; hasMore: boolean }> {
        const page = (await axios.get('/api/interview/questions', { params })).data;
        return { items: (page.data as WireQuestion[]).map(mapQuestion), hasMore: page.current_page < page.last_page };
    },
    async createTopic(name: string, parentId: number | null = null): Promise<InterviewTopic> {
        return mapTopic((await axios.post('/api/interview/topics', { name, parent_id: parentId })).data.data);
    },
    async createQuestion(payload: { promptEn: string; promptRu: string | null; topicId: number | null; tags: string[] }): Promise<InterviewQuestion> {
        const wire = { prompt_en: payload.promptEn, prompt_ru: payload.promptRu, topic_id: payload.topicId, tags: payload.tags };
        return mapQuestion((await axios.post('/api/interview/questions', wire)).data.data);
    },
    async updateQuestion(id: number, payload: Partial<InterviewQuestion> & { answers?: Partial<Record<'short' | 'full', { en: string; ru: string }>>; tags?: string[]; topicId?: number | null }): Promise<InterviewQuestion> {
        const wire = {
            ...(payload.promptEn !== undefined ? { prompt_en: payload.promptEn } : {}),
            ...(payload.promptRu !== undefined ? { prompt_ru: payload.promptRu } : {}),
            ...(payload.preparationState !== undefined ? { preparation_state: payload.preparationState } : {}),
            ...(payload.tags !== undefined ? { tags: payload.tags } : {}),
            ...(payload.topicId !== undefined ? { topic_id: payload.topicId } : {}),
            ...(payload.answers !== undefined ? { answers: payload.answers } : {}),
        };
        return mapQuestion((await axios.put(`/api/interview/questions/${id}`, wire)).data.data);
    },
    async restoreRevision(questionId: number, answerId: number, revisionId: number): Promise<InterviewQuestion> {
        return mapQuestion((await axios.post(`/api/interview/questions/${questionId}/answers/${answerId}/revisions/${revisionId}/restore`)).data.data);
    },
    async profile(): Promise<InterviewProfile> {
        return mapProfile((await axios.get('/api/interview/profile')).data.data);
    },
    async saveProfile(profile: InterviewProfile): Promise<InterviewProfile> {
        return mapProfile((await axios.put('/api/interview/profile', profilePayload(profile))).data.data);
    },
    async startSession(mode: 'coached' | 'mock', questionIds: number[] = [], questionCount = Math.max(1, questionIds.length), topicId: number | null = null, focus: string | null = null): Promise<InterviewPracticeSession> {
        return mapSession((await axios.post('/api/interview/sessions', { mode, question_ids: questionIds, question_count: questionCount, topic_id: topicId, focus })).data.data);
    },
    async getSession(id: number): Promise<InterviewPracticeSession> {
        return mapSession((await axios.get(`/api/interview/sessions/${id}`)).data.data);
    },
    async sendSessionMessage(id: number, content: string): Promise<void> {
        await axios.post(`/api/interview/sessions/${id}/messages`, { content });
    },
    async completeSession(id: number): Promise<InterviewPracticeSession> {
        return mapSession((await axios.post(`/api/interview/sessions/${id}/complete`)).data.data);
    },
    async sessions(): Promise<InterviewPracticeSummary[]> {
        const page = (await axios.get('/api/interview/sessions')).data;
        return (page.data as WireSessionSummary[]).map((session) => ({ id: session.id, mode: session.mode, status: session.status, updatedAt: session.updated_at }));
    },
};
