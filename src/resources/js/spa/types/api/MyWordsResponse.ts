import type { LexemeAssociationItem, LexemeExampleItem } from '../lexeme';

export type MyWordStatus = 'all' | 'in_learning' | 'known' | 'new';

export interface MyWordContext {
    content_lexeme_id: number;
    content_id: number | null;
    content_title: string | null;
    language: string | null;
    level: string | null;
}

export interface MyWordItem {
    id: number;
    lexeme_id: number;
    content_lexeme_id: number | null;
    lexeme: string;
    status: Exclude<MyWordStatus, 'all'>;
    learned_at: string | null;
    in_review: boolean;
    language: string | null;
    level: string | null;
    translation?: string | null;
    example?: string | null;
    examples?: LexemeExampleItem[];
    associations?: LexemeAssociationItem[];
    contexts: MyWordContext[];
}

export interface MyWordsParams {
    status?: MyWordStatus;
    language?: string;
    level?: string;
    content_id?: number;
    search?: string;
    per_page?: number;
    page?: number;
}

export interface MyWordsResponse {
    data: MyWordItem[];
    meta: {
        current_page: number;
        per_page: number;
        total: number;
        last_page?: number;
    };
    links?: Record<string, string | null>;
}
