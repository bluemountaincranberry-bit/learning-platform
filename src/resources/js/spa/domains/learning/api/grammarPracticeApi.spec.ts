import { beforeEach, describe, expect, it, vi } from 'vitest';
import { grammarPracticeApi } from './grammarPracticeApi';

const request = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }));
vi.mock('axios', () => ({ default: request }));

describe('grammarPracticeApi contract mapping', () => {
    beforeEach(() => vi.resetAllMocks());

    it('maps nested practice responses from wire snake_case to domain camelCase', async () => {
        request.get.mockResolvedValue({
            data: {
                available_count: 3,
                last_result: { correct_count: 2, completed_at: '2026-10-07T08:00:00Z' },
            },
        });

        await expect(grammarPracticeApi.getOverview(7)).resolves.toEqual({
            availableCount: 3,
            lastResult: { correctCount: 2, completedAt: '2026-10-07T08:00:00Z' },
        });
    });

    it('maps domain request objects to wire snake_case recursively', async () => {
        request.post.mockResolvedValue({ data: { status: 'ready', available_count: 5, exercises: [{ id: 11, rule_id: 7 }] } });

        const result = await grammarPracticeApi.startRound(7, { level: 'medium', count: 5, exerciseIds: [11] });

        expect(request.post).toHaveBeenCalledWith(
            '/api/grammar-rules/7/practice/rounds',
            { level: 'medium', count: 5, exercise_ids: [11] },
            { validateStatus: expect.any(Function) },
        );
        expect(result).toEqual({ status: 'ready', availableCount: 5, exercises: [{ id: 11, ruleId: 7 }] });
    });
});
