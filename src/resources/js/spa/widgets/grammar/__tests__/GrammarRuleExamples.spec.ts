import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import GrammarRuleExamples from '../GrammarRuleExamples.vue';
import type { GrammarRuleExample } from '../../../types';

const api = vi.hoisted(() => ({
    getExamples: vi.fn(),
    generateExamples: vi.fn(),
    hideExample: vi.fn(),
}));
vi.mock('../../../domains/content', () => ({ grammarApi: api }));

function example(id: number, overrides: Partial<GrammarRuleExample> = {}): GrammarRuleExample {
    return {
        id,
        example: `She works ${id}.`,
        translation: `Она работает ${id}.`,
        kind: 'affirmative',
        mistake: null,
        target_spans: [[4, 9]],
        origin: 'ai',
        from_content: false,
        ...overrides,
    };
}

function mountExamples(props: Record<string, unknown> = {}) {
    return mount(GrammarRuleExamples, { props: { ruleId: 7, authenticated: true, pollMs: 10, ...props } });
}

describe('GrammarRuleExamples', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        api.getExamples.mockReset();
        api.generateExamples.mockReset();
        api.hideExample.mockReset().mockResolvedValue(undefined);
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('highlights the grammar form and shows translation, kind and mistake', async () => {
        api.getExamples.mockResolvedValue({
            examples: [
                example(1),
                example(2, { kind: 'mistake', mistake: "She don't like it.", example: "She doesn't like it.", target_spans: [[4, 11]] }),
            ],
            generation: { status: 'idle' },
        });

        const wrapper = mountExamples();
        await flushPromises();

        const targets = wrapper.findAll('[data-test="example-target"]').map((node) => node.text());
        expect(targets).toEqual(['works', "doesn't"]);
        expect(wrapper.text()).toContain('Она работает 1.');
        expect(wrapper.get('[data-test="example-mistake"]').text()).toContain("She don't like it.");
        expect(wrapper.text()).toContain('Common mistake');
        expect(wrapper.text()).toContain('AI');
    });

    it('falls back to plain text when an example has no marked form', async () => {
        api.getExamples.mockResolvedValue({
            examples: [example(1, { target_spans: null }), example(2, { target_spans: [] })],
            generation: { status: 'idle' },
        });

        const wrapper = mountExamples();
        await flushPromises();

        expect(wrapper.find('[data-test="example-target"]').exists()).toBe(false);
        expect(wrapper.findAll('[data-test="example-text"]').map((node) => node.text())).toEqual(['She works 1.', 'She works 2.']);
    });

    it('shows 3 examples first and the rest on Show all', async () => {
        api.getExamples.mockResolvedValue({ examples: [1, 2, 3, 4, 5].map((id) => example(id)), generation: { status: 'idle' } });

        const wrapper = mountExamples();
        await flushPromises();

        expect(wrapper.findAll('[data-test="example"]')).toHaveLength(3);
        const toggle = wrapper.get('[data-test="examples-toggle"]');
        expect(toggle.text()).toContain('Show all 5 examples');
        expect(toggle.attributes('aria-expanded')).toBe('false');

        await toggle.trigger('click');
        expect(wrapper.findAll('[data-test="example"]')).toHaveLength(5);
        expect(wrapper.get('[data-test="examples-toggle"]').text()).toContain('Show fewer');
        expect(wrapper.get('[data-test="examples-toggle"]').attributes('aria-expanded')).toBe('true');

        await wrapper.get('[data-test="examples-toggle"]').trigger('click');
        expect(wrapper.findAll('[data-test="example"]')).toHaveLength(3);
    });

    it('has no Show all toggle for 3 examples or fewer', async () => {
        api.getExamples.mockResolvedValue({ examples: [1, 2, 3].map((id) => example(id)), generation: { status: 'idle' } });

        const wrapper = mountExamples();
        await flushPromises();

        expect(wrapper.findAll('[data-test="example"]')).toHaveLength(3);
        expect(wrapper.find('[data-test="examples-toggle"]').exists()).toBe(false);
    });

    it('opens the full list after More examples so new ones are visible', async () => {
        const firstThree = [1, 2, 3].map((id) => example(id));
        api.getExamples
            .mockResolvedValueOnce({ examples: firstThree, generation: { status: 'idle' } })
            .mockResolvedValueOnce({ examples: [...firstThree, example(4), example(5)], generation: { status: 'done' } });
        api.generateExamples.mockResolvedValue({ status: 'queued' });

        const wrapper = mountExamples();
        await flushPromises();
        await wrapper.get('[data-test="examples-more"]').trigger('click');
        await vi.advanceTimersByTimeAsync(10);
        await flushPromises();

        expect(wrapper.findAll('[data-test="example"]')).toHaveLength(5);
    });

    it('asks for more examples and polls until the batch is done', async () => {
        api.getExamples
            .mockResolvedValueOnce({ examples: [example(1)], generation: { status: 'idle' } })
            .mockResolvedValueOnce({ examples: [example(1)], generation: { status: 'running' } })
            .mockResolvedValueOnce({ examples: [example(1), example(2), example(3)], generation: { status: 'done' } });
        api.generateExamples.mockResolvedValue({ status: 'queued' });

        const wrapper = mountExamples();
        await flushPromises();
        await wrapper.get('[data-test="examples-more"]').trigger('click');
        await flushPromises();

        expect(wrapper.get('[data-test="examples-more"]').text()).toContain('Writing examples');
        expect(wrapper.get('[data-test="examples-more"]').attributes('disabled')).toBeDefined();

        await vi.advanceTimersByTimeAsync(10);
        await vi.advanceTimersByTimeAsync(10);
        await flushPromises();

        expect(wrapper.findAll('[data-test="example"]')).toHaveLength(3);
        expect(wrapper.get('[data-test="examples-more"]').text()).toContain('More examples');
        expect(wrapper.find('[data-test="examples-notice"]').exists()).toBe(false);
    });

    it('explains the daily limit instead of spinning', async () => {
        api.getExamples.mockResolvedValue({ examples: [example(1)], generation: { status: 'idle' } });
        api.generateExamples.mockResolvedValue({ status: 'limited' });

        const wrapper = mountExamples();
        await flushPromises();
        await wrapper.get('[data-test="examples-more"]').trigger('click');
        await flushPromises();

        expect(wrapper.get('[data-test="examples-notice"]').text()).toContain('Come back tomorrow');
        expect(wrapper.get('[data-test="examples-more"]').attributes('disabled')).toBeUndefined();
    });

    it('says so when the AI batch failed', async () => {
        api.getExamples
            .mockResolvedValueOnce({ examples: [example(1)], generation: { status: 'idle' } })
            .mockResolvedValueOnce({ examples: [example(1)], generation: { status: 'failed' } });
        api.generateExamples.mockResolvedValue({ status: 'queued' });

        const wrapper = mountExamples();
        await flushPromises();
        await wrapper.get('[data-test="examples-more"]').trigger('click');
        await vi.advanceTimersByTimeAsync(10);
        await flushPromises();

        expect(wrapper.get('[data-test="examples-notice"]').text()).toContain('Could not write new examples');
    });

    it('hides an example for the learner', async () => {
        api.getExamples.mockResolvedValue({ examples: [example(1), example(2)], generation: { status: 'idle' } });

        const wrapper = mountExamples();
        await flushPromises();
        await wrapper.findAll('[data-test="example-hide"]')[0].trigger('click');
        await flushPromises();

        expect(api.hideExample).toHaveBeenCalledWith(7, 1);
        expect(wrapper.findAll('[data-test="example"]')).toHaveLength(1);
    });

    it('guests see examples but no AI or hide actions', async () => {
        api.getExamples.mockResolvedValue({ examples: [example(1)], generation: { status: 'idle' } });

        const wrapper = mountExamples({ authenticated: false });
        await flushPromises();

        expect(wrapper.findAll('[data-test="example"]')).toHaveLength(1);
        expect(wrapper.find('[data-test="examples-more"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="example-hide"]').exists()).toBe(false);
    });

    it('resumes progress when a batch is already running on load', async () => {
        api.getExamples
            .mockResolvedValueOnce({ examples: [], generation: { status: 'queued' } })
            .mockResolvedValueOnce({ examples: [example(1)], generation: { status: 'done' } });

        const wrapper = mountExamples();
        await flushPromises();
        expect(wrapper.get('[data-test="examples-more"]').text()).toContain('Writing examples');

        await vi.advanceTimersByTimeAsync(10);
        await flushPromises();
        expect(wrapper.findAll('[data-test="example"]')).toHaveLength(1);
    });
});
