import axios, { type AxiosRequestConfig } from 'axios';
import type { ApiErrorResponse } from '../../types';

/**
 * Normalized error exposed by domain API modules.
 * It keeps the existing Axios-compatible response shape so current callers
 * using parseApiError continue to work during the migration.
 */
export class ApiClientError extends Error {
    response?: {
        status?: number;
        data?: ApiErrorResponse;
    };

    constructor(message: string, status?: number, data?: ApiErrorResponse) {
        super(message);
        this.name = 'ApiClientError';
        this.response = { status, data };
    }
}

function getErrorMessage(data?: ApiErrorResponse): string | undefined {
    if (data?.message) return data.message;

    return Object.values(data?.errors ?? {})
        .flat()
        .find(Boolean);
}

/**
 * Typed request boundary for new domain API modules.
 * Existing modules may keep using Axios until their domain is migrated.
 */
export async function request<T>(config: AxiosRequestConfig): Promise<T> {
    try {
        const response = await axios.request<T>(config);
        return response.data;
    } catch (error: unknown) {
        if (!axios.isAxiosError<ApiErrorResponse>(error)) {
            throw error;
        }

        const data = error.response?.data;
        throw new ApiClientError(
            getErrorMessage(data) ?? error.message,
            error.response?.status,
            data,
        );
    }
}
