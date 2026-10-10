import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { defineComponent, h } from 'vue';
import InterviewPage from '../InterviewPage.vue';

const api = vi.hoisted(() => ({ topics: vi.fn(), tags: vi.fn(), questions: vi.fn(), profile: vi.fn(), updateQuestion: vi.fn(), saveProfile: vi.fn(), restoreRevision: vi.fn(), startSession: vi.fn(), sendSessionMessage: vi.fn(), pinVoiceRecording: vi.fn(), sessions: vi.fn(), getSession: vi.fn(), drafts: vi.fn(), decideDraft: vi.fn() }));
const translation = vi.hoisted(() => ({ translate: vi.fn() }));
const speech = vi.hoisted(() => ({ providers: vi.fn().mockResolvedValue({ providers: [] }), transcribe: vi.fn(), pin: vi.fn(), flow: vi.fn().mockResolvedValue({ preferences: {} }) }));
vi.mock('../../domains/interview/api', () => ({ interviewApi: api }));
vi.mock('../../domains/ai/api/translateApi', () => ({ translateApi: translation }));
vi.mock('../../domains/learning', () => ({ speechApi: speech, learningFlowApi: { get: speech.flow } }));

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
        speech.providers.mockResolvedValue({ providers: [] });
        translation.translate.mockResolvedValue({ translation: 'Стабильный интерфейс.' });
        speech.flow.mockResolvedValue({ preferences: {} });
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
        expect(wrapper.find('h2.text-xl').element.parentElement?.parentElement?.className).toContain('flex-col');
        expect(wrapper.text()).not.toContain('Что такое API?');
        await wrapper.findAll('button').find((button) => button.text().includes('Show Russian'))!.trigger('click');
        expect(wrapper.text()).toContain('Что такое API?');
    });

    it('previews cross-session observation evidence and persists it only after confirmation', async () => {
        const observation = { id: 17, patternType: 'strength' as const, summary: 'You connect your action to a measurable outcome.', examples: [
            { session_id: 10, question_id: 2, question_prompt_en: 'What is an API?', evidence: 'I built a small API.', source_message_id: 101 },
            { session_id: 11, question_id: 3, question_prompt_en: 'Describe a challenge.', evidence: 'I fixed the timeout bug.', source_message_id: 102 },
        ] };
        api.profile.mockResolvedValue({ careerGoal: 'Copilot Studio Developer', milestones: [], observations: [observation] });
        api.drafts.mockResolvedValue([{ id: 44, kind: 'pattern_observation', patternType: 'strength', summary: observation.summary, examples: [
            { sessionId: 10, questionId: 2, questionPromptEn: 'What is an API?', evidence: 'I built a small API.' },
            { sessionId: 11, questionId: 3, questionPromptEn: 'Describe a challenge.', evidence: 'I fixed the timeout bug.' },
        ] }]);
        const wrapper = mount(InterviewPage);
        await flushPromises();

        expect(wrapper.text()).toContain('I built a small API.');
        expect(wrapper.text()).toContain('I fixed the timeout bug.');
        expect(wrapper.text()).not.toContain('Repeated coaching observations');
        await wrapper.findAll('button').find((button) => button.text() === 'Save observation')!.trigger('click');
        await flushPromises();

        expect(api.decideDraft).toHaveBeenCalledWith(44, 'confirm');
        expect(wrapper.text()).toContain('Repeated coaching observation saved to your profile.');
        expect(wrapper.text()).toContain('You connect your action to a measurable outcome.');
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

    it('previews a translation and keeps it out of saved text until the learner applies and saves it', async () => {
        const wrapper = mount(InterviewPage);
        await flushPromises();
        const details = wrapper.find('[aria-label="Question details"]');
        await details.findAll('button').find((button) => button.text() === 'Edit question')!.trigger('click');
        const fields = details.findAll('textarea');
        await fields[0].setValue('A stable interface.');
        await details.findAll('button').find((button) => button.text() === 'Suggest Russian translation')!.trigger('click');
        await flushPromises();

        expect(translation.translate).toHaveBeenCalledWith('A stable interface.', 'ru');
        expect(wrapper.text()).toContain('Стабильный интерфейс.');
        expect(api.updateQuestion).not.toHaveBeenCalled();
        await wrapper.find('[aria-label="Translation proposal review"]').findAll('button')[0].trigger('click');
        expect((fields[1].element as HTMLTextAreaElement).value).toBe('Стабильный интерфейс.');
        expect(api.updateQuestion).not.toHaveBeenCalled();
        await details.findAll('button').find((button) => button.text() === 'Save question')!.trigger('click');
        await flushPromises();

        expect(api.updateQuestion).toHaveBeenCalledWith(2, expect.objectContaining({ promptRu: 'Стабильный интерфейс.' }));
    });

    it('can suggest English from Russian and discard without changing the saved question', async () => {
        translation.translate.mockResolvedValue({ translation: 'What does this API do?' });
        const wrapper = mount(InterviewPage);
        await flushPromises();
        const details = wrapper.find('[aria-label="Question details"]');
        await details.findAll('button').find((button) => button.text() === 'Edit question')!.trigger('click');
        const fields = details.findAll('textarea');
        await fields[1].setValue('Что делает этот API?');
        await details.findAll('button').find((button) => button.text() === 'Suggest English translation')!.trigger('click');
        await flushPromises();

        expect(translation.translate).toHaveBeenCalledWith('Что делает этот API?', 'en');
        expect(wrapper.find('[aria-label="Translation proposal review"]').text()).toContain('What does this API do?');
        await wrapper.find('[aria-label="Translation proposal review"]').findAll('button')[1].trigger('click');
        expect(api.updateQuestion).not.toHaveBeenCalled();
        expect((fields[0].element as HTMLTextAreaElement).value).toBe('What is an API?');
    });

    it('starts a coached session with the selected question', async () => {
        api.startSession.mockResolvedValue({ id: 9, conversationId: 11, mode: 'coached', status: 'active', questionCount: 1, focus: null, difficulty: 'any', questions: [structuredClone(question)], messages: [] });
        const wrapper = mount(InterviewPage);
        await flushPromises();
        await wrapper.find('[aria-label="Practice difficulty"]').setValue('advanced');
        await wrapper.findAll('button').find((button) => button.text().includes('Start coached practice'))!.trigger('click');
        await flushPromises();

        expect(api.startSession).toHaveBeenCalledWith('coached', [2], 1, null, null, 'advanced');
        expect(wrapper.text()).toContain('Coached practice');
        expect(wrapper.find('#interview-practice-message').exists()).toBe(true);
    });

    it('shows a Continue action and evidence-based topics to revisit on the preparation home', async () => {
        const session = { id: 19, conversationId: 23, mode: 'coached' as const, status: 'active' as const, questionCount: 1, focus: null, difficulty: 'any' as const, questions: [structuredClone(question)], messages: [] };
        api.questions.mockResolvedValue({ items: [{ ...structuredClone(question), preparationState: 'needs_practice' }], hasMore: false });
        api.sessions.mockResolvedValue([{ id: 19, mode: 'coached', status: 'active', updatedAt: '2026-10-09T12:00:00Z' }]);
        api.getSession.mockResolvedValue(session);
        const wrapper = mount(InterviewPage);
        await flushPromises();

        expect(wrapper.find('[aria-label="Interview preparation home"]').text()).toContain('Suggested topics to revisit:');
        expect(wrapper.text()).toContain('HTTP · 1');
        await wrapper.findAll('button').find((button) => button.text() === 'Continue practice')!.trigger('click');
        await flushPromises();

        expect(api.getSession).toHaveBeenCalledWith(19);
        expect(wrapper.text()).toContain('Coached practice');
    });

    it('includes revisit topics found on later question-bank pages', async () => {
        api.topics.mockResolvedValue([
            { id: 1, name: 'HTTP', parentId: null, sortOrder: 0 },
            { id: 2, name: 'Databases', parentId: null, sortOrder: 1 },
        ]);
        api.questions.mockImplementation(async (params: Record<string, string> = {}) => {
            if (params.per_page !== '100') return { items: [structuredClone(question)], hasMore: false };
            if (params.page === '1') return { items: [structuredClone(question)], hasMore: true };
            return { items: [{ ...structuredClone(question), id: 200, preparationState: 'needs_practice', topic: { id: 2, name: 'Databases', parentId: null, sortOrder: 1 } }], hasMore: false };
        });
        const wrapper = mount(InterviewPage);
        await flushPromises();

        expect(wrapper.text()).toContain('Databases · 1');
        expect(api.questions).toHaveBeenCalledWith({ per_page: '100', page: '2' });
    });

    it('keeps the learner answer available when the message provider rejects the send', async () => {
        api.startSession.mockResolvedValue({ id: 9, conversationId: 11, mode: 'coached', status: 'active', questionCount: 1, focus: null, difficulty: 'any', questions: [structuredClone(question)], messages: [] });
        api.sendSessionMessage.mockRejectedValue(new Error('provider unavailable'));
        const wrapper = mount(InterviewPage);
        await flushPromises();
        await wrapper.findAll('button').find((button) => button.text().includes('Start coached practice'))!.trigger('click');
        await flushPromises();
        const answer = wrapper.find('#interview-practice-message');
        await answer.setValue('I built a small API.');
        await wrapper.find('form').trigger('submit');
        await flushPromises();

        expect(wrapper.findAll('[role="alert"]').some((alert) => alert.text().includes('Your answer is still here'))).toBe(true);
        expect((wrapper.find('#interview-practice-message').element as HTMLTextAreaElement).value).toBe('I built a small API.');
    });

    it('explains when an accepted answer gets no agent reply and allows a session refresh', async () => {
        vi.useFakeTimers();
        try {
            const session = { id: 9, conversationId: 11, mode: 'coached', status: 'active', questionCount: 1, focus: null, difficulty: 'any', questions: [structuredClone(question)], messages: [] };
            api.startSession.mockResolvedValue(session);
            api.sendSessionMessage.mockResolvedValue(undefined);
            api.getSession.mockResolvedValue(session);
            const wrapper = mount(InterviewPage);
            await flushPromises();
            await wrapper.findAll('button').find((button) => button.text().includes('Start coached practice'))!.trigger('click');
            await flushPromises();
            await wrapper.find('#interview-practice-message').setValue('I built an API for a class project.');
            await wrapper.find('form').trigger('submit');
            await flushPromises();
            await vi.advanceTimersByTimeAsync(15_000);
            await flushPromises();

            expect(wrapper.findAll('[role="alert"]').some((alert) => alert.text().includes('Your answer was saved, but the Interview Agent has not replied yet'))).toBe(true);
            expect(wrapper.findAll('button').some((button) => button.text() === 'Refresh session')).toBe(true);
            await wrapper.findAll('button').find((button) => button.text() === 'Refresh session')!.trigger('click');
            await flushPromises();
            expect(api.getSession).toHaveBeenCalledWith(9);
        } finally {
            vi.useRealTimers();
        }
    });

    it('lets a learner edit the transcribed answer before attaching the recording', async () => {
        const session = { id: 9, conversationId: 11, mode: 'coached', status: 'active', questionCount: 1, focus: null, difficulty: 'any', questions: [structuredClone(question)], messages: [] };
        api.startSession.mockResolvedValue(session);
        api.sendSessionMessage.mockResolvedValue(undefined);
        api.getSession.mockResolvedValue({ ...session, messages: [{ id: 21, role: 'assistant', content: 'Tell me more.', voiceAudioUrl: null, voiceAudioPinned: false, voiceAudioExpiresAt: null }] });
        const voiceStub = defineComponent({
            emits: ['ready', 'cleared', 'retention'],
            setup(_, { emit }) { return () => h('div', [
                h('button', { type: 'button', onClick: () => emit('ready', 'Raw transcript', new Blob(['audio']), 'local_whisper', 'en', false) }, 'Use voice transcript'),
                h('button', { type: 'button', onClick: () => emit('retention', true) }, 'Keep voice forever'),
            ]); },
        });
        const wrapper = mount(InterviewPage, { global: { stubs: { VoiceDictationControl: voiceStub } } });
        await flushPromises();
        await wrapper.findAll('button').find((button) => button.text().includes('Start coached practice'))!.trigger('click');
        await flushPromises();
        await wrapper.findAll('button').find((button) => button.text() === 'Use voice transcript')!.trigger('click');
        await wrapper.findAll('button').find((button) => button.text() === 'Keep voice forever')!.trigger('click');
        await flushPromises();
        const answer = wrapper.find('#interview-practice-message');
        await answer.setValue('Edited transcript before sending.');
        await wrapper.find('form').trigger('submit');
        await flushPromises();

        expect(api.sendSessionMessage).toHaveBeenCalledWith(9, 'Edited transcript before sending.', expect.objectContaining({ provider: 'local_whisper', language: 'en', keepForever: true }));
        expect(api.sendSessionMessage.mock.calls[0][2].audio).toBeInstanceOf(Blob);
    });

    it('reopens a saved session from recent practice history', async () => {
        const feedback = '**Содержание:** Вы назвали API и тесты для таймаутов.\n\n**English improvement:** “I added timeout tests.”';
        const session = { id: 9, conversationId: 11, mode: 'mock', status: 'completed', questionCount: 1, focus: null, difficulty: 'any', questions: [structuredClone(question)], messages: [{ id: 21, role: 'assistant', content: feedback }] } as const;
        api.sessions.mockResolvedValue([{ id: 9, mode: 'mock', status: 'completed', updatedAt: '2026-10-10' }]);
        api.getSession.mockResolvedValue(session);
        const wrapper = mount(InterviewPage);
        await flushPromises();
        await wrapper.findAll('button').find((button) => button.text().includes('Mock interview · completed'))!.trigger('click');
        await flushPromises();

        expect(api.getSession).toHaveBeenCalledWith(9);
        expect(wrapper.text()).toContain('Содержание');
        expect(wrapper.text()).toContain('Вы назвали API и тесты для таймаутов.');
        expect(wrapper.text()).toContain('I added timeout tests.');
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

    it('shows the evidence and reason before changing a question preparation state', async () => {
        api.drafts.mockResolvedValue([{ id: 45, kind: 'observation', questionPromptEn: 'How did you handle an API timeout?', questionPromptRu: 'Как вы обрабатывали таймаут API?', preparationState: 'needs_practice', evidence: 'I added timeout tests to the API client.', reason: 'You described the test change, but not how you handled the timeout itself.' }]);
        api.decideDraft.mockImplementation(async () => { api.drafts.mockResolvedValue([]); });
        const wrapper = mount(InterviewPage);
        await flushPromises();

        expect(wrapper.text()).toContain('I added timeout tests to the API client.');
        expect(wrapper.text()).toContain('You described the test change, but not how you handled the timeout itself.');
        expect(wrapper.text()).toContain('Suggested state: needs practice');
        await wrapper.findAll('button').find((button) => button.text() === 'Update question state')!.trigger('click');
        await flushPromises();

        expect(api.decideDraft).toHaveBeenCalledWith(45, 'confirm');
        expect(wrapper.text()).toContain('Question state updated to needs practice.');
    });
});
