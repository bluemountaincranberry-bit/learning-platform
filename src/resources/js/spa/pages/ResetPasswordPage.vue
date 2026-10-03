<script setup lang="ts">
import { ref } from 'vue';
import { useRoute } from 'vue-router';
import { authApi } from '../domains/user';
import { parseApiError } from '../types';
import UiInput from '../shared/ui/UiInput.vue';

const route = useRoute();
const token = String(route.query.token ?? '');
const email = ref(String(route.query.email ?? ''));

const password = ref('');
const passwordConfirmation = ref('');
const loading = ref(false);
const error = ref('');
const done = ref(false);

async function handleSubmit() {
    if (!token || !email.value || !password.value || !passwordConfirmation.value) {
        error.value = 'Please fill in all fields.';
        return;
    }
    if (password.value !== passwordConfirmation.value) {
        error.value = 'Passwords do not match.';
        return;
    }
    error.value = '';
    loading.value = true;
    try {
        await authApi.resetPassword(token, email.value, password.value, passwordConfirmation.value);
        done.value = true;
    } catch (e: unknown) {
        error.value = parseApiError(e, 'Could not reset password. The link may have expired.');
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <section class="max-w-md mx-auto mt-12">
        <h2 class="text-2xl font-semibold mb-6">Set a new password</h2>

        <div v-if="done" class="space-y-4">
            <p class="text-sm text-muted-foreground">Your password has been reset. You've been logged out everywhere — log in with your new password.</p>
            <RouterLink :to="{ name: 'login' }" class="text-primary hover:underline text-sm">Go to login</RouterLink>
        </div>

        <div v-else-if="!token" class="space-y-4">
            <p class="text-sm text-danger">
                This reset link is missing or invalid. Request a new one from the
                <RouterLink :to="{ name: 'forgot-password' }" class="text-primary hover:underline">forgot password</RouterLink> page.
            </p>
        </div>

        <form v-else @submit.prevent="handleSubmit" class="space-y-4">
            <div>
                <label for="email" class="block text-sm font-medium mb-1">Email</label>
                <UiInput id="email" v-model="email" type="email" placeholder="your@email.com" required />
            </div>

            <div>
                <label for="password" class="block text-sm font-medium mb-1">New password</label>
                <UiInput id="password" v-model="password" type="password" placeholder="At least 8 characters" required />
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium mb-1">Confirm new password</label>
                <UiInput
                    id="password_confirmation"
                    v-model="passwordConfirmation"
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
                {{ loading ? 'Resetting...' : 'Reset password' }}
            </button>
        </form>
    </section>
</template>
