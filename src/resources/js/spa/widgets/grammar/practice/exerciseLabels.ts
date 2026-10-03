import type { GrammarExerciseType, GrammarPracticeLevel } from '../../../types';

/** Task line when the exercise has no `instruction` of its own. */
export const EXERCISE_TASK: Record<GrammarExerciseType, string> = {
    multiple_choice: 'Choose the right form',
    build: 'Build the sentence',
    cloze: 'Fill the gap',
    transform: 'Rewrite the sentence',
    fix: 'Fix the mistake',
};

export const LEVEL_LABEL: Record<GrammarPracticeLevel, string> = { easy: 'Easy', medium: 'Medium', hard: 'Hard' };

export const LEVEL_HINT: Record<GrammarPracticeLevel, string> = {
    easy: 'choose · build',
    medium: 'easy → hard',
    hard: 'type · transform · fix',
};

/** Splits a prompt around its blank ("_____") so the gap can be drawn without v-html. */
export function promptParts(prompt: string): { before: string; after: string } | null {
    const match = /_{3,}/.exec(prompt);
    if (!match) return null;
    return { before: prompt.slice(0, match.index), after: prompt.slice(match.index + match[0].length) };
}
