import { describe, expect, it } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import WordListToolbar from '../WordListToolbar.vue';

describe('WordListToolbar', () => {
    it('uses the shared status control to filter content words', async () => {
        const newWord = { id: 1, type: 'word', text: 'speak', sort_order: 1, learned: false, in_review: false };
        const queuedWord = { id: 2, type: 'word', text: 'listen', sort_order: 2, learned: false, in_review: true };
        const wrapper = mount(WordListToolbar, {
            props: {
                lexemes: [newWord, queuedWord],
                selectedIds: new Set<number>(),
                bulkMarkLearned: async () => null,
                bulkStartLearning: async () => null,
            },
        });
        await flushPromises();

        const statuses = wrapper.get('[role="group"][aria-label="Word status"]');
        await statuses.findAll('button').find((button) => button.text().includes('To learn'))!.trigger('click');
        await flushPromises();

        expect(wrapper.emitted('update:filtered')?.at(-1)?.[0]).toEqual([queuedWord]);
    });
});
