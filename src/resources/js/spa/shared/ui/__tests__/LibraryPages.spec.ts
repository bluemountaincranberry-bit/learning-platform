import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createRouter, createMemoryHistory } from 'vue-router';
import MyWordsPage from '../../../pages/MyWordsPage.vue';
import MyGrammarPage from '../../../pages/MyGrammarPage.vue';

const api = vi.hoisted(() => ({ words: vi.fn(), grammar: vi.fn(), addWord: vi.fn(), startLearning: vi.fn(), stopLearning: vi.fn(), startLexemeLearning: vi.fn(), markLearned: vi.fn() }));
vi.mock('../../../domains/user', () => ({ useAuthStore: () => ({ isAuthenticated: true }) }));
vi.mock('../../../domains/learning', () => ({ myWordsApi: { getList: api.words, addWord: api.addWord, startLearning: api.startLearning, stopLearning: api.stopLearning }, learnedGrammarRulesApi: { getList: api.grammar }, useGrammarPracticeSetting: () => ({ setting: { value: { level: 'medium', count: 10 } } }) }));
vi.mock('../../../domains/content', () => ({ contentApi: { startLexemeLearning: api.startLexemeLearning }, grammarApi: { markLearned: api.markLearned } }));

async function renderPage(component: typeof MyWordsPage | typeof MyGrammarPage, path: string) {
    const router = createRouter({ history: createMemoryHistory(), routes: [
        { path: '/my-words', component: MyWordsPage }, { path: '/my-grammar', component: MyGrammarPage },
        { path: '/word/:id', name: 'word.details', component: { template: '<div />' } },
        { path: '/lessons/:id', name: 'lesson.details', component: { template: '<div />' } },
        { path: '/catalog/:id', name: 'catalog.details', component: { template: '<div />' } },
        { path: '/grammar/:id', name: 'grammar.details', component: { template: '<div />' } },
    ] });
    await router.push(path);
    const wrapper = mount(component, { global: { plugins: [router] } });
    await flushPromises();
    return wrapper;
}

describe('learning library page adoption', () => {
    beforeEach(() => vi.resetAllMocks());
    it('selects and expands a word while learning uses its canonical ID', async () => {
        api.words.mockResolvedValue({ data: [{ id: 42, lexeme_id: 42, content_lexeme_id: 7, lexeme: 'run', translation: 'бежать', language: 'en', status: 'new', in_review: false, learned_at: null, contexts: [], examples: [{ example: 'I run.', translation: 'Я бегаю.', is_primary: true }] }], meta: { current_page: 1, per_page: 15, total: 1 } });
        api.startLearning.mockResolvedValue(undefined);
        const wrapper = await renderPage(MyWordsPage, '/my-words');
        await wrapper.get('input[aria-label="Select run"]').setValue(true);
        expect(wrapper.text()).toContain('1 selected');
        await wrapper.get('button[aria-label="run — бежать. Show details"]').trigger('click');
        await flushPromises();
        expect(wrapper.get('a[href="/word/42"]').text()).toBe('Word page');
        expect(wrapper.text()).toContain('I run.');
        await wrapper.findAll('button').find((button) => button.text() === 'Add')!.trigger('click');
        await flushPromises();
        expect(api.startLearning).toHaveBeenCalledWith(42);
    });
    it('adds a contentless word through the personal-word form', async () => {
        api.words.mockResolvedValue({ data: [], meta: { current_page: 1, per_page: 15, total: 0 } });
        api.addWord.mockResolvedValue({ lexeme: { id: 42, lemma: 'retain', language: 'en', is_personal: true } });
        const wrapper = await renderPage(MyWordsPage, '/my-words');
        await wrapper.get('input[aria-label="Word"]').setValue('retain');
        await wrapper.get('input[aria-label="Language"]').setValue('en');
        await wrapper.find('form').trigger('submit');
        await flushPromises();
        expect(api.addWord).toHaveBeenCalledWith({ lemma: 'retain', language: 'en' });
        expect(wrapper.text()).toContain('Added “retain” to learning.');
    });
    it('shows both video and lesson sources on one word row', async () => {
        api.words.mockResolvedValue({ data: [{
            id: 42, lexeme_id: 42, content_lexeme_id: 7, lexeme: 'run', translation: 'бежать', language: 'en',
            status: 'new', in_review: false, learned_at: null,
            contexts: [{ content_lexeme_id: 7, content_id: 8, content_title: 'Video lesson', language: 'en', level: 'A2' }],
            lessonSources: [{ lessonId: 9, lessonTitle: 'Tuesday class', candidateId: 10 }],
        }], meta: { current_page: 1, per_page: 15, total: 1 } });

        const wrapper = await renderPage(MyWordsPage, '/my-words');
        await wrapper.get('button[aria-label="run — бежать. Show details"]').trigger('click');
        await flushPromises();

        expect(wrapper.findAll('a').map((link) => link.text())).toContain('Video lesson');
        expect(wrapper.get('a[href="/lessons/9"]').text()).toBe('Tuesday class');
        expect(wrapper.text()).not.toContain('No content context');
        expect(wrapper.findAll('input[aria-label="Select run"]')).toHaveLength(1);
    });
    it('starts and stops contentless rows by canonical ID', async () => {
        const stopped = { data: [{ id: 42, lexeme_id: 42, content_lexeme_id: null, lexeme: 'retain', language: 'en', status: 'new', in_review: false, learned_at: null, contexts: [] }], meta: { current_page: 1, per_page: 15, total: 1 } };
        const learning = { data: [{ id: 42, lexeme_id: 42, content_lexeme_id: null, lexeme: 'retain', language: 'en', status: 'in_learning', in_review: true, learned_at: null, contexts: [] }], meta: { current_page: 1, per_page: 15, total: 1 } };
        api.words.mockResolvedValueOnce(stopped).mockResolvedValueOnce(learning).mockResolvedValue(stopped);
        api.startLearning.mockResolvedValue(undefined);
        api.stopLearning.mockResolvedValue(undefined);
        const wrapper = await renderPage(MyWordsPage, '/my-words');
        await wrapper.get('button[aria-label="retain. Show details"]').trigger('click');
        await flushPromises();
        await wrapper.get('button[aria-label="Add retain to practice"]').trigger('click');
        await flushPromises();
        expect(api.startLearning).toHaveBeenCalledWith(42);
        await wrapper.get('button[aria-label="retain. Show details"]').trigger('click');
        await flushPromises();
        await wrapper.get('button[aria-label="Remove retain from practice"]').trigger('click');
        await flushPromises();
        expect(api.stopLearning).toHaveBeenCalledWith(42);
    });
    it('shows grammar status and keeps learned mutation separate from the canonical rule link', async () => {
        api.grammar.mockResolvedValue({ data: [{ id: 91, grammar_rule_id: 7, title: 'Present simple', summary: 'Daily routines', status: 'learning', level: 'A1', started_at: '2026-10-04', confidence_calculated: null, topic: null }], meta: { current_page: 1, per_page: 15, total: 1 } });
        api.markLearned.mockResolvedValue(undefined);
        const wrapper = await renderPage(MyGrammarPage, '/my-grammar');
        expect(wrapper.get('[role="link"][aria-label="Open Present simple"]').text()).toContain('Present simple');
        expect(wrapper.text()).toContain('Started');
        await wrapper.findAll('button').find((button) => button.text() === 'Mark as learned')!.trigger('click');
        await flushPromises();
        expect(api.markLearned).toHaveBeenCalledWith(7);
        expect(wrapper.text()).toContain('Learned');
    });
});
