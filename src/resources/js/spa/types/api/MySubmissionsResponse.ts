import type { Content } from '../content';

/**
 * Response of GET /api/content/my-submissions.
 */
export interface MySubmissionsResponse {
    data: Content[];
}
