import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { createRouter, createMemoryHistory } from 'vue-router';
import ChatPage from '../../../pages/ChatPage.vue';
import LessonDetailPage from '../../../pages/LessonDetailPage.vue';
const api = vi.hoisted(() => ({
    createConversation: vi.fn(), streamMessage: vi.fn(), get: vi.fn(), listMessages: vi.fn(), sendMessage: vi.fn(), update: vi.fn(),
    destroy: vi.fn(), restore: vi.fn(), speak: vi.fn(), createLexeme: vi.fn(), updateLexeme: vi.fn(), deleteLexeme: vi.fn(), restoreLexeme: vi.fn(),
    createGrammar: vi.fn(), updateGrammar: vi.fn(), deleteGrammar: vi.fn(), restoreGrammar: vi.fn(), addGrammarToMyGrammar: vi.fn(), addLexemeToMyWords: vi.fn(),
    createCorrection: vi.fn(), updateCorrection: vi.fn(), deleteCorrection: vi.fn(), restoreCorrection: vi.fn(),
}));
vi.mock('../../../shared/lib/speech', () => ({ isSpeechSupported: () => true, speak: api.speak }));
vi.mock('../../../domains/ai', () => ({ tutorApi: api }));
vi.mock('../../../domains/user', () => ({ useAuthStore: () => ({ isAuthenticated: true, canAccessTutorAgent: true }) }));
vi.mock('../../../domains/learning', () => ({ lessonApi: api }));
async function renderPage(component: typeof ChatPage | typeof LessonDetailPage, path: string) {
    const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/chat', component: ChatPage }, { path: '/lessons/:id', component: LessonDetailPage }, { path: '/grammar/:id', name: 'grammar.details', component: { template: '<div />' } }, { path: '/grammar/:id/practice', name: 'grammar.practice', component: { template: '<div />' } }, { path: '/repetitions', name: 'repetitions', component: { template: '<div />' } }, { path: '/word/:id', name: 'word.details', component: { template: '<div />' } }] });
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

        await wrapper.findAll('button[role="tab"]').find((node) => node.text() === 'Notes')!.trigger('click');
        await button(wrapper, 'Edit details').trigger('click');
        await wrapper.get('#lesson-title').setValue('French class');
        await wrapper.get('#lesson-date').setValue('2026-10-12');
        await wrapper.get('#lesson-teacher').setValue('Marie');
        await wrapper.get('#lesson-topic').setValue('At the station');
        await wrapper.get('#lesson-language').setValue('ja');
        await wrapper.get('#lesson-tags').setValue('travel, review');
        await wrapper.get('textarea').setValue('Updated notes');
        await button(wrapper, 'Save details').trigger('click');
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

    it('adds, edits and removes lesson words with an undo action', async () => {
        api.get.mockResolvedValue({ id: 3, title: 'Lesson', status: 'active', language: 'en', tags: [], notes: '', homework: '', grammar: [], lexemes: [], corrections: [] });
        api.listMessages.mockResolvedValue({ messages: [], is_waiting: false });
        api.createLexeme.mockResolvedValue({ id: 4, text: 'look after', type: 'phrasal_verb', language: 'en', translation: 'заботиться', level: 'B1', status: 'new', matched_lexeme_id: null, example: null, example_translation: null, source: 'manual' });
        api.updateLexeme.mockResolvedValue({ id: 4, text: 'look after', type: 'phrasal_verb', language: 'en', translation: 'присматривать', level: 'B1', status: 'new', matched_lexeme_id: null, example: null, example_translation: null, source: 'manual' });
        api.deleteLexeme.mockResolvedValue(undefined);
        api.restoreLexeme.mockResolvedValue({ id: 4, text: 'look after', type: 'phrasal_verb', language: 'en', translation: 'присматривать', level: 'B1', status: 'new', matched_lexeme_id: null, example: null, example_translation: null, source: 'manual' });
        const wrapper = await renderPage(LessonDetailPage, '/lessons/3');
        await flushPromises();

        await wrapper.findAll('button[role="tab"]').find((node) => node.text() === 'Words')!.trigger('click');
        await button(wrapper, 'Add word').trigger('click');
        await wrapper.get('input[placeholder="e.g. look after"]').setValue('look after');
        await wrapper.get('input[placeholder="Translation"]').setValue('заботиться');
        await wrapper.find('form').trigger('submit');
        await flushPromises();
        expect(api.createLexeme).toHaveBeenCalledWith(3, expect.objectContaining({ text: 'look after', translation: 'заботиться' }));
        expect(wrapper.text()).toContain('look after');

        const wordButton = wrapper.findAll('button').find((node) => node.attributes('aria-label')?.startsWith('look after'))!;
        await wordButton.trigger('click');
        await button(wrapper, 'Edit').trigger('click');
        await wrapper.get('input[placeholder="Translation"]').setValue('присматривать');
        await wrapper.find('form').trigger('submit');
        await flushPromises();
        expect(api.updateLexeme).toHaveBeenCalledWith(3, 4, expect.objectContaining({ translation: 'присматривать' }));

        await button(wrapper, 'Remove').trigger('click');
        await flushPromises();
        expect(api.deleteLexeme).toHaveBeenCalledWith(3, 4);
        expect(wrapper.text()).not.toContain('look after');
        await button(wrapper, 'Undo').trigger('click');
        await flushPromises();
        expect(api.restoreLexeme).toHaveBeenCalledWith(3, 4);
        expect(wrapper.text()).toContain('look after');
    });

    it('adds lesson grammar and corrections without AI', async () => {
        api.get.mockResolvedValue({ id: 3, title: 'Lesson', status: 'active', language: 'en', tags: [], notes: '', homework: '', grammar: [], lexemes: [], corrections: [] });
        api.listMessages.mockResolvedValue({ messages: [], is_waiting: false });
        api.createGrammar.mockResolvedValue({ id: 10, title: 'Past habits', summary: 'Use used to.', example: 'I used to swim.', example_translation: null, status: 'new', matched_grammar_rule_id: null, source: 'manual' });
        api.createCorrection.mockResolvedValue({ id: 20, original_text: 'She go yesterday.', corrected_text: 'She went yesterday.', explanation: 'Past simple.', source: 'manual' });
        api.updateGrammar.mockResolvedValue({ id: 10, title: 'Past states', summary: 'Use used to.', example: 'I used to swim.', example_translation: null, status: 'new', matched_grammar_rule_id: null, source: 'manual' });
        api.deleteGrammar.mockResolvedValue(undefined);
        api.restoreGrammar.mockResolvedValue({ id: 10, title: 'Past states', summary: 'Use used to.', example: 'I used to swim.', example_translation: null, status: 'new', matched_grammar_rule_id: null, source: 'manual' });
        api.updateCorrection.mockResolvedValue({ id: 20, original_text: 'She goes yesterday.', corrected_text: 'She went yesterday.', explanation: 'Past simple.', source: 'manual' });
        api.deleteCorrection.mockResolvedValue(undefined);
        api.restoreCorrection.mockResolvedValue({ id: 20, original_text: 'She goes yesterday.', corrected_text: 'She went yesterday.', explanation: 'Past simple.', source: 'manual' });
        const wrapper = await renderPage(LessonDetailPage, '/lessons/3');
        await flushPromises();

        await wrapper.findAll('button[role="tab"]').find((node) => node.text() === 'Grammar')!.trigger('click');
        await button(wrapper, 'Add grammar').trigger('click');
        await wrapper.get('input[placeholder="e.g. Past habits with used to"]').setValue('Past habits');
        await wrapper.get('textarea[placeholder="When and how to use this pattern"]').setValue('Use used to.');
        await wrapper.find('form').trigger('submit');
        await flushPromises();
        expect(api.createGrammar).toHaveBeenCalledWith(3, expect.objectContaining({ title: 'Past habits', summary: 'Use used to.' }));
        expect(wrapper.text()).toContain('Past habits');
        await button(wrapper, 'Edit').trigger('click');
        await wrapper.get('input[placeholder="e.g. Past habits with used to"]').setValue('Past states');
        await wrapper.find('form').trigger('submit');
        await flushPromises();
        expect(api.updateGrammar).toHaveBeenCalledWith(3, 10, expect.objectContaining({ title: 'Past states' }));
        await button(wrapper, 'Remove').trigger('click');
        await flushPromises();
        expect(api.deleteGrammar).toHaveBeenCalledWith(3, 10);
        await button(wrapper, 'Undo').trigger('click');
        await flushPromises();
        expect(api.restoreGrammar).toHaveBeenCalledWith(3, 10);

        const correctionsTab = wrapper.findAll('button[role="tab"]').find((node) => node.text() === 'Corrections');
        await correctionsTab!.trigger('click');
        await button(wrapper, 'Add correction').trigger('click');
        await wrapper.get('textarea[placeholder="Original wording"]').setValue('She go yesterday.');
        await wrapper.get('textarea[placeholder="Corrected wording"]').setValue('She went yesterday.');
        await wrapper.get('textarea[placeholder="Optional explanation"]').setValue('Past simple.');
        await wrapper.find('form').trigger('submit');
        await flushPromises();
        expect(api.createCorrection).toHaveBeenCalledWith(3, expect.objectContaining({ original_text: 'She go yesterday.', corrected_text: 'She went yesterday.' }));
        expect(wrapper.text()).toContain('She went yesterday.');
        await button(wrapper, 'Edit').trigger('click');
        await wrapper.get('textarea[placeholder="Original wording"]').setValue('She goes yesterday.');
        await wrapper.find('form').trigger('submit');
        await flushPromises();
        expect(api.updateCorrection).toHaveBeenCalledWith(3, 20, expect.objectContaining({ original_text: 'She goes yesterday.' }));
        await button(wrapper, 'Remove').trigger('click');
        await flushPromises();
        expect(api.deleteCorrection).toHaveBeenCalledWith(3, 20);
        await button(wrapper, 'Undo').trigger('click');
        await flushPromises();
        expect(api.restoreCorrection).toHaveBeenCalledWith(3, 20);
    });

    it('adds a lesson grammar point to My grammar and removes the New dead end', async () => {
        api.get.mockResolvedValue({
            id: 3, title: 'Lesson', status: 'active', language: 'en', tags: [], notes: '', homework: '', lexemes: [], corrections: [],
            grammar: [{ id: 10, title: 'Past habits', summary: 'Use used to.', body: 'Use the infinitive.', example: 'I used to swim.', example_translation: null, status: 'new', matched_grammar_rule_id: null, personal_grammar_rule_id: null, source: 'manual' }],
        });
        api.listMessages.mockResolvedValue({ messages: [], is_waiting: false });
        api.addGrammarToMyGrammar.mockResolvedValue({
            grammar_rule_id: 42, matched_grammar_rule_id: null, personal_grammar_rule_id: 42,
            is_personal: true, in_my_grammar: true, status: 'linked',
        });
        const wrapper = await renderPage(LessonDetailPage, '/lessons/3');
        await flushPromises();
        await wrapper.findAll('button[role="tab"]').find((node) => node.text() === 'Grammar')!.trigger('click');
        expect(wrapper.text()).toContain('Not added');
        expect(wrapper.text()).not.toContain('New');
        await button(wrapper, 'Add to My grammar').trigger('click');
        await flushPromises();
        expect(api.addGrammarToMyGrammar).toHaveBeenCalledWith(3, 10);
        expect(wrapper.text()).toContain('In My grammar');
        expect(wrapper.text()).not.toContain('Add to My grammar');
    });

    it('shows dictionary status and a compact add action on lesson word rows', async () => {
        api.get.mockResolvedValue({ id: 3, title: 'Lesson', status: 'active', language: 'en', tags: [], notes: '', homework: '', grammar: [], corrections: [], lexemes: [{ id: 4, text: 'look after', type: 'phrasal_verb', language: 'en', translation: 'заботиться', status: 'matched', matched_lexeme_id: 42, in_my_words: false, in_review: false }] });
        api.listMessages.mockResolvedValue({ messages: [], is_waiting: false });
        api.addLexemeToMyWords.mockResolvedValue({ lexeme_id: 42, matched_lexeme_id: 42, lemma: 'look after', language: 'en', is_personal: false, in_my_words: true, in_review: true, status: 'matched' });
        const wrapper = await renderPage(LessonDetailPage, '/lessons/3');
        await flushPromises();
        expect(wrapper.findAll('button[role="tab"]').find((node) => node.text() === 'Words')!.attributes('aria-selected')).toBe('true');
        expect(wrapper.get('button[aria-label="look after — заботиться. Show details"]').classes()).toContain('min-h-11');
        expect(wrapper.find('section.-mx-4').classes()).toContain('sm:rounded-xl');
        expect(wrapper.text()).toContain('Match');
        await wrapper.get('button[aria-label="Add look after to My words and practice"]').trigger('click');
        await flushPromises();
        expect(api.addLexemeToMyWords).toHaveBeenCalledWith(3, 4);
        expect(wrapper.text()).toContain('In practice');
        expect(wrapper.get('a[aria-label="Practice look after"]').attributes('href')).toContain('lexeme_ids=42');
        expect(wrapper.get('a[href*="lexeme_ids=42"]').text()).toContain('Practice 1');
        await wrapper.get('[role="group"][aria-label="Lesson word status"]').findAll('button')[2].trigger('click');
        await flushPromises();
        expect(wrapper.text()).toContain('look after');
    });

});
