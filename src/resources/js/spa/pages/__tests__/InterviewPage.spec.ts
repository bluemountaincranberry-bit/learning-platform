import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import InterviewPage from '../InterviewPage.vue';

const api = vi.hoisted(() => ({ topics: vi.fn(), tags: vi.fn(), questions: vi.fn(), profile: vi.fn(), updateQuestion: vi.fn(), saveProfile: vi.fn(), restoreRevision: vi.fn(), startSession: vi.fn(), sessions: vi.fn(), getSession: vi.fn(), drafts: vi.fn(), decideDraft: vi.fn() }));
vi.mock('../../domains/interview/api', () => ({ interviewApi: api }));

const question = {
    id: 2, promptEn: 'What is an API?', promptRu: 'Что такое API?', preparationState: 'unpracticed',
    topic: { id: 1, name: 'HTTP', parentId: null, sortOrder: 0 }, tags: ['REST'],
    answers: {
        short: { id: 10, en: 'A contract.', ru: 'Контракт.', revisions: [] },
        full: { id: 11, en: null, ru: null, revisions: [] },
    },
};

describe('InterviewPage', () => {
    beforeEach(() => {
        vi.resetAllMocks();
        api.topics.mockResolvedValue([{ id: 1, name: 'HTTP', parentId: null, sortOrder: 0 }]);
        api.tags.mockResolvedValue(['REST']);
        api.questions.mockResolvedValue({ items: [structuredClone(question)], hasMore: false });
        api.profile.mockResolvedValue({ careerGoal: 'Copilot Studio Developer', milestones: [] });
        api.sessions.mockResolvedValue([]);
        api.drafts.mockResolvedValue([]);
        api.updateQuestion.mockImplementation(async (_id: number, payload: { answers: { short: { en: string; ru: string } } }) => ({
            ...structuredClone(question), answers: { ...structuredClone(question.answers), short: { ...question.answers.short, ...payload.answers.short } },
        }));
    });

    it('shows bilingual bank search controls and lets a learner reveal the translation', async () => {
        const wrapper = mount(InterviewPage);
        await flushPromises();

        expect(wrapper.text()).toContain('What is an API?');
        expect(wrapper.find('input[placeholder="Search in English or Russian"]').exists()).toBe(true);
        expect(wrapper.text()).not.toContain('Что такое API?');
        await wrapper.findAll('button').find((button) => button.text().includes('Show Russian'))!.trigger('click');
        expect(wrapper.text()).toContain('Что такое API?');
    });

    it('saves both language fields as one revisionable answer variant', async () => {
        const wrapper = mount(InterviewPage);
        await flushPromises();
        const fields = wrapper.find('[aria-label="Question details"]').findAll('textarea');
        await fields[0].setValue('An interface for software.');
        await fields[1].setValue('Интерфейс для программ.');
        await wrapper.findAll('button').find((button) => button.text().includes('Save short answer'))!.trigger('click');
        await flushPromises();

        expect(api.updateQuestion).toHaveBeenCalledWith(2, {
            answers: { short: { en: 'An interface for software.', ru: 'Интерфейс для программ.' } },
        });
    });

    it('starts a coached session with the selected question', async () => {
        api.startSession.mockResolvedValue({ id: 9, conversationId: 11, mode: 'coached', status: 'active', questionCount: 1, focus: null, questions: [structuredClone(question)], messages: [] });
        const wrapper = mount(InterviewPage);
        await flushPromises();
        await wrapper.findAll('button').find((button) => button.text().includes('Start coached practice'))!.trigger('click');
        await flushPromises();

        expect(api.startSession).toHaveBeenCalledWith('coached', [2], 1, null, null);
        expect(wrapper.text()).toContain('Coached practice');
        expect(wrapper.find('#interview-practice-message').exists()).toBe(true);
    });

    it('reopens a saved session from recent practice history', async () => {
        const session = { id: 9, conversationId: 11, mode: 'mock', status: 'completed', questionCount: 1, focus: null, questions: [structuredClone(question)], messages: [{ id: 21, role: 'assistant', content: 'Your examples were clear.' }] } as const;
        api.sessions.mockResolvedValue([{ id: 9, mode: 'mock', status: 'completed', updatedAt: '2026-10-10' }]);
        api.getSession.mockResolvedValue(session);
        const wrapper = mount(InterviewPage);
        await flushPromises();
        await wrapper.findAll('button').find((button) => button.text().includes('Mock interview · completed'))!.trigger('click');
        await flushPromises();

        expect(api.getSession).toHaveBeenCalledWith(9);
        expect(wrapper.text()).toContain('Your examples were clear.');
    });

    it('requires explicit learner confirmation before adding an AI question proposal', async () => {
        api.drafts.mockResolvedValue([{ id: 41, kind: 'question', promptEn: 'How do you handle a timeout?', promptRu: 'Как вы обрабатываете таймаут?', topicId: 1, tags: ['reliability'] }]);
        api.decideDraft.mockImplementation(async () => { api.drafts.mockResolvedValue([]); });
        const wrapper = mount(InterviewPage);
        await flushPromises();

        expect(wrapper.text()).toContain('How do you handle a timeout?');
        expect(wrapper.text()).toContain('review before adding');
        expect(wrapper.text()).toContain('HTTP · reliability');
        await wrapper.findAll('button').find((button) => button.text() === 'Add question')!.trigger('click');
        await flushPromises();

        expect(api.decideDraft).toHaveBeenCalledWith(41, 'confirm');
        expect(wrapper.text()).not.toContain('How do you handle a timeout?');
    });

    it('shows the exact AI profile changes before saving them', async () => {
        api.drafts.mockResolvedValue([{ id: 42, kind: 'profile', changes: [
            { label: 'Career goal', value: 'Junior developer' },
            { label: 'Experience stories', value: 'I built a study project.' },
            { label: 'Skills', value: '(empty — clears this list)' },
        ] }]);
        api.decideDraft.mockResolvedValue(undefined);
        const wrapper = mount(InterviewPage);
        await flushPromises();

        expect(wrapper.text()).toContain('Junior developer');
        expect(wrapper.text()).toContain('I built a study project.');
        expect(wrapper.text()).toContain('(empty — clears this list)');
        expect(wrapper.text()).toContain('Save profile updates');
        await wrapper.findAll('button').find((button) => button.text() === 'Save profile updates')!.trigger('click');
        await flushPromises();

        expect(api.decideDraft).toHaveBeenCalledWith(42, 'confirm');
    });

    it('previews both languages of an AI answer revision before saving it', async () => {
        api.drafts.mockResolvedValue([{ id: 43, kind: 'answer', questionPromptEn: 'Describe a project you built.', questionPromptRu: 'Опишите проект, который вы создали.', variant: 'short', textEn: 'I built an app.', textRu: 'Я сделал приложение.' }]);
        api.decideDraft.mockResolvedValue(undefined);
        const wrapper = mount(InterviewPage);
        await flushPromises();

        expect(wrapper.text()).toContain('Describe a project you built. · short answer');
        expect(wrapper.text()).toContain('I built an app.');
        expect(wrapper.text()).toContain('Я сделал приложение.');
        await wrapper.findAll('button').find((button) => button.text() === 'Save answer revision')!.trigger('click');
        await flushPromises();

        expect(api.decideDraft).toHaveBeenCalledWith(43, 'confirm');
    });

    it('requires confirmation before adding an AI vocabulary suggestion', async () => {
        api.drafts.mockResolvedValue([{ id: 44, kind: 'vocabulary', lemma: 'resilient', language: 'en' }]);
        api.decideDraft.mockResolvedValue(undefined);
        const wrapper = mount(InterviewPage);
        await flushPromises();

        expect(wrapper.text()).toContain('Add resilient to your English vocabulary?');
        await wrapper.findAll('button').find((button) => button.text() === 'Add to My words')!.trigger('click');
        await flushPromises();

        expect(api.decideDraft).toHaveBeenCalledWith(44, 'confirm');
        expect(wrapper.text()).toContain('Added “resilient” to My words.');
    });
});
