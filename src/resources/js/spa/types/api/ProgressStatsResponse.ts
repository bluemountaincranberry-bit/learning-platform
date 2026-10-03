export interface ProgressStatsOverview {
    total_learned: number;
    today_count: number;
    streak: number;
    daily_goal: number | null;
    week_count: number;
    month_count: number;
}

export interface ProgressStatsByContentItem {
    content_id: number;
    title: string;
    accuracy: number;
    total_answers: number;
    known_answers: number;
}

export interface ProgressStatsByLanguageLevel {
    language: string;
    level: string | null;
    total_answers: number;
    known_answers: number;
    accuracy: number;
}

export interface ProgressStatsWeakWord {
    lexeme: string;
    content_id: number;
    hint: string;
}

export interface ProgressStatsWeakGrammarTopic {
    grammar_rule_id: number;
    title: string;
    mistake_count: number;
}

export interface ProgressStatsRecommendation {
    type: string;
    content_id?: number;
    title?: string;
    hint?: string;
    activity?: 'dictation' | 'cloze' | 'shadowing' | 'recall';
    skill?: string;
}

export interface ProgressStatsSkillAccuracy {
    skill: string;
    attempts: number;
    correct: number;
    accuracy: number;
}

export interface ProgressStatsRetentionPoint {
    attempts: number;
    correct: number;
    accuracy: number;
}

export interface ProgressStatsPoints {
    total: number;
    today: number;
    week: number;
    by_activity: Record<string, number>;
}

export interface ProgressStatsResponse {
    overview: ProgressStatsOverview;
    by_content: {
        best: ProgressStatsByContentItem[];
        weak: ProgressStatsByContentItem[];
    };
    by_language_level: ProgressStatsByLanguageLevel[];
    weak_words: ProgressStatsWeakWord[];
    weak_grammar_topics: ProgressStatsWeakGrammarTopic[];
    recommendations: ProgressStatsRecommendation[];
    skill_accuracy: ProgressStatsSkillAccuracy[];
    retention: Record<'1' | '7' | '30', ProgressStatsRetentionPoint>;
    points: ProgressStatsPoints;
}
