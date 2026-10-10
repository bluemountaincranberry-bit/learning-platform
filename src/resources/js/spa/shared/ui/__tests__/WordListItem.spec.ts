import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import WordListItem from '../WordListItem.vue';

describe('WordListItem', () => {
    it('keeps queue membership as its only per-word action and shows learned state separately', () => {
        const wrapper = mount(WordListItem, {
            props: {
                lexeme: { id: 1, lexeme_id: 42, type: 'word', text: 'run', sort_order: 1, learned: true, in_review: false },
                selectable: true,
                selected: false,
            },
        });

        expect(wrapper.get('input[type="checkbox"]').attributes('aria-label')).toBe('Select run');
        expect(wrapper.findAll('button').filter((button) => /(?:Add run to|Remove run from) practice/.test(button.attributes('aria-label') ?? ''))).toHaveLength(1);
        expect(wrapper.text()).toContain('Learned');
        expect(wrapper.findAll('button').some((button) => /known|learned/i.test(button.attributes('aria-label') ?? ''))).toBe(false);
    });
});
