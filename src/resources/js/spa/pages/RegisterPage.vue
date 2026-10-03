<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore, useProfileStore } from '../domains/user';
import { authApi } from '../domains/user';
import { parseApiError } from '../types';
import UiInput from '../shared/ui/UiInput.vue';

const router = useRouter();
const authStore = useAuthStore();

const name = ref('');
const email = ref('');
const password = ref('');
const passwordConfirmation = ref('');
const loading = ref(false);
const error = ref('');

async function handleRegister() {
    error.value = '';
    loading.value = true;
    try {
        const data = await authApi.register(name.value, email.value, password.value, passwordConfirmation.value);
        if (data?.token && data?.user) {
            authStore.setAuth(data.token, data.user);
            const me = await authApi.me();
            authStore.setRoles(me.roles ?? []);
            await useProfileStore().fetchProfile();
        }
        router.push({ name: 'onboarding' });
    } catch (e: unknown) {
        error.value = parseApiError(e, 'Registration failed. Please try again.');
    } finally {
        loading.value = false;
    }
}

function handleSubmit() {
    if (!name.value || !email.value || !password.value || !passwordConfirmation.value) {
        error.value = 'Please fill in all fields.';
        return;
    }
    if (password.value !== passwordConfirmation.value) {
        error.value = 'Passwords do not match.';
        return;
    }
    handleRegister();
}
</script>

<template>
    <section class="max-w-md mx-auto mt-12">
        <h2 class="text-2xl font-semibold mb-6">Create your account</h2>

        <form @submit.prevent="handleSubmit" class="space-y-4">
            <div>
                <label for="name" class="block text-sm font-medium mb-1">Name</label>
                <UiInput id="name" v-model="name" type="text" placeholder="Your name" required />
            </div>

            <div>
                <label for="email" class="block text-sm font-medium mb-1">Email</label>
                <UiInput id="email" v-model="email" type="email" placeholder="your@email.com" required />
            </div>

            <div>
                <label for="password" class="block text-sm font-medium mb-1">Password</label>
                <UiInput id="password" v-model="password" type="password" placeholder="At least 8 characters" required />
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium mb-1">Confirm password</label>
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
                {{ loading ? 'Creating account...' : 'Create account' }}
            </button>
        </form>

        <p class="mt-4 text-sm text-muted-foreground">
            Already have an account?
            <RouterLink :to="{ name: 'login' }" class="text-primary hover:underline">Login</RouterLink>
        </p>
    </section>
</template>
