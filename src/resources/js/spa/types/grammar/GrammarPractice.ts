/**
 * Grammar practice on one rule (VIK-31): start card, round, answer check,
 * result. Answers are checked on the server; an exercise arrives without its
 * answer, hint or explanation (see GrammarPracticeService).
 */
export type GrammarPracticeLevel = 'easy' | 'medium' | 'hard';

export type GrammarExerciseType = 'multiple_choice' | 'build' | 'cloze' | 'transform' | 'fix';

export type GrammarPracticeOutcome = 'first_try' | 'after_hint' | 'answer_shown' | 'reported';

export interface GrammarPracticeExercise {
    id: number;
    ruleId: number;
    type: GrammarExerciseType;
    level: 'easy' | 'hard';
    /** Short task line ("Make it a question"); the UI falls back to a per-type label. */
    instruction: string | null;
    prompt: string;
    /** Choose the form. */
    options: string[] | null;
    /** Build the sentence, already shuffled. */
    tiles: string[] | null;
    origin: 'admin' | 'ai';
}

export interface GrammarPracticeLastResult {
    scorePct: number;
    correctCount: number;
    scoredCount: number;
    level: GrammarPracticeLevel | null;
    completedAt: string;
}

/** GET /api/grammar-rules/:id/practice */
export interface GrammarPracticeOverview {
    rule: { id: number; title: string };
    availableCount: number;
    unseenCount: number;
    preparing: boolean;
    canGenerate: boolean;
    lastResult: GrammarPracticeLastResult | null;
    inMyList: boolean;
    learned: boolean;
    confidenceCalculated: number | null;
}

/** POST /api/grammar-rules/:id/practice/rounds — 200 ready, 202 preparing, 503 unavailable. */
export interface GrammarPracticeRoundResponse {
    status: 'ready' | 'preparing' | 'unavailable';
    /** For unavailable: `limited` (daily batches used up) or `unavailable` (AI off / failed). */
    reason?: 'limited' | 'unavailable';
    exercises: GrammarPracticeExercise[];
    availableCount: number;
}

/** POST /api/grammar-exercises/:id/check */
export interface GrammarPracticeCheckResponse {
    correct: boolean;
    /** Set once the exercise is settled (correct, or answer shown). */
    outcome?: Exclude<GrammarPracticeOutcome, 'reported'>;
    /** First wrong answer: a nudge that never contains the answer. */
    hint?: string;
    /** Choose the form: the wrong option to strike out. */
    struckOptionIndex?: number | null;
    answer?: string;
    explanation?: string | null;
}

/**
 * One exercise of a finished round. The server takes outcome and answer
 * from its own record of the checks; `outcome` only matters for `reported`.
 */
export interface GrammarPracticeResultItem {
    exerciseId: number;
    outcome: GrammarPracticeOutcome;
    attempts: number;
    given: string | null;
    ms: number;
}

export interface GrammarPracticeReviewItem {
    exerciseId: number;
    type: GrammarExerciseType;
    instruction: string | null;
    prompt: string;
    given: string | null;
    answer: string;
    outcome: 'after_hint' | 'answer_shown';
}

/** POST /api/grammar-rules/:id/practice/complete */
export interface GrammarPracticeResult {
    /** null for a "Practice mistakes" replay, which is not saved. */
    attemptId: number | null;
    level: GrammarPracticeLevel;
    scorePct: number;
    firstTry: number;
    afterHint: number;
    missed: number;
    reported: number;
    confidenceBefore: number | null;
    confidenceAfter: number | null;
    inMyList: boolean;
    learned: boolean;
    canMarkLearned: boolean;
    toReview: GrammarPracticeReviewItem[];
}
