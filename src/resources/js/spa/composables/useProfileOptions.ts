/** Human-readable names for CEFR_LEVELS (see useCatalog.ts) — shared by Settings and Onboarding. */
export const CEFR_LEVEL_NAMES: Record<string, string> = {
    A1: 'Beginner',
    A2: 'Elementary',
    B1: 'Intermediate',
    B2: 'Upper intermediate',
    C1: 'Advanced',
    C2: 'Proficient',
};

/** Native/translation-language choices — shared by Settings and Onboarding. */
export const TRANSLATION_LANGUAGE_OPTIONS: { value: string; label: string }[] = [
    { value: '', label: 'No preference' },
    { value: 'ru', label: 'Русский' },
    { value: 'en', label: 'English' },
    { value: 'es', label: 'Español' },
    { value: 'fr', label: 'Français' },
    { value: 'de', label: 'Deutsch' },
    { value: 'it', label: 'Italiano' },
    { value: 'pt', label: 'Português' },
    { value: 'zh', label: '中文' },
    { value: 'ja', label: '日本語' },
    { value: 'ko', label: '한국어' },
    { value: 'ar', label: 'العربية' },
    { value: 'tr', label: 'Türkçe' },
    { value: 'pl', label: 'Polski' },
    { value: 'nl', label: 'Nederlands' },
    { value: 'uk', label: 'Українська' },
];
