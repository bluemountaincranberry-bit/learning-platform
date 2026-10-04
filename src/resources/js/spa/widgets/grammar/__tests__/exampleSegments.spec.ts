import { describe, expect, it } from 'vitest';
import { exampleSegments } from '../exampleSegments';

describe('exampleSegments', () => {
    it('marks one span', () => {
        expect(exampleSegments("He doesn't like tea.", [[3, 15]])).toEqual([
            { text: 'He ', target: false },
            { text: "doesn't like", target: true },
            { text: ' tea.', target: false },
        ]);
    });

    it('marks split forms at the edges', () => {
        expect(exampleSegments('Do you live here?', [[0, 2], [7, 11]])).toEqual([
            { text: 'Do', target: true },
            { text: ' you ', target: false },
            { text: 'live', target: true },
            { text: ' here?', target: false },
        ]);
    });

    it('counts characters, not UTF-16 units', () => {
        expect(exampleSegments('😀 She works.', [[6, 11]])).toEqual([
            { text: '😀 She ', target: false },
            { text: 'works', target: true },
            { text: '.', target: false },
        ]);
    });

    it('falls back to plain text without or with broken spans', () => {
        expect(exampleSegments('Plain.', null)).toEqual([{ text: 'Plain.', target: false }]);
        expect(exampleSegments('Plain.', [[4, 99], [3, 1]])).toEqual([{ text: 'Plain.', target: false }]);
    });

    it('ignores overlapping spans', () => {
        expect(exampleSegments('abcdef', [[0, 3], [2, 4]])).toEqual([
            { text: 'abc', target: true },
            { text: 'def', target: false },
        ]);
    });
});
