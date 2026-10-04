import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createRouter, createMemoryHistory } from 'vue-router';
import MyWordsPage from '../../../pages/MyWordsPage.vue';
import MyGrammarPage from '../../../pages/MyGrammarPage.vue';

const api = vi.hoisted(() => ({ words: vi.fn(), grammar: vi.fn(), startLexemeLearning: vi.fn(), markLearned: vi.fn() }));
vi.mock('../../../domains/user', () => ({ useAuthStore: () => ({ isAuthenticated: true }) }));
vi.mock('../../../domains/learning', () => ({ myWordsApi: { getList: api.words }, learnedGrammarRulesApi: { getList: api.grammar } }));
vi.mock('../../../domains/content', () => ({ contentApi: { startLexemeLearning: api.startLexemeLearning }, grammarApi: { markLearned: api.markLearned } }));

async function renderPage(component: typeof MyWordsPage | typeof MyGrammarPage, path: string) {
    const router = createRouter({ history: createMemoryHistory(), routes: [
        { path: '/my-words', component: MyWordsPage }, { path: '/my-grammar', component: MyGrammarPage },
        { path: '/word/:id', name: 'word.details', component: { template: '<div />' } },
        { path: '/grammar/:id', name: 'grammar.details', component: { template: '<div />' } },
    ] });
    await router.push(path);
    const wrapper = mount(component, { global: { plugins: [router] } });
    await flushPromises();
    return wrapper;
}

describe('learning library page adoption', () => {
    beforeEach(() => vi.resetAllMocks());
    it('selects and expands a word while learning uses the occurrence ID and navigation uses its canonical ID', async () => {
        api.words.mockResolvedValue({ data: [{ id: 42, lexeme_id: 42, content_lexeme_id: 7, lexeme: 'run', translation: 'бежать', language: 'en', status: 'new', in_review: false, learned_at: null, contexts: [], examples: [{ example: 'I run.', translation: 'Я бегаю.', is_primary: true }] }], meta: { current_page: 1, per_page: 15, total: 1 } });
        api.startLexemeLearning.mockResolvedValue(undefined);
        const wrapper = await renderPage(MyWordsPage, '/my-words');
        await wrapper.get('input[aria-label="Select run"]').setValue(true);
        expect(wrapper.text()).toContain('1 selected');
        await wrapper.get('button[aria-label="run — бежать. Show details"]').trigger('click');
        await flushPromises();
        expect(wrapper.get('a[href="/word/42"]').text()).toBe('Word page');
        expect(wrapper.text()).toContain('I run.');
        await wrapper.findAll('button').find((button) => button.text() === 'Add')!.trigger('click');
        await flushPromises();
        expect(api.startLexemeLearning).toHaveBeenCalledWith(7);
    });
    it('shows grammar status and keeps learned mutation separate from the canonical rule link', async () => {
        api.grammar.mockResolvedValue({ data: [{ id: 91, grammar_rule_id: 7, title: 'Present simple', summary: 'Daily routines', status: 'learning', level: 'A1', started_at: '2026-10-04', topic: null }], meta: { current_page: 1, per_page: 15, total: 1 } });
        api.markLearned.mockResolvedValue(undefined);
        const wrapper = await renderPage(MyGrammarPage, '/my-grammar');
        expect(wrapper.get('a[href="/grammar/7"]').text()).toBe('Present simple');
        expect(wrapper.text()).toContain('Learning');
        await wrapper.findAll('button').find((button) => button.text() === 'Mark as learned')!.trigger('click');
        await flushPromises();
        expect(api.markLearned).toHaveBeenCalledWith(7);
        expect(wrapper.text()).toContain('Learned');
    });
});
