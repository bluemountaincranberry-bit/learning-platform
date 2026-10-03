export interface QuizQuestion {
    type: 'multiple_choice' | 'gap_fill';
    prompt: string;
    answer: string;
    choices?: string[];
}
