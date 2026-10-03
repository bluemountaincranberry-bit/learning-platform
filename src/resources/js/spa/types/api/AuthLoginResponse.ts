import type { User } from '../user/User';

/**
 * Response of POST /api/auth/login.
 */
export interface AuthLoginResponse {
    message: string;
    token: string;
    user: User;
}
