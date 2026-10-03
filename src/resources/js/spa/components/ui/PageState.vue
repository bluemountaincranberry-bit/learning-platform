<script setup lang="ts">
defineProps<{
    loading: boolean;
    error?: string;
}>();
</script>

<template>
    <div v-if="loading" class="rounded-xl border border-border bg-card p-5" aria-live="polite" aria-busy="true">
        <div class="h-4 w-32 animate-pulse rounded bg-muted"></div>
        <div class="mt-3 h-3 w-3/4 animate-pulse rounded bg-muted/70"></div>
    </div>
    <div v-else-if="error" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-500/20 bg-amber-500/10 px-4 py-3 text-sm text-amber-800" role="alert">
        <span>{{ error }}</span>
        <slot name="retry" />
    </div>
    <div v-else><slot /></div>
</template>
