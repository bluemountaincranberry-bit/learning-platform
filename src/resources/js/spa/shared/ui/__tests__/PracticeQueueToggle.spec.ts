import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import PracticeQueueToggle from '../PracticeQueueToggle.vue';

describe('PracticeQueueToggle', () => {
    it('offers one stateful queue action with an accessible touch target', async () => {
        const wrapper = mount(PracticeQueueToggle, { props: { queued: false, word: 'run' } });
        const add = wrapper.get('button');
        expect(add.attributes('aria-label')).toBe('Add run to practice');
        expect(add.attributes('aria-pressed')).toBe('false');
        expect(add.classes()).toContain('h-11');
        expect(add.classes()).toContain('w-11');
        await add.trigger('click');
        expect(wrapper.emitted('toggle')).toHaveLength(1);

        await wrapper.setProps({ queued: true });
        expect(wrapper.get('button').attributes('aria-label')).toBe('Remove run from practice');
        expect(wrapper.get('button').attributes('aria-pressed')).toBe('true');
    });
});
