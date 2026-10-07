import { afterEach, describe, expect, it, vi } from 'vitest';
import { useGrammarPracticeRound } from '../useGrammarPracticeRound';
import type { GrammarPracticeExercise, GrammarPracticeResult } from '../../types';

function exercise(id: number, type: GrammarPracticeExercise['type'] = 'cloze'): GrammarPracticeExercise {
    return { id, ruleId: 7, type, level: type === 'multiple_choice' || type === 'build' ? 'easy' : 'hard', instruction: null, prompt: `P${id}`, options: null, tiles: null, origin: 'ai' };
}

const result = { attemptId: 1, scorePct: 50 } as GrammarPracticeResult;

function fakeApi(overrides: Record<string, unknown> = {}) {
    return {
        getOverview: vi.fn(),
        startRound: vi.fn().mockResolvedValue({ status: 'ready', exercises: [exercise(1), exercise(2)], availableCount: 2 }),
        check: vi.fn(),
        report: vi.fn(),
        complete: vi.fn().mockResolvedValue(result),
        ...overrides,
    };
}

const options = { ruleId: 7, level: 'medium' as const, count: 10, pollMs: 10 };

afterEach(() => vi.useRealTimers());

describe('useGrammarPracticeRound', () => {
    it('waits while exercises are prepared, then starts the round', async () => {
        vi.useFakeTimers();
        const api = fakeApi({
            startRound: vi.fn()
                .mockResolvedValueOnce({ status: 'preparing', exercises: [], availableCount: 0 })
                .mockResolvedValueOnce({ status: 'ready', exercises: [exercise(1)], availableCount: 5 }),
        });
        const round = useGrammarPracticeRound(options, api as never);

        await round.start();
        expect(round.phase.value).toBe('preparing');

        await vi.advanceTimersByTimeAsync(10);
        expect(round.phase.value).toBe('answering');
        expect(api.startRound).toHaveBeenCalledTimes(2);
    });

    it('says why a round cannot start', async () => {
        const api = fakeApi({ startRound: vi.fn().mockResolvedValue({ status: 'unavailable', reason: 'limited', exercises: [], availableCount: 0 }) });
        const round = useGrammarPracticeRound(options, api as never);

        await round.start();

        expect(round.phase.value).toBe('unavailable');
        expect(round.unavailableReason.value).toBe('limited');
    });

    it('hint → retry → answer, with exactly one outcome per exercise', async () => {
        const api = fakeApi({
            check: vi.fn()
                .mockResolvedValueOnce({ correct: false, hint: 'Think of the 3rd form.', struckOptionIndex: null })
                .mockResolvedValueOnce({ correct: false, outcome: 'answer_shown', answer: 'has lost', explanation: 'x' }),
        });
        const round = useGrammarPracticeRound(options, api as never);
        await round.start();

        await round.submit('has losed');
        expect(round.hint.value).toBe('Think of the 3rd form.');
        expect(round.attempt.value).toBe(2);
        expect(round.phase.value).toBe('answering');
        expect(api.check).toHaveBeenLastCalledWith(1, { given: 'has losed' });

        await round.submit('has loosed');
        expect(api.check).toHaveBeenLastCalledWith(1, { given: 'has loosed' });
        expect(round.phase.value).toBe('settled');
        expect(round.settled.value?.answer).toBe('has lost');
        expect(round.items.value).toEqual([expect.objectContaining({ exerciseId: 1, outcome: 'answer_shown', attempts: 2, given: 'has loosed' })]);

        // A settled exercise can't be answered again.
        await round.submit('has lost');
        expect(api.check).toHaveBeenCalledTimes(2);
    });

    it('Show answer settles the exercise without a second try', async () => {
        const api = fakeApi({ check: vi.fn().mockResolvedValue({ correct: false, outcome: 'answer_shown', answer: 'a' }) });
        const round = useGrammarPracticeRound(options, api as never);
        await round.start();

        await round.showAnswer();

        expect(api.check).toHaveBeenCalledWith(1, { given: null, showAnswer: true });
        expect(round.items.value[0].outcome).toBe('answer_shown');
    });

    it('a reported exercise is replaced in place and logged as reported', async () => {
        const api = fakeApi({ report: vi.fn().mockResolvedValue({ replacement: exercise(9) }) });
        const round = useGrammarPracticeRound(options, api as never);
        await round.start();

        await round.report();

        expect(api.report).toHaveBeenCalledWith(1, { level: 'medium', roundExerciseIds: [1, 2], reason: null });
        expect(round.exercises.value.map((e) => e.id)).toEqual([9, 2]);
        expect(round.current.value?.id).toBe(9);
        expect(round.items.value).toEqual([expect.objectContaining({ exerciseId: 1, outcome: 'reported' })]);
    });

    it('saves the round once, only after the last exercise', async () => {
        const api = fakeApi({ check: vi.fn().mockResolvedValue({ correct: true, outcome: 'first_try', answer: 'a' }) });
        const round = useGrammarPracticeRound({ ...options, contentId: 3 }, api as never);
        await round.start();

        await round.submit('a');
        await round.next();
        expect(api.complete).not.toHaveBeenCalled();

        await round.submit('a');
        await round.next();

        expect(api.complete).toHaveBeenCalledTimes(1);
        expect(api.complete).toHaveBeenCalledWith(7, expect.objectContaining({ level: 'medium', contentId: 3, items: [expect.objectContaining({ exerciseId: 1 }), expect.objectContaining({ exerciseId: 2 })] }));
        expect(round.phase.value).toBe('result');
        expect(round.result.value).toEqual(result);
    });

    it('Practice mistakes starts a new round over the given exercises', async () => {
        const api = fakeApi();
        const round = useGrammarPracticeRound(options, api as never);
        await round.start();

        await round.restart([2]);

        expect(api.startRound).toHaveBeenLastCalledWith(7, { level: 'medium', count: 10, exerciseIds: [2] });
        expect(round.items.value).toEqual([]);

        api.check = vi.fn().mockResolvedValue({ correct: true, outcome: 'after_hint', answer: 'a' });
        await round.submit('a');
        await round.next();
        await round.next();
        expect(api.complete).toHaveBeenLastCalledWith(7, expect.objectContaining({ replay: true }));
    });
});
