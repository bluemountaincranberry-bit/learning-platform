import type { LexemeAssociationItem, LexemeExampleItem } from '../lexeme';

/**
 * One due SRS card enriched with translation/examples/associations for the
 * trainer's Review mode card (GET /api/training/review-queue).
 */
export interface TrainingReviewItem {
    card_id: number;
    content_id: number;
    content_lexeme_id: number | null;
    item_key: string;
    lexeme_display: string;
    part_of_speech?: string | null;
    level?: string | null;
    state: string;
    next_review_at: string | null;
    translation: string | null;
    example: string | null;
    examples: LexemeExampleItem[];
    associations: LexemeAssociationItem[];
}

export interface TrainingSelectedLexemeItem {
    content_id: number;
    content_lexeme_id: number;
    lexeme_display: string;
    part_of_speech?: string | null;
    level?: string | null;
    translation: string | null;
    example: string | null;
    examples: LexemeExampleItem[];
    associations: LexemeAssociationItem[];
}

export interface TrainingReviewQueueResponse {
    items: TrainingReviewItem[];
}

export interface TrainingSelectedLexemesResponse {
    items: TrainingSelectedLexemeItem[];
}
