import { storeToRefs } from 'pinia';
import { useAuthStore } from '../stores/authStore';

/**
 * Facade over auth store for components. Prefer using useAuthStore() directly when you need actions.
 */
export function useAuth() {
    const store = useAuthStore();
    const { token, user, roles, isAuthenticated, initialized, canAccessTutorAgent } = storeToRefs(store);
    return {
        token,
        user,
        roles,
        isAuthenticated,
        initialized,
        canAccessTutorAgent,
        setAuth: store.setAuth,
        clearAuth: store.clearAuth,
        logout: store.logout,
        checkAuth: store.checkAuth,
    };
}
