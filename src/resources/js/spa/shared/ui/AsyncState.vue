<script setup lang="ts">
import UiButton from './UiButton.vue';
import UiEmptyState from './UiEmptyState.vue';
import UiSpinner from './UiSpinner.vue';

withDefaults(defineProps<{
    loading?: boolean;
    error?: string | null;
    empty?: boolean;
    emptyTitle?: string;
    emptyDescription?: string;
}>(), {
    loading: false,
    error: null,
    empty: false,
    emptyTitle: 'Nothing here yet',
    emptyDescription: undefined,
});

const emit = defineEmits<{
    retry: [];
}>();
</script>

<template>
    <div v-if="loading" class="flex min-h-32 items-center justify-center rounded-xl border border-border bg-card p-6">
        <slot name="loading">
            <UiSpinner />
        </slot>
    </div>

    <div v-else-if="error" class="rounded-xl border border-destructive/30 bg-destructive/5 p-6 text-sm">
        <slot name="error" :error="error">
            <p class="font-medium text-destructive">{{ error }}</p>
            <UiButton class="mt-3" size="sm" @click="emit('retry')">Try again</UiButton>
        </slot>
    </div>

    <UiEmptyState v-else-if="empty" :title="emptyTitle" :description="emptyDescription">
        <slot name="empty" />
    </UiEmptyState>

    <slot v-else />
</template>
