/**
 * GET /api/srs/due response.
 */
export interface SrsCardDue {
    id: number;
    user_id: number;
    content_id: number;
    item_key: string;
    lexeme_display: string;
    state: string;
    interval_days: number;
    ease_factor: number;
    next_review_at: string;
    created_at: string;
    updated_at: string;
}

export interface SrsDueResponse {
    items: SrsCardDue[];
}
