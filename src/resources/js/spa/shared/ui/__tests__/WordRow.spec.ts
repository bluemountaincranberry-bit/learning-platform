import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { createRouter, createMemoryHistory } from 'vue-router';
import WordRow from '../WordRow.vue';

const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/word/:id', name: 'word.details', component: { template: '<div />' } }] });

describe('WordRow', () => {
    it('selects independently, expands examples and links the canonical word', async () => {
        const wrapper = mount(WordRow, { props: { text: 'run', translation: 'бежать', level: 'A1', lexemeId: 42, selectable: true, examples: [{ example: 'I run daily.', translation: 'Я бегаю каждый день.', is_primary: true }] }, global: { plugins: [router] } });
        expect(wrapper.find('a').exists()).toBe(false);
        await wrapper.get('input').setValue(true);
        expect(wrapper.emitted('toggleSelect')).toHaveLength(1);
        expect(wrapper.find('a').exists()).toBe(false);
        await wrapper.get('button[aria-expanded]').trigger('click');
        expect(wrapper.get('a').attributes('href')).toBe('/word/42');
        expect(wrapper.text()).toContain('I run daily.');
        await wrapper.get('button[aria-label="Show example translation"]').trigger('click');
        expect(wrapper.text()).toContain('Я бегаю каждый день.');
    });
    it('shows an unresolved lesson word without inventing a dictionary link', () => {
        const wrapper = mount(WordRow, { props: { text: 'new phrase', defaultExpanded: true }, global: { plugins: [router] } });
        expect(wrapper.find('a').exists()).toBe(false);
    });
});
