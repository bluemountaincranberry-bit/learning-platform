import type { Content } from '../content/Content';

/** GET /api/ai/recommended/contents response */
export interface RecommendedContentsResponse {
    data: Content[];
}

/** GET /api/ai/recommended/lexemes response */
export interface RecommendedLexemesResponse {
    data: { id: number; text: string; content_id: number }[];
}
