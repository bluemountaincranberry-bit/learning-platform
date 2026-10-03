/**
 * Task 10.5: renders an occurrence's grammar tags (e.g. {"tense": "past"}
 * for "ran") as a short human-readable string, e.g. "past" or "past,
 * irregular". Shared between WordListItem.vue (inline badge) and
 * LexemeDetailPage.vue (Forms section) so the two stay visually consistent.
 */
export function formatGrammarFeatures(features?: Record<string, string | boolean> | null): string | null {
    if (!features) return null;

    const parts = Object.entries(features)
        .filter(([, value]) => value !== false)
        .map(([key, value]) => (key === 'is_irregular' ? 'irregular' : String(value)));

    return parts.length > 0 ? parts.join(', ') : null;
}
