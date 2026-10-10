/**
 * Extended route meta for SPA (used in router.beforeEach).
 */
export interface RouteMeta {
    [key: string]: unknown;
    [key: symbol]: unknown;
    requiresAuth?: boolean;
    requiresGuest?: boolean;
    /** Task: graph/prompt builder — admin role only, checked in router.beforeEach against authStore.roles. */
    requiresAdmin?: boolean;
    title?: string;
    section?: 'home' | 'catalog' | 'learn' | 'grammar' | 'review' | 'chat' | 'settings' | 'lessons' | 'interview' | 'onboarding' | 'admin';
    focusLayout?: boolean;
    /** Query fields that define a fresh page instance; other query changes preserve page state. */
    pageKeyQuery?: string[];
}
