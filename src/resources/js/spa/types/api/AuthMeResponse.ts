import type { User } from '../user/User';

/**
 * Response of GET /api/auth/me.
 */
export interface AuthMeResponse {
    user: User | null;
    roles: string[];
}
