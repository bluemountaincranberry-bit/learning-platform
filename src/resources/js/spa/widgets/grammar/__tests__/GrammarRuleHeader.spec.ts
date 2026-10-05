import { afterEach, describe, expect, it } from 'vitest';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import GrammarRuleHeader from '../GrammarRuleHeader.vue';
import type { GrammarRule } from '../../../types';

function rule(overrides: Partial<GrammarRule> = {}): GrammarRule {
    return {
        id: 3,
        slug: 'present-simple',
        title: 'Present Simple',
        language: 'en',
        level: 'A1',
        summary: 'Habits and facts.',
        body: null,
        in_my_list: false,
        learned: false,
        ...overrides,
    };
}

let wrapper: VueWrapper | null = null;

function mountHeader(props: Record<string, unknown> = {}) {
    wrapper = mount(GrammarRuleHeader, {
        props: { rule: rule(), authenticated: true, busy: false, ...props },
        attachTo: document.body,
    });
    return wrapper;
}

async function openMenu(header: VueWrapper): Promise<string[]> {
    await header.get('[data-test="rule-menu"]').trigger('keydown', { key: 'Enter' });
    await flushPromises();
    return Array.from(document.body.querySelectorAll('[data-test="rule-menu-item"]')).map((node) => node.textContent?.trim() ?? '');
}

function menuItem(label: string): HTMLElement {
    const item = Array.from(document.body.querySelectorAll<HTMLElement>('[data-test="rule-menu-item"]'))
        .find((node) => node.textContent?.includes(label));
    if (!item) throw new Error(`No menu item "${label}"`);
    return item;
}

describe('GrammarRuleHeader', () => {
    afterEach(() => {
        wrapper?.unmount();
        wrapper = null;
    });

    it('shows back, title and level on one line', () => {
        const header = mountHeader();

        expect(header.get('[data-test="rule-back"]').attributes('aria-label')).toBe('Back');
        expect(header.get('h2').text()).toBe('Present Simple');
        expect(header.get('[data-test="rule-title-row"]').text()).toContain('A1');
    });

    it('offers Add to my grammar first, the rest in the menu', async () => {
        const header = mountHeader();

        expect(header.get('[data-test="rule-primary"]').text()).toContain('Add to my grammar');
        expect(await openMenu(header)).toEqual(['Discuss with AI', 'Mark as learned']);
    });

    it('switches the primary action to Practice once the rule is in my grammar', async () => {
        const header = mountHeader({ rule: rule({ in_my_list: true }) });

        expect(header.get('[data-test="rule-primary"]').text()).toContain('Practice');
        expect(await openMenu(header)).toEqual(['Discuss with AI', 'Mark as learned', 'Remove from my grammar']);
    });

    it('shows Learned and keeps Practice and Remove for a learned rule', async () => {
        const header = mountHeader({ rule: rule({ in_my_list: true, learned: true }) });

        expect(header.get('[data-test="rule-title-row"]').text()).toContain('Learned');
        expect(header.get('[data-test="rule-primary"]').text()).toContain('Practice');
        expect(await openMenu(header)).toEqual(['Discuss with AI', 'Remove from my grammar']);
    });

    it('guests get Practice and Discuss with AI only', async () => {
        const header = mountHeader({ authenticated: false, rule: rule({ in_my_list: undefined, learned: undefined }) });

        expect(header.get('[data-test="rule-primary"]').text()).toContain('Practice');
        expect(await openMenu(header)).toEqual(['Discuss with AI']);
    });

    it('emits the chosen action', async () => {
        const header = mountHeader({ rule: rule({ in_my_list: true }) });

        await header.get('[data-test="rule-back"]').trigger('click');
        await header.get('[data-test="rule-primary"]').trigger('click');
        await openMenu(header);
        menuItem('Mark as learned').click();
        await flushPromises();
        await openMenu(header);
        menuItem('Remove from my grammar').click();
        await flushPromises();
        await openMenu(header);
        menuItem('Discuss with AI').click();
        await flushPromises();

        expect(Object.keys(header.emitted())).toEqual(expect.arrayContaining(['back', 'practice', 'learned', 'remove', 'discuss']));
    });

    it('emits add from the primary action and disables it while busy', async () => {
        const header = mountHeader();
        await header.get('[data-test="rule-primary"]').trigger('click');
        expect(header.emitted('add')).toHaveLength(1);

        await header.setProps({ busy: true });
        expect(header.get('[data-test="rule-primary"]').attributes('disabled')).toBeDefined();
    });
});
