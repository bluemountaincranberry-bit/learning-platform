import type { Content } from '../content';

/**
 * Response of POST /api/content/submit-youtube.
 */
export interface SubmitYoutubeResponse {
    message: string;
    content: Content;
}
