/**
 * A practice exercise for a grammar rule (GrammarRuleExerciseResource),
 * from GET /api/grammar-rules/:id/exercises. Grading is entirely
 * client-side — the answer ships with the payload, matching how examples'
 * translations are already shown up front elsewhere in this app.
 */
export interface GrammarRuleExercise {
    id: number;
    type: 'cloze' | 'multiple_choice';
    prompt: string;
    /** Correct answer text, present for type=cloze. */
    answer: string | null;
    /** Answer options, present for type=multiple_choice. */
    options: string[] | null;
    /** 0-based index into options, present for type=multiple_choice. */
    answer_index: number | null;
    explanation: string | null;
}

export interface GrammarRuleExercisesResponse {
    exercises: GrammarRuleExercise[];
}
