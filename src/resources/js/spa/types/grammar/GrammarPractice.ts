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
    rule_id: number;
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
    score_pct: number;
    correct_count: number;
    scored_count: number;
    level: GrammarPracticeLevel | null;
    completed_at: string;
}

/** GET /api/grammar-rules/:id/practice */
export interface GrammarPracticeOverview {
    rule: { id: number; title: string };
    available_count: number;
    unseen_count: number;
    preparing: boolean;
    can_generate: boolean;
    last_result: GrammarPracticeLastResult | null;
    in_my_list: boolean;
    learned: boolean;
    confidence_calculated: number | null;
}

/** POST /api/grammar-rules/:id/practice/rounds — 200 ready, 202 preparing, 503 unavailable. */
export interface GrammarPracticeRoundResponse {
    status: 'ready' | 'preparing' | 'unavailable';
    /** For unavailable: `limited` (daily batches used up) or `unavailable` (AI off / failed). */
    reason?: 'limited' | 'unavailable';
    exercises: GrammarPracticeExercise[];
    available_count: number;
}

/** POST /api/grammar-exercises/:id/check */
export interface GrammarPracticeCheckResponse {
    correct: boolean;
    /** Set once the exercise is settled (correct, or answer shown). */
    outcome?: Exclude<GrammarPracticeOutcome, 'reported'>;
    /** First wrong answer: a nudge that never contains the answer. */
    hint?: string;
    /** Choose the form: the wrong option to strike out. */
    struck_option_index?: number | null;
    answer?: string;
    explanation?: string | null;
}

export interface GrammarPracticeResultItem {
    exercise_id: number;
    outcome: GrammarPracticeOutcome;
    attempts: number;
    given: string | null;
    ms: number;
}

export interface GrammarPracticeReviewItem {
    exercise_id: number;
    type: GrammarExerciseType;
    instruction: string | null;
    prompt: string;
    given: string | null;
    answer: string;
    outcome: 'after_hint' | 'answer_shown';
}

/** POST /api/grammar-rules/:id/practice/complete */
export interface GrammarPracticeResult {
    attempt_id: number;
    level: GrammarPracticeLevel;
    score_pct: number;
    first_try: number;
    after_hint: number;
    missed: number;
    reported: number;
    confidence_before: number | null;
    confidence_after: number | null;
    in_my_list: boolean;
    learned: boolean;
    can_mark_learned: boolean;
    to_review: GrammarPracticeReviewItem[];
}
