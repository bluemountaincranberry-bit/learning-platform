/**
 * User entity as returned by API (UserResource).
 */
export interface User {
    id: number;
    name: string;
    email: string;
    timezone: string | null;
    ui_language: string | null;
    /** Native/translation-target language — controls which language word translations are shown in. */
    translation_language: string | null;
    /** Self-reported CEFR level (A1-C2). A soft signal, considered but not enforced. */
    current_level: string | null;
    learning_goal: 'conversation' | 'work' | 'travel' | 'exam' | 'general' | null;
    /** 'thorough' | 'focused' | null. Null falls back to 'thorough' server-side — see AiAnalysisAutoDispatchService. */
    ai_extraction_thoroughness: string | null;
    daily_goal: number | null;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}
