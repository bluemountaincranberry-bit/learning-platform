<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { authApi, useAuthStore, useProfileStore } from '../domains/user';
import { parseApiError } from '../types';
import UiInput from '../shared/ui/UiInput.vue';

const router = useRouter();
const authStore = useAuthStore();

const email = ref('');
const password = ref('');
const loading = ref(false);
const error = ref('');

async function handleLogin() {
    error.value = '';
    loading.value = true;
    try {
        const data = await authApi.login(email.value, password.value);
        if (data?.token && data?.user) {
            authStore.setAuth(data.token, data.user);
            // POST /api/auth/login doesn't return roles (task 6.5) — fetch
            // them the same way app boot already does, so nav-item
            // visibility/ChatPage's role check are correct immediately
            // after login, not only after the next full page reload.
            const me = await authApi.me();
            authStore.setRoles(me.roles ?? []);
            await useProfileStore().fetchProfile();
        }
        router.push({ name: 'catalog' });
    } catch (e: unknown) {
        error.value = parseApiError(e, 'Login failed. Please check your credentials.');
    } finally {
        loading.value = false;
    }
}

function handleSubmit() {
    if (!email.value || !password.value) {
        error.value = 'Please fill in all fields.';
        return;
    }
    handleLogin();
}
</script>

<template>
    <section class="max-w-md mx-auto mt-12">
        <h2 class="text-2xl font-semibold mb-6">Login</h2>

        <form @submit.prevent="handleSubmit" class="space-y-4">
            <div>
                <label for="email" class="block text-sm font-medium mb-1">Email</label>
                <UiInput
                    id="email"
                    v-model="email"
                    type="email"
                    placeholder="your@email.com"
                    required
                />
            </div>

            <div>
                <label for="password" class="block text-sm font-medium mb-1">Password</label>
                <UiInput
                    id="password"
                    v-model="password"
                    type="password"
                    placeholder="••••••••"
                    required
                />
            </div>

            <div v-if="error" class="text-danger text-sm" role="alert">
                {{ error }}
            </div>

            <button
                type="submit"
                :disabled="loading"
                class="w-full bg-primary text-white py-2 px-4 rounded hover:bg-primary-hover disabled:opacity-50 disabled:cursor-not-allowed"
                :aria-busy="loading"
            >
                {{ loading ? 'Logging in...' : 'Login' }}
            </button>

            <p class="text-sm text-muted-foreground">
                <RouterLink :to="{ name: 'forgot-password' }" class="text-primary hover:underline">Forgot password?</RouterLink>
            </p>
        </form>

        <p class="mt-4 text-sm text-muted-foreground">
            Don't have an account?
            <RouterLink :to="{ name: 'register' }" class="text-primary hover:underline">Create one</RouterLink>
        </p>
    </section>
</template>
