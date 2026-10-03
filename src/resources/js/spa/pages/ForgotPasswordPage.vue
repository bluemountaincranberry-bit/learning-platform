<script setup lang="ts">
import { ref } from 'vue';
import { authApi } from '../domains/user';
import { parseApiError } from '../types';
import UiInput from '../shared/ui/UiInput.vue';

const email = ref('');
const loading = ref(false);
const error = ref('');
const sent = ref(false);

async function handleSubmit() {
    if (!email.value) {
        error.value = 'Please enter your email.';
        return;
    }
    error.value = '';
    loading.value = true;
    try {
        await authApi.forgotPassword(email.value);
        sent.value = true;
    } catch (e: unknown) {
        error.value = parseApiError(e, 'Something went wrong. Please try again.');
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <section class="max-w-md mx-auto mt-12">
        <h2 class="text-2xl font-semibold mb-6">Reset your password</h2>

        <div v-if="sent" class="space-y-4">
            <p class="text-sm text-muted-foreground">
                If an account exists for <strong>{{ email }}</strong>, we've sent a link to reset your password.
            </p>
            <RouterLink :to="{ name: 'login' }" class="text-primary hover:underline text-sm">Back to login</RouterLink>
        </div>

        <form v-else @submit.prevent="handleSubmit" class="space-y-4">
            <p class="text-sm text-muted-foreground">Enter your email and we'll send you a link to reset your password.</p>

            <div>
                <label for="email" class="block text-sm font-medium mb-1">Email</label>
                <UiInput id="email" v-model="email" type="email" placeholder="your@email.com" required />
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
                {{ loading ? 'Sending...' : 'Send reset link' }}
            </button>

            <p class="text-sm text-muted-foreground">
                <RouterLink :to="{ name: 'login' }" class="text-primary hover:underline">Back to login</RouterLink>
            </p>
        </form>
    </section>
</template>
