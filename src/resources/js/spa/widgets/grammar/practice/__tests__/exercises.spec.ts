import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import BuildExercise from '../BuildExercise.vue';
import ChooseExercise from '../ChooseExercise.vue';
import ExerciseFeedback from '../ExerciseFeedback.vue';
import TypedExercise from '../TypedExercise.vue';
import type { GrammarPracticeExercise } from '../../../../types';

function exercise(overrides: Partial<GrammarPracticeExercise>): GrammarPracticeExercise {
    return {
        id: 1, rule_id: 7, type: 'cloze', level: 'hard', instruction: null,
        prompt: 'She _____ (lose) her keys.', options: null, tiles: null, origin: 'ai',
        ...overrides,
    };
}

describe('ChooseExercise', () => {
    const choose = exercise({ type: 'multiple_choice', level: 'easy', prompt: 'I _____ this film twice.', options: ['saw', 'have seen', 'seen'] });

    it('answers with the tapped option index', async () => {
        const wrapper = mount(ChooseExercise, { props: { exercise: choose, struck: [], answer: null, disabled: false } });

        await wrapper.findAll('[data-test="option"]')[1].trigger('click');

        expect(wrapper.emitted('answer')).toEqual([['1']]);
    });

    it('strikes out the wrong option after a hint and fills the gap once answered', () => {
        const wrapper = mount(ChooseExercise, { props: { exercise: choose, struck: [0], answer: 'have seen', disabled: true } });
        const options = wrapper.findAll('[data-test="option"]');

        expect(options[0].classes()).toContain('line-through');
        expect(options[0].attributes('disabled')).toBeDefined();
        expect(options[1].classes()).toContain('border-success');
        expect(wrapper.get('[data-test="gap"]').text()).toBe('have seen');
    });
});

describe('BuildExercise', () => {
    const build = exercise({ type: 'build', level: 'easy', prompt: 'Make a question', tiles: ['been', 'Have', 'to Japan?', 'you'] });

    it('builds the sentence from tapped tiles and lets a tile go back', async () => {
        const wrapper = mount(BuildExercise, {
            props: { exercise: build, disabled: false, modelValue: '', 'onUpdate:modelValue': (v: string) => wrapper.setProps({ modelValue: v }) },
        });
        const tiles = wrapper.findAll('[data-test="tile"]');

        for (const i of [1, 3, 0, 2]) await tiles[i].trigger('click');
        expect(wrapper.props('modelValue')).toBe('Have you been to Japan?');
        expect(tiles[1].attributes('disabled')).toBeDefined();

        await wrapper.findAll('[data-test="placed-tile"]')[2].trigger('click');
        expect(wrapper.props('modelValue')).toBe('Have you to Japan?');
    });
});

describe('TypedExercise', () => {
    it('draws the gap, binds the input and submits on Enter', async () => {
        const wrapper = mount(TypedExercise, {
            props: { exercise: exercise({}), disabled: false, state: 'idle' as const, modelValue: '', 'onUpdate:modelValue': (v: string) => wrapper.setProps({ modelValue: v }) },
        });

        expect(wrapper.find('[data-test="gap"]').exists()).toBe(true);
        const input = wrapper.get('[data-test="answer-input"]');
        await input.setValue('has lost');
        await input.trigger('keydown', { key: 'Enter' });

        expect(wrapper.props('modelValue')).toBe('has lost');
        expect(wrapper.emitted('submit')).toHaveLength(1);
    });

    it.each(['transform', 'fix'] as const)('shows the %s sentence as is and marks a wrong answer', (type) => {
        const wrapper = mount(TypedExercise, {
            props: { exercise: exercise({ type, prompt: 'They have finished.' }), disabled: true, state: 'wrong', modelValue: 'x' },
        });

        expect(wrapper.get('[data-test="prompt"]').text()).toBe('They have finished.');
        expect(wrapper.get('[data-test="answer-input"]').classes()).toContain('border-danger');
    });
});

describe('ExerciseFeedback', () => {
    it('shows only the hint before the exercise is settled', () => {
        const wrapper = mount(ExerciseFeedback, { props: { hint: 'Irregular verb: think of its 3rd form.', settled: null } });

        expect(wrapper.get('[data-test="feedback-hint"]').text()).toContain('Irregular verb');
        expect(wrapper.find('[data-test="feedback-answer"]').exists()).toBe(false);
    });

    it('shows the answer with a See rule link after the second miss', async () => {
        const wrapper = mount(ExerciseFeedback, {
            props: { hint: 'x', settled: { correct: false, outcome: 'answer_shown', answer: 'has lost', explanation: 'lose – lost – lost.' } },
        });

        expect(wrapper.get('[data-test="feedback-answer"]').text()).toContain('has lost');
        expect(wrapper.find('[data-test="feedback-hint"]').exists()).toBe(false);
        await wrapper.get('button').trigger('click');
        expect(wrapper.emitted('seeRule')).toHaveLength(1);
    });

    it('confirms a correct answer', () => {
        const wrapper = mount(ExerciseFeedback, { props: { hint: null, settled: { correct: true, outcome: 'first_try', answer: 'has lost', explanation: null } } });

        expect(wrapper.get('[data-test="feedback-correct"]').text()).toContain('Correct');
    });
});
