import type { Content } from '../content/Content';

/**
 * Response of GET /api/content (Laravel paginated collection).
 */
export interface ContentListResponse {
    data: Content[];
    links?: Record<string, unknown>;
    meta?: Record<string, unknown>;
}
