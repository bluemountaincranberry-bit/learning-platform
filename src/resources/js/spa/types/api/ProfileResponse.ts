import type { User } from '../user/User';

/**
 * Response of GET /api/profile.
 */
export interface ProfileResponse {
    user: User;
    today_learned_count: number;
    streak_days: number;
}
