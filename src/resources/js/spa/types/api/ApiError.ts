/**
 * Shape of an error response from the API (Laravel validation or message).
 */
export interface ApiErrorResponse {
    message?: string;
    errors?: Record<string, string[]>;
}

/**
 * Axios-style error with optional response.data.
 */
export interface ApiError extends Error {
    response?: {
        status?: number;
        data?: ApiErrorResponse;
    };
}

/**
 * Extract a user-facing error message from an unknown catch value.
 */
export function parseApiError(error: unknown, fallback = 'Something went wrong.'): string {
    const err = error as ApiError;
    const data = err?.response?.data;
    if (data?.message) return data.message;
    if (data?.errors && typeof data.errors === 'object') {
        const first = Object.values(data.errors).flat().find(Boolean);
        if (first) return first;
    }
    return fallback;
}
