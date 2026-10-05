import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { createRouter, createMemoryHistory } from 'vue-router';
import ChatPage from '../../../pages/ChatPage.vue';
import LessonDetailPage from '../../../pages/LessonDetailPage.vue';
const api = vi.hoisted(() => ({ createConversation: vi.fn(), streamMessage: vi.fn(), get: vi.fn(), listMessages: vi.fn(), sendMessage: vi.fn(), update: vi.fn(), destroy: vi.fn(), restore: vi.fn(), speak: vi.fn() }));
vi.mock('../../../shared/lib/speech', () => ({ isSpeechSupported: () => true, speak: api.speak }));
vi.mock('../../../domains/ai', () => ({ tutorApi: api }));
vi.mock('../../../domains/user', () => ({ useAuthStore: () => ({ isAuthenticated: true, canAccessTutorAgent: true }) }));
vi.mock('../../../domains/learning', () => ({ lessonApi: api }));
async function renderPage(component: typeof ChatPage | typeof LessonDetailPage, path: string) {
    const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/chat', component: ChatPage }, { path: '/lessons/:id', component: LessonDetailPage }] });
    await router.push(path);
    return mount(component, { global: { plugins: [router], stubs: { QuizCard: true } } });
}
function button(wrapper: ReturnType<typeof mount>, text: string) { return wrapper.findAll('button').find((node) => node.text() === text)!; }
describe('chat page retry seams', () => {
    beforeEach(() => { vi.resetAllMocks(); Element.prototype.scrollIntoView = vi.fn(); });
    it('retries tutor text with its original entry context and prevents concurrent sends', async () => {
        api.createConversation.mockResolvedValue({ conversation_id: 9 });
        api.streamMessage.mockRejectedValueOnce(new Error('offline')).mockImplementationOnce(async (_id, _text, chunk) => { chunk('**Ready** [run](/word/42)'); return { quiz: [] }; });
        const wrapper = await renderPage(ChatPage, '/chat?context_type=lexeme&context_id=42&context_title=run');
        await wrapper.get('input').setValue('Explain this');
        await button(wrapper, 'Send').trigger('click');
        await button(wrapper, 'Send').trigger('click');
        await flushPromises();
        expect(api.streamMessage).toHaveBeenCalledTimes(1);
        await button(wrapper, 'Try again').trigger('click');
        await flushPromises();
        expect(api.streamMessage).toHaveBeenCalledTimes(2);
        expect(api.streamMessage.mock.calls[1][1]).toBe('[The student is asking about this word: "run"]\n\nExplain this');
        expect(api.streamMessage.mock.calls[1][3]).toEqual({ context_type: 'lexeme', context_ref_id: 42, context_label: 'run' });
        expect(wrapper.get('strong').text()).toBe('Ready');
    });
    it('refreshes after an accepted lesson send fails to load, without resending the message', async () => {
        api.get.mockResolvedValue({ id: 3, title: 'Lesson', lexemes: [], grammar: [] });
        api.listMessages.mockResolvedValueOnce({ messages: [], is_waiting: false }).mockRejectedValueOnce(new Error('offline')).mockResolvedValue({ messages: [{ id: 1, role: 'user', content: 'Notes', attachment_name: 'notes.pdf' }], is_waiting: false });
        api.sendMessage.mockResolvedValue(undefined);
        const wrapper = await renderPage(LessonDetailPage, '/lessons/3');
        await flushPromises();
        // Switch to Chat tab first
        const chatTab = wrapper.findAll('button[role="tab"]').find((node) => node.text() === 'Chat');
        if (chatTab) await chatTab.trigger('click');
        await flushPromises();
        await wrapper.get('input[placeholder="What did you learn today?"]').setValue('Notes');
        await button(wrapper, 'Send').trigger('click');
        await flushPromises();
        await button(wrapper, 'Try again').trigger('click');
        await flushPromises();
        expect(api.sendMessage).toHaveBeenCalledTimes(1);
        expect(wrapper.text()).toContain('Notes');
        expect(wrapper.text()).toContain('Attached');
    });
    it('retains a failed lesson attachment and retries the original text and file', async () => {
        api.get.mockResolvedValue({ id: 3, title: 'Lesson', lexemes: [], grammar: [] });
        api.listMessages.mockResolvedValue({ messages: [], is_waiting: false });
        api.sendMessage.mockRejectedValueOnce(new Error('offline')).mockResolvedValueOnce(undefined);
        const wrapper = await renderPage(LessonDetailPage, '/lessons/3');
        await flushPromises();
        // Switch to Chat tab first
        const chatTab = wrapper.findAll('button[role="tab"]').find((node) => node.text() === 'Chat');
        if (chatTab) await chatTab.trigger('click');
        await flushPromises();
        const file = new File(['notes'], 'notes.pdf', { type: 'application/pdf' });
        Object.defineProperty(wrapper.get('input[type="file"]').element, 'files', { value: [file] });
        await wrapper.get('input[type="file"]').trigger('change');
        await wrapper.get('input[placeholder="What did you learn today?"]').setValue('Original notes');
        await button(wrapper, 'Send').trigger('click');
        await flushPromises();
        expect(wrapper.text()).toContain('notes.pdf');
        await wrapper.get('input[placeholder="What did you learn today?"]').setValue('Edited draft');
        await button(wrapper, 'Try again').trigger('click');
        await flushPromises();
        expect(api.sendMessage.mock.calls[1]).toEqual([3, 'Original notes', file]);
    });

    it('pronounces lesson words and examples in their source language', async () => {
        api.get.mockResolvedValue({ id: 3, title: 'French lesson', grammar: [], lexemes: [{ id: 4, text: 'bonjour', language: 'fr', example: 'Bonjour tout le monde.', example_translation: 'Hello everyone.', status: 'new' }] });
        api.listMessages.mockResolvedValue({ messages: [], is_waiting: false });
        const wrapper = await renderPage(LessonDetailPage, '/lessons/3');
        await flushPromises();
        // Switch to Words tab first (pronunciation test is on words)
        const wordsTab = wrapper.findAll('button[role="tab"]').find((node) => node.text() === 'Words');
        if (wordsTab) await wordsTab.trigger('click');
        await flushPromises();
        await wrapper.get('button[aria-label="bonjour. Show details"]').trigger('click');
        await wrapper.get('button[aria-label="Pronounce bonjour"]').trigger('click');
        expect(api.speak).toHaveBeenCalledWith('bonjour', 'fr');
        await wrapper.get('button[aria-label="Pronounce Bonjour tout le monde."]').trigger('click');
        expect(api.speak).toHaveBeenCalledWith('Bonjour tout le monde.', 'fr');
    });

    it('saves editable lesson fields together from the lesson header', async () => {
        api.get.mockResolvedValue({
            id: 3, title: 'Lesson', lesson_date: '2026-10-05T00:00:00.000000Z', teacher: 'Marie',
            topic: 'Travel', language: 'fr', tags: ['speaking'], notes: 'Original', homework: '',
            grammar: [], lexemes: [],
        });
        api.listMessages.mockResolvedValue({ messages: [], is_waiting: false });
        api.update.mockResolvedValue(undefined);
        const wrapper = await renderPage(LessonDetailPage, '/lessons/3');
        await flushPromises();

        await wrapper.get('#lesson-title').setValue('French class');
        await wrapper.get('#lesson-date').setValue('2026-10-12');
        await wrapper.get('#lesson-teacher').setValue('Marie');
        await wrapper.get('#lesson-topic').setValue('At the station');
        await wrapper.get('#lesson-language').setValue('ja');
        await wrapper.get('#lesson-tags').setValue('travel, review');
        await wrapper.get('textarea').setValue('Updated notes');
        await button(wrapper, 'Save').trigger('click');
        await flushPromises();

        expect(api.update).toHaveBeenCalledWith(3, {
            title: 'French class', lesson_date: '2026-10-12', teacher: 'Marie', topic: 'At the station',
            language: 'ja', tags: ['travel', 'review'], notes: 'Updated notes', homework: '',
        });
    });

    it('archives and restores the lesson without deleting it', async () => {
        api.get.mockResolvedValue({ id: 3, title: 'Lesson', status: 'active', language: 'en', tags: [], notes: '', homework: '', grammar: [], lexemes: [] });
        api.listMessages.mockResolvedValue({ messages: [], is_waiting: false });
        api.destroy.mockResolvedValue(undefined);
        api.restore.mockResolvedValue(undefined);
        const wrapper = await renderPage(LessonDetailPage, '/lessons/3');
        await flushPromises();

        await button(wrapper, 'Archive lesson').trigger('click');
        await flushPromises();
        expect(api.destroy).toHaveBeenCalledWith(3);
        await button(wrapper, 'Restore lesson').trigger('click');
        await flushPromises();
        expect(api.restore).toHaveBeenCalledWith(3);
    });

    it('shows the corrections placeholder without taking work from VIK-17', async () => {
        api.get.mockResolvedValue({ id: 3, title: 'Lesson', status: 'active', language: 'en', tags: [], notes: '', homework: '', grammar: [], lexemes: [] });
        api.listMessages.mockResolvedValue({ messages: [], is_waiting: false });
        const wrapper = await renderPage(LessonDetailPage, '/lessons/3');
        await flushPromises();

        const correctionsTab = wrapper.findAll('button[role="tab"]').find((node) => node.text() === 'Corrections');
        expect(correctionsTab).toBeDefined();
        await correctionsTab!.trigger('click');
        expect(wrapper.text()).toContain('Manual corrections will be available in VIK-17.');
    });

});
