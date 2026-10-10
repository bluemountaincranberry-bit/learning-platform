import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import InterviewPage from '../InterviewPage.vue';

const api = vi.hoisted(() => ({ topics: vi.fn(), tags: vi.fn(), questions: vi.fn(), profile: vi.fn(), updateQuestion: vi.fn(), saveProfile: vi.fn(), restoreRevision: vi.fn() }));
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
});
