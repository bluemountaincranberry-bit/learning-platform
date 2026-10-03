/** Per-id outcome of a bulk lexeme action (task 7.5). */
export interface BulkLexemeActionResult {
    id: number;
    ok: boolean;
    message?: string;
}

/**
 * Response of POST /api/content/lexemes/bulk-mark-learned and
 * .../bulk-start-learning. A failure on one id never loses the others —
 * this shape lets the SPA show "8 of 10 marked, 2 failed" rather than an
 * all-or-nothing result.
 */
export interface BulkLexemeActionResponse {
    results: BulkLexemeActionResult[];
    succeeded: number;
    failed: number;
}
