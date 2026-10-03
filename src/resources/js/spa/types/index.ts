/**
 * SPA types barrel. Import: import type { Content, User } from '../types'.
 * Domain folders; one type per file by default; API responses *Response.
 */
export type { Content, ContentListParams } from './content';
export type { User } from './user';
export type { LexemeWithLearned, LexemeExampleItem, LexemeAssociationItem, LexemeConfidence } from './lexeme';
export type { LexemeDetail, LexemeDetailResponse, LexemeTranslationItem, MoreExamplesResponse } from './lexeme';
export type { BulkLexemeActionResponse, BulkLexemeActionResult } from './lexeme';
export type {
    GrammarRule,
    GrammarRuleExample,
    GrammarRuleExampleKind,
    GrammarRuleExampleGenerationStatus,
    GrammarRuleExampleRequestStatus,
    GrammarRuleExamplesResponse,
    GrammarRuleTopic,
    GrammarRuleListParams,
    GrammarRuleListResponse,
    GrammarRuleOneResponse,
    ContentGrammarRulesResponse,
    GrammarPracticeLevel,
    GrammarExerciseType,
    GrammarPracticeOutcome,
    GrammarPracticeExercise,
    GrammarPracticeLastResult,
    GrammarPracticeOverview,
    GrammarPracticeRoundResponse,
    GrammarPracticeCheckResponse,
    GrammarPracticeResultItem,
    GrammarPracticeReviewItem,
    GrammarPracticeResult,
} from './grammar';
export type { ApiError, ApiErrorResponse } from './api';
export { parseApiError } from './api';
export type {
    AuthLoginResponse,
    AuthMeResponse,
    ContentListResponse,
    ContentOneResponse,
    ContentCategoriesResponse,
    ContentLexemesResponse,
    SubmitYoutubePayload,
    SubmitYoutubeResponse,
    MySubmissionsResponse,
    ProfileResponse,
    ProgressStatsResponse,
    RecommendedContentsResponse,
    RecommendedLexemesResponse,
    LearnedLexemesResponse,
    LearnedLexemeItem,
    LearnedLexemesParams,
    LearnedGrammarRulesResponse,
    LearnedGrammarRuleItem,
    LearnedGrammarRulesParams,
    AiSuggestionExample,
    AiSuggestionLexemeCandidate,
    AiSuggestionGrammarCandidate,
    AiSuggestionsResponse,
    AcceptAiSuggestionsPayload,
    AcceptAiSuggestionsResponse,
    TranscriptResponse,
    TranscriptSegment,
    TranscriptLexemeReference,
    TranscriptTranslationsResponse,
    CreateTranscriptLexemeResponse,
} from './api';
export type { RouteMeta } from './router';
export type { ExerciseAttempt, ExerciseAttemptResponse } from './api/ExerciseAttemptResponse';
