export interface TranscriptLexemeReference {
    content_lexeme_id: number;
    text: string;
    start_offset: number;
    end_offset: number;
    surface_text: string;
    match_type: string;
}

export interface TranscriptSegment {
    id: number;
    sequence: number;
    start_ms: number;
    end_ms: number | null;
    text: string;
    language: string | null;
    source: string;
    lexemes: TranscriptLexemeReference[];
}

export interface TranscriptResponse {
    content_id: number;
    language: string;
    segments: TranscriptSegment[];
    has_more: boolean;
    next_from_ms: number | null;
}

export interface TranscriptTranslationsResponse {
    status: 'pending' | 'ready';
    language: string;
    translations: Record<number, string>;
}

export interface CreateTranscriptLexemeResponse {
    content_lexeme_id: number;
    text: string;
    translation: string;
}
