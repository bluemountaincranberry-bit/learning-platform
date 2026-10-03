export interface ExerciseAttempt {
    id: number;
    status: 'processing' | 'completed' | 'failed';
    exercise_type: 'dictation' | 'shadowing' | 'speaking';
    user_text: string | null;
    score: number | null;
    is_correct: boolean | null;
    error_type: string | null;
    provider_result?: {
        pronunciation?: {
            accuracy?: number | null;
            fluency?: number | null;
            completeness?: number | null;
            prosody?: number | null;
        };
        [key: string]: unknown;
    };
}

export interface ExerciseAttemptResponse {
    attempt: ExerciseAttempt;
}
