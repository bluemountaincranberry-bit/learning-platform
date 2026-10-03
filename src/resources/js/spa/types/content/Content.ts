/**
 * Content entity as returned by API (ContentResource).
 * Progress fields present when request is authenticated (CP-01).
 */
export interface Content {
    id: number;
    title: string;
    type: string;
    language: string;
    level: string | null;
    status: string;
    source_url: string | null;
    /** Full transcript/body text. Only present on the single-content endpoint (GET /api/content/{id}), not the list. */
    source_text?: string | null;
    origin: string | null;
    created_by: number | null;
    created_at: string;
    updated_at: string;
    /** Present for authenticated user: words learned in this content */
    learned_count?: number;
    /** Present for authenticated user: words currently added to SRS learning */
    in_learning_count?: number;
    /** Present for authenticated user: total lexemes in this content */
    total_lexemes?: number;
    /** Present for authenticated user: 0–100 */
    progress_pct?: number;
    /** Present for authenticated user: passed the "Ready to watch" exam at least once. */
    ready_to_watch?: boolean;
    /** Task 9.6: only present on GET /api/content/my-submissions — how many
     * pending AI candidates (lexeme + grammar) are waiting on the owner's
     * review for this content's latest analysis run. */
    pending_ai_suggestions_count?: number;
}
