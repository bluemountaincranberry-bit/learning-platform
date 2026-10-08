import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { createRouter, createMemoryHistory } from 'vue-router';
import GrammarCard from '../GrammarCard.vue';
const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/grammar/:id', name: 'grammar.details', component: { template: '<div />' } }] });
describe('GrammarCard', () => {
    it('links the canonical rule and keeps the action separate from navigation', async () => {
        const wrapper = mount(GrammarCard, { props: { title: 'Present simple', ruleId: 7, level: 'A1', summary: 'Daily routines', status: 'Learning' }, slots: { actions: '<button>Mark as learned</button>' }, global: { plugins: [router] } });
        expect(wrapper.get('a').attributes('href')).toBe('/grammar/7');
        expect(wrapper.text()).toContain('Learning');
        expect(wrapper.text()).toContain('Daily routines');
        expect(wrapper.get('button').element.closest('a')).toBe(null);
    });
    it('renders a pending candidate without a link', () => {
        const wrapper = mount(GrammarCard, { props: { title: 'Unmatched rule', status: 'New' }, global: { plugins: [router] } });
        expect(wrapper.find('a').exists()).toBe(false);
    });
    it('shows a lesson rule example and keeps longer rule details readable on demand', () => {
        const wrapper = mount(GrammarCard, { props: {
            title: 'Past habits',
            summary: 'Use used to for repeated past actions.',
            body: 'Use the infinitive after used to.',
            example: 'I used to walk to school.',
            exampleTranslation: 'Раньше я ходила в школу пешком.',
        }, global: { plugins: [router] } });
        expect(wrapper.text()).toContain('I used to walk to school.');
        expect(wrapper.text()).toContain('Раньше я ходила в школу пешком.');
        expect(wrapper.get('details').text()).toContain('Use the infinitive after used to.');
    });
});
