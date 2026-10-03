import type { LexemeWithLearned } from '../lexeme/LexemeWithLearned';

/**
 * Response of GET /api/content/:id/lexemes.
 */
export interface ContentLexemesResponse {
    lexemes: LexemeWithLearned[];
}
