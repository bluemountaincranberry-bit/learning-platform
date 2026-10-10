import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { createMemoryHistory, createRouter, type Router } from 'vue-router';
import { defineComponent } from 'vue';
import GrammarRuleAiEditorPage from '../GrammarRuleAiEditorPage.vue';
import type { GrammarRuleEditorDraft, GrammarRuleEditorRule } from '../../types';

const api = vi.hoisted(() => ({
    getEditorRule: vi.fn(),
    proposeGrammarEdit: vi.fn(),
    applyGrammarEdit: vi.fn(),
    getGrammarEditRevisions: vi.fn(),
    restoreGrammarEditRevision: vi.fn(),
}));
vi.mock('../../domains/content', () => ({ grammarApi: api }));
vi.mock('../../shared/ui/MarkdownContent.vue', () => ({ default: defineComponent({ template: '<div />' }) }));
vi.mock('../../shared/ui/ChatMessage.vue', () => ({
    default: defineComponent({
        props: ['content', 'error', 'retryable'],
        emits: ['retry'],
        template: '<div><span>{{ content || error }}</span><button v-if="retryable" @click="$emit(\'retry\')">Retry</button></div>',
    }),
}));

const initialDraft: GrammarRuleEditorDraft = {
    title: 'Present simple',
    summary: 'Habits and facts.',
    body: '## Form\nSubject + verb.',
    examples: [{ id: 8, language: 'en', example: 'She walks to work.', translation: 'Она ходит на работу.', is_primary: true, sort_order: 10 }],
};

function editorRule(overrides: Partial<GrammarRuleEditorRule> = {}): GrammarRuleEditorRule {
    return {
        ...initialDraft,
        id: 3,
        slug: 'present-simple',
        language: 'en',
        level: 'A1',
        editor_version: 4,
        updated_at: '2026-10-09T10:00:00Z',
        ...overrides,
    };
}

const Stub = defineComponent({ template: '<div />' });
let wrapper: VueWrapper | null = null;

async function mountEditor(): Promise<{ page: VueWrapper; router: Router }> {
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [
            { path: '/grammar/:id/editor', name: 'grammar.editor', component: GrammarRuleAiEditorPage },
            { path: '/grammar/:id', name: 'grammar.details', component: Stub },
        ],
    });
    await router.push('/grammar/3/editor');
    await router.isReady();
    wrapper = mount(defineComponent({ template: '<router-view />' }), { global: { plugins: [router] }, attachTo: document.body });
    await flushPromises();
    return { page: wrapper, router };
}

describe('GrammarRuleAiEditorPage', () => {
    beforeEach(() => {
        Object.values(api).forEach((method) => method.mockReset());
        api.getEditorRule.mockResolvedValue({ rule: editorRule() });
        api.proposeGrammarEdit.mockResolvedValue({ proposal: {
            ...initialDraft,
            title: 'Present simple for routines',
            examples: [{ language: 'en', example: 'She reads every day.', translation: 'Она читает каждый день.', is_primary: true, sort_order: 10 }],
        } });
        api.applyGrammarEdit.mockImplementation(async (_id: string, draft: GrammarRuleEditorDraft) => ({
            rule: editorRule({ ...draft, editor_version: 5 }),
        }));
        api.getGrammarEditRevisions.mockResolvedValue({ revisions: [] });
        api.restoreGrammarEditRevision.mockResolvedValue({ rule: editorRule() });
    });

    afterEach(() => {
        wrapper?.unmount();
        wrapper = null;
    });

    it('opens with the current rule and does not save an AI proposal automatically', async () => {
        const { page } = await mountEditor();

        expect(page.text()).toContain('Present simple');
        page.findAll('button').find((button) => button.text().includes('Make the explanation easier'))?.element.click();
        await flushPromises();

        expect(api.proposeGrammarEdit).toHaveBeenCalledWith('3', expect.objectContaining({ instruction: 'Make the explanation easier to read' }));
        expect(api.applyGrammarEdit).not.toHaveBeenCalled();
        expect(page.find('input').element).toHaveProperty('value', 'Present simple for routines');
        expect(page.text()).toContain('Review draft');
    });

    it('applies the reviewed draft using the version loaded for this rule', async () => {
        const { page } = await mountEditor();
        page.findAll('button').find((button) => button.text().includes('Make the explanation easier'))?.element.click();
        await flushPromises();

        page.findAll('button').find((button) => button.text().includes('Apply changes'))?.element.click();
        await flushPromises();

        expect(api.applyGrammarEdit).toHaveBeenCalledWith('3', expect.objectContaining({ title: 'Present simple for routines' }), 4);
        expect(page.text()).toContain('No changes to apply');
    });
});
