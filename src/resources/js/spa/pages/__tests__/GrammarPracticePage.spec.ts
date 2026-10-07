import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createMemoryHistory, createRouter } from 'vue-router';
import GrammarPracticePage from '../GrammarPracticePage.vue';
import type { GrammarPracticeExercise } from '../../types';

const api = vi.hoisted(() => ({
    getOverview: vi.fn(),
    startRound: vi.fn(),
    check: vi.fn(),
    report: vi.fn(),
    complete: vi.fn(),
}));
vi.mock('../../domains/learning/api/grammarPracticeApi', () => ({ grammarPracticeApi: api }));
vi.mock('../../domains/content', () => ({
    grammarApi: {
        getOne: vi.fn().mockResolvedValue({ rule: { id: 7, title: 'Present Perfect', body: 'have + V3', examples: [] } }),
        markLearned: vi.fn().mockResolvedValue({ ok: true }),
    },
}));

const round: GrammarPracticeExercise[] = [
    { id: 1, ruleId: 7, type: 'multiple_choice', level: 'easy', instruction: null, prompt: 'I _____ it twice.', options: ['saw', 'have seen'], tiles: null, origin: 'ai' },
    { id: 2, ruleId: 7, type: 'build', level: 'easy', instruction: 'Make a question', prompt: 'Make a question', options: null, tiles: ['you', 'Have', 'been?'], origin: 'ai' },
    { id: 3, ruleId: 7, type: 'cloze', level: 'hard', instruction: null, prompt: 'She _____ (lose) it.', options: null, tiles: null, origin: 'admin' },
    { id: 4, ruleId: 7, type: 'transform', level: 'hard', instruction: 'Make it a question', prompt: 'They have finished.', options: null, tiles: null, origin: 'ai' },
    { id: 5, ruleId: 7, type: 'fix', level: 'hard', instruction: null, prompt: 'I have seen him yesterday.', options: null, tiles: null, origin: 'ai' },
];

async function mountPage(query = '?level=medium&count=5&from=/grammar/7') {
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [
            { path: '/grammar/:id/practice', name: 'grammar.practice', component: GrammarPracticePage },
            { path: '/grammar/:id', name: 'grammar.details', component: { template: '<div>rule</div>' } },
        ],
    });
    await router.push(`/grammar/7/practice${query}`);
    await router.isReady();
    const wrapper = mount(GrammarPracticePage, { global: { plugins: [router] }, attachTo: document.body });
    await flushPromises();
    return { wrapper, router };
}

beforeEach(() => {
    vi.clearAllMocks();
    document.body.innerHTML = '';
    api.startRound.mockResolvedValue({ status: 'ready', exercises: round, availableCount: 5 });
});

describe('GrammarPracticePage', () => {
    it('renders all five types in order and ends on the result screen', async () => {
        api.check.mockResolvedValue({ correct: true, outcome: 'first_try', answer: 'ok', explanation: 'Because.' });
        api.complete.mockResolvedValue({
            attemptId: 1, level: 'medium', scorePct: 90, firstTry: 4, afterHint: 1, missed: 0, reported: 0,
            confidenceBefore: 72, confidenceAfter: 78, inMyList: true, learned: false, canMarkLearned: true,
            toReview: [{ exerciseId: 3, type: 'cloze', instruction: null, prompt: 'She _____ (lose) it.', given: 'has losed', answer: 'has lost', outcome: 'after_hint' }],
        });
        const { wrapper } = await mountPage();

        expect(api.startRound).toHaveBeenCalledWith(7, { level: 'medium', count: 5 });

        // 1. choose the form
        expect(wrapper.get('[data-test="progress"]').text()).toBe('1 / 5');
        await wrapper.findAll('[data-test="option"]')[1].trigger('click');
        await flushPromises();
        expect(wrapper.find('[data-test="feedback-correct"]').exists()).toBe(true);
        await wrapper.get('[data-test="next"]').trigger('click');

        // 2. build the sentence
        expect(wrapper.get('[data-test="task"]').text()).toBe('Make a question');
        for (const tile of wrapper.findAll('[data-test="tile"]')) await tile.trigger('click');
        await wrapper.get('[data-test="check"]').trigger('click');
        await flushPromises();
        expect(api.check).toHaveBeenLastCalledWith(2, { given: 'you Have been?' });
        await wrapper.get('[data-test="next"]').trigger('click');

        // 3–5. typed types
        for (const [id, task] of [[3, 'Fill the gap'], [4, 'Make it a question'], [5, 'Fix the mistake']] as const) {
            expect(wrapper.get('[data-test="task"]').text()).toBe(task);
            await wrapper.get('[data-test="answer-input"]').setValue('answer');
            await wrapper.get('[data-test="check"]').trigger('click');
            await flushPromises();
            expect(api.check).toHaveBeenLastCalledWith(id, { given: 'answer' });
            await wrapper.get('[data-test="next"]').trigger('click');
            await flushPromises();
        }

        expect(api.complete).toHaveBeenCalledTimes(1);
        expect(wrapper.get('[data-test="score"]').text()).toBe('90%');
        expect(wrapper.get('[data-test="confidence-after"]').text()).toBe('78%');
        expect(wrapper.findAll('[data-test="review-item"]')).toHaveLength(1);
        expect(wrapper.find('[data-test="mark-learned"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="practice-mistakes"]').exists()).toBe(true);
    });

    it('shows the hint, then the answer, on a typed exercise', async () => {
        api.startRound.mockResolvedValue({ status: 'ready', exercises: [round[2]], availableCount: 5 });
        api.check
            .mockResolvedValueOnce({ correct: false, hint: 'Irregular verb.', struckOptionIndex: null })
            .mockResolvedValueOnce({ correct: false, outcome: 'answer_shown', answer: 'has lost', explanation: 'lose – lost – lost.' });
        const { wrapper } = await mountPage();

        await wrapper.get('[data-test="answer-input"]').setValue('has losed');
        await wrapper.get('[data-test="check"]').trigger('click');
        await flushPromises();
        expect(wrapper.get('[data-test="feedback-hint"]').text()).toContain('Irregular verb.');
        expect(wrapper.text()).not.toContain('has lost');
        expect(wrapper.get('[data-test="check"]').text()).toBe('Try again');

        await wrapper.get('[data-test="show-answer"]').trigger('click');
        await flushPromises();
        expect(wrapper.get('[data-test="feedback-answer"]').text()).toContain('has lost');
        expect(wrapper.get('[data-test="next"]').text()).toBe('See result');
    });

    it('says when exercises cannot be prepared', async () => {
        api.startRound.mockResolvedValue({ status: 'unavailable', reason: 'unavailable', exercises: [], availableCount: 0 });
        const { wrapper } = await mountPage();

        expect(wrapper.get('[data-test="unavailable"]').text()).toContain("Exercises can't be prepared right now");
    });

    it('closing before answering anything leaves without saving', async () => {
        const { wrapper, router } = await mountPage();

        await wrapper.get('[data-test="close"]').trigger('click');
        await flushPromises();

        expect(router.currentRoute.value.fullPath).toBe('/grammar/7');
        expect(api.complete).not.toHaveBeenCalled();
    });
});
