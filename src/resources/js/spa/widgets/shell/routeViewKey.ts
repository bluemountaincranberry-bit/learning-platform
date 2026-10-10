import type { RouteLocationNormalizedLoaded } from 'vue-router';

/**
 * Route path identifies the page instance. Query values only participate when
 * the page declares them as construction-time inputs in route meta.
 */
export function routeViewKey(route: Pick<RouteLocationNormalizedLoaded, 'path' | 'query' | 'meta'>): string {
    const queryFields = route.meta.pageKeyQuery;
    if (!Array.isArray(queryFields) || queryFields.length === 0) return route.path;

    const identity = Object.fromEntries(
        [...new Set(queryFields)].sort().map((field) => [field, route.query[field] ?? null]),
    );

    return `${route.path}?${JSON.stringify(identity)}`;
}
