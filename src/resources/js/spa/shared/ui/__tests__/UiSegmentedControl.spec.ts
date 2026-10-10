import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import UiSegmentedControl from '../UiSegmentedControl.vue';

describe('UiSegmentedControl', () => {
    it('exposes the current segment and emits a selected value', async () => {
        const wrapper = mount(UiSegmentedControl, {
            props: {
                segments: [{ value: 'all', label: 'All', count: 4 }, { value: 'learning', label: 'In practice', count: 2 }],
                modelValue: 'all',
                ariaLabel: 'Word status',
            },
        });

        expect(wrapper.get('[role="group"]').attributes('aria-label')).toBe('Word status');
        expect(wrapper.findAll('button')[0].attributes('aria-pressed')).toBe('true');
        await wrapper.findAll('button')[1].trigger('click');
        expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['learning']);
    });
});
