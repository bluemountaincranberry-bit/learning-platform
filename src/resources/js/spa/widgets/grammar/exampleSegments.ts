export interface ExampleSegment {
    text: string;
    target: boolean;
}

/**
 * Splits an example sentence into plain and grammar-form pieces. Spans are
 * [start, end) offsets in characters (code points) — the server counts the
 * same way — so slicing goes through Array.from, not string indices.
 * Invalid or overlapping spans are ignored; no spans → one plain segment.
 */
export function exampleSegments(text: string, spans: [number, number][] | null | undefined): ExampleSegment[] {
    const chars = Array.from(text);
    const valid = (spans ?? [])
        .filter(([start, end]) => Number.isInteger(start) && Number.isInteger(end) && start >= 0 && end > start && end <= chars.length)
        .sort((a, b) => a[0] - b[0]);

    const segments: ExampleSegment[] = [];
    let cursor = 0;

    for (const [start, end] of valid) {
        if (start < cursor) continue;
        if (start > cursor) segments.push({ text: chars.slice(cursor, start).join(''), target: false });
        segments.push({ text: chars.slice(start, end).join(''), target: true });
        cursor = end;
    }

    if (cursor < chars.length || segments.length === 0) {
        segments.push({ text: chars.slice(cursor).join(''), target: false });
    }

    return segments;
}
