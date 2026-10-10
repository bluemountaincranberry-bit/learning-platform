import axios from 'axios';
import type { InterviewAnswer, InterviewProfile, InterviewQuestion, InterviewTopic } from './types';

type WireTopic = { id: number; name: string; parent_id: number | null; sort_order: number };
type WireAnswer = { id: number; en: string | null; ru: string | null; revisions: { id: number; text_en: string | null; text_ru: string | null; created_at: string }[] };
type WireQuestion = { id: number; prompt_en: string; prompt_ru: string | null; preparation_state: InterviewQuestion['preparationState']; topic: WireTopic | null; tags: string[]; answers: Partial<Record<'short' | 'full', WireAnswer>> };
type WireProfile = { id?: number; career_goal: string | null; skills: string[] | null; experience_level: string | null; projects: string[] | null; experience_stories: string[] | null; milestones: { id?: number; title: string; target_date: string | null }[] };

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

export const interviewApi = {
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
};
