import type { Content } from '../content/Content';

/**
 * Response of GET /api/content/:id.
 */
export interface ContentOneResponse {
    content: Content;
}
