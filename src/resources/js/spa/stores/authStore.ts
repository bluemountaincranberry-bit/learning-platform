import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import type { User } from '../types';
import { authApi } from '../domains/user/api/authApi';
import { useProfileStore } from './profileStore';

const STORAGE_TOKEN = 'auth_token';
const STORAGE_USER = 'auth_user';
const STORAGE_ROLES = 'auth_roles';

// Task 6.5: kept in sync with the backend's access-tutor-agent gate
// (AppServiceProvider) — a role in this list means "staff", not "student",
// and staff never gets a working tutor chat. Not the only place this rule
// could live, but the SPA has no other notion of role-gated UI yet, so a
// small local constant is simpler than a shared config for one rule.
const STAFF_ROLES = ['admin', 'editor', 'moderator'];

function getStoredUser(): User | null {
    try {
        const raw = localStorage.getItem(STORAGE_USER);
        return raw ? (JSON.parse(raw) as User) : null;
    } catch {
        return null;
    }
}

function getStoredRoles(): string[] {
    try {
        const raw = localStorage.getItem(STORAGE_ROLES);
        const parsed = raw ? JSON.parse(raw) : [];
        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
}

export const useAuthStore = defineStore('auth', () => {
    const token = ref<string | null>(localStorage.getItem(STORAGE_TOKEN) || null);
    const user = ref<User | null>(getStoredUser());
    const roles = ref<string[]>(getStoredRoles());
    const initialized = ref(false);
    let authCheckPromise: Promise<boolean> | null = null;

    const isAuthenticated = computed(() => !!token.value && !!user.value);

    // Task 6.5: nav-item visibility (SpaShell.vue) and ChatPage.vue's
    // friendly "not available for your role" screen both read this instead
    // of duplicating the STAFF_ROLES check.
    const canAccessTutorAgent = computed(() => !roles.value.some((role) => STAFF_ROLES.includes(role)));

    function setAuth(authToken: string, authUser: User): void {
        token.value = authToken;
        user.value = authUser;
        localStorage.setItem(STORAGE_TOKEN, authToken);
        localStorage.setItem(STORAGE_USER, JSON.stringify(authUser));
    }

    function setRoles(authRoles: string[]): void {
        roles.value = authRoles;
        localStorage.setItem(STORAGE_ROLES, JSON.stringify(authRoles));
    }

    function clearAuth(): void {
        token.value = null;
        user.value = null;
        roles.value = [];
        localStorage.removeItem(STORAGE_TOKEN);
        localStorage.removeItem(STORAGE_USER);
        localStorage.removeItem(STORAGE_ROLES);
        useProfileStore().clearProfile();
    }

    async function logout(): Promise<void> {
        try {
            await authApi.logout();
        } catch {
            // Ignore errors on logout
        } finally {
            clearAuth();
        }
    }

    async function checkAuth(): Promise<boolean> {
        if (authCheckPromise) return authCheckPromise;

        authCheckPromise = (async () => {
            const storedToken = localStorage.getItem(STORAGE_TOKEN);
            if (!storedToken) {
                clearAuth();
                initialized.value = true;
                return false;
            }
            try {
                const data = await authApi.me();
                if (data?.user) {
                    setAuth(storedToken, data.user);
                    setRoles(data.roles ?? []);
                    await useProfileStore().fetchProfile();
                    return true;
                }
            } catch {
                clearAuth();
            } finally {
                initialized.value = true;
            }
            return false;
        })();

        return authCheckPromise;
    }

    return {
        token,
        user,
        roles,
        isAuthenticated,
        initialized,
        canAccessTutorAgent,
        setAuth,
        setRoles,
        clearAuth,
        logout,
        checkAuth,
    };
});
