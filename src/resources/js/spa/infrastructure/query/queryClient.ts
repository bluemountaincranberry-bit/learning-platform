import {
    MutationCache,
    QueryCache,
    QueryClient,
} from '@tanstack/vue-query';

/**
 * Application-wide server-state policy.
 *
 * Domain query modules may override these defaults when their data has a
 * different lifecycle, but the exception should be explicit and local.
 */
export function createAppQueryClient(): QueryClient {
    return new QueryClient({
        queryCache: new QueryCache(),
        mutationCache: new MutationCache(),
        defaultOptions: {
            queries: {
                staleTime: 5 * 60 * 1000,
                gcTime: 30 * 60 * 1000,
                refetchOnWindowFocus: false,
                retry: 1,
            },
            mutations: {
                retry: 0,
            },
        },
    });
}

export const queryClient = createAppQueryClient();
