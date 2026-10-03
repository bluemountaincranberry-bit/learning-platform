/**
 * Query params for content catalog list (ContentIndexRequest).
 */
export interface ContentListParams {
    type?: string;
    language?: string;
    level?: string;
    /** Full-text search query (optional). */
    q?: string;
    /** 'mine' scopes to the authenticated user's own submissions; dropped server-side for guests. */
    scope?: 'mine' | 'all';
}
