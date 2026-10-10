export interface GrammarRuleEditorExample {
    id?: number;
    language: string;
    example: string;
    translation: string | null;
    is_primary: boolean;
    sort_order: number;
}

export interface GrammarRuleEditorDraft {
    title: string;
    summary: string | null;
    body: string | null;
    examples: GrammarRuleEditorExample[];
}

export interface GrammarRuleEditorRule extends GrammarRuleEditorDraft {
    id: number;
    slug: string;
    language: string;
    level: string | null;
    editor_version: number;
    updated_at: string;
}

export interface GrammarRuleEditorTurn {
    role: 'user' | 'assistant';
    content: string;
}

export interface GrammarRuleEditorRevision {
    id: number;
    created_at: string;
    old: GrammarRuleEditorDraft;
    new: GrammarRuleEditorDraft;
}
