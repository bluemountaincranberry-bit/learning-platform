import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { createMemoryHistory, createRouter, type Router } from 'vue-router';
import { defineComponent } from 'vue';
import GrammarRuleDetailPage from '../GrammarRuleDetailPage.vue';
import type { GrammarRule } from '../../types';

const api = vi.hoisted(() => ({
    getOne: vi.fn(),
    getExercises: vi.fn(),
    getExamples: vi.fn(),
    generateExamples: vi.fn(),
    hideExample: vi.fn(),
    startLearning: vi.fn(),
    markLearned: vi.fn(),
    unmarkLearned: vi.fn(),
}));
const auth = vi.hoisted(() => ({ isAuthenticated: true }));
vi.mock('../../domains/content', () => ({ grammarApi: api }));
vi.mock('../../domains/user', () => ({ useAuthStore: () => auth }));

function rule(overrides: Partial<GrammarRule> = {}): GrammarRule {
    return {
        id: 3,
        slug: 'present-simple',
        title: 'Present Simple',
        language: 'en',
        level: 'A1',
        summary: 'Habits and facts.',
        body: 'Use it for **habits**.',
        topic: { id: 1, name: 'Tenses' },
        examples: [],
        in_my_list: false,
        learned: false,
        ...overrides,
    };
}

const Stub = defineComponent({ template: '<div />' });
let wrapper: VueWrapper | null = null;

async function mountPage(from?: string): Promise<{ page: VueWrapper; router: Router }> {
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [
            { path: '/grammar', name: 'grammar', component: Stub },
            { path: '/my-grammar', name: 'my-grammar', component: Stub },
            { path: '/chat', name: 'chat', component: Stub },
            { path: '/lessons/:id', name: 'lesson.details', component: Stub },
            { path: '/grammar/:id', name: 'grammar.details', component: GrammarRuleDetailPage },
        ],
    });
    if (from) await router.push(from);
    // Memory history does not add the browser adapter's `back` state.
    await router.push({ path: '/grammar/3', state: { back: from ?? null } });
    await router.isReady();
    wrapper = mount(GrammarRuleDetailPage, { global: { plugins: [router] }, attachTo: document.body });
    await flushPromises();
    return { page: wrapper, router };
}

describe('GrammarRuleDetailPage', () => {
    beforeEach(() => {
        auth.isAuthenticated = true;
        for (const fn of Object.values(api)) fn.mockReset();
        api.getOne.mockResolvedValue({ rule: rule() });
        api.getExercises.mockResolvedValue({ exercises: [] });
        api.getExamples.mockResolvedValue({ examples: [], generation: { status: 'idle' } });
        api.startLearning.mockResolvedValue(undefined);
    });

    afterEach(() => {
        wrapper?.unmount();
        wrapper = null;
    });

    it('puts the explanation right after the compact header', async () => {
        const { page } = await mountPage();

        const sections = page.findAll('section');
        expect(sections[0].attributes('data-test')).toBe('rule-header');
        expect(sections[1].text()).toContain('Explanation');
        expect(sections[1].text()).toContain('Habits and facts.');
        expect(sections[1].text()).toContain('Tenses');
        expect(page.text()).not.toContain('Back to grammar');
    });

    it('shows a personal rule source lesson and the practice entry point', async () => {
        api.getOne.mockResolvedValue({ rule: rule({
            topic: undefined,
            is_personal: true,
            source_lesson: { id: 99, title: 'Tuesday class' },
            in_my_list: true,
        }) });
        const { page } = await mountPage();

        expect(page.get('[data-test="personal-rule-source"]').text()).toContain('Tuesday class');
        expect(page.find('[data-test="personal-rule-source"] a').attributes('href')).toContain('/lessons/99');
        expect(page.find('#rule-exercises').exists()).toBe(true);
    });

    it('adds the rule to my grammar, then offers Practice', async () => {
        const { page } = await mountPage();

        await page.get('[data-test="rule-primary"]').trigger('click');
        await flushPromises();

        expect(api.startLearning).toHaveBeenCalledWith(3);
        expect(page.get('[data-test="rule-primary"]').text()).toContain('Practice');
    });

    it('Practice scrolls to the exercises', async () => {
        api.getOne.mockResolvedValue({ rule: rule({ in_my_list: true }) });
        const { page } = await mountPage();
        const scroll = vi.fn();
        page.get('#rule-exercises').element.scrollIntoView = scroll;

        await page.get('[data-test="rule-primary"]').trigger('click');

        expect(scroll).toHaveBeenCalled();
    });

    it('back returns to the previous page', async () => {
        const { page, router } = await mountPage('/my-grammar');

        await page.get('[data-test="rule-back"]').trigger('click');
        await flushPromises();
        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(router.currentRoute.value.name).toBe('my-grammar');
    });

    it('back opens the grammar list on a direct visit', async () => {
        const { page, router } = await mountPage();

        await page.get('[data-test="rule-back"]').trigger('click');
        await flushPromises();

        expect(router.currentRoute.value.name).toBe('grammar');
    });

    it('Discuss with AI opens the chat with the rule as context', async () => {
        const { page, router } = await mountPage();

        await page.get('[data-test="rule-menu"]').trigger('keydown', { key: 'Enter' });
        await flushPromises();
        Array.from(document.body.querySelectorAll<HTMLElement>('[data-test="rule-menu-item"]'))
            .find((node) => node.textContent?.includes('Discuss with AI'))!
            .click();
        await flushPromises();

        expect(router.currentRoute.value.name).toBe('chat');
        expect(router.currentRoute.value.query).toEqual({ context_type: 'grammar', context_id: '3', context_title: 'Present Simple' });
    });
});
