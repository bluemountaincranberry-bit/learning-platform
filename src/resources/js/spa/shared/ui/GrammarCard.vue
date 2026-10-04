<script setup lang="ts">
import { RouterLink } from 'vue-router';
import UiBadge from './UiBadge.vue';

defineProps<{ title: string; ruleId?: number | null; level?: string | null; summary?: string | null; status?: string | null }>();
</script>

<template>
    <article class="min-w-0 space-y-2 rounded-spa border border-border bg-surface p-3">
        <div class="flex min-w-0 items-start gap-2">
            <RouterLink v-if="ruleId" :to="{ name: 'grammar.details', params: { id: ruleId } }" class="flex min-h-11 min-w-0 flex-1 items-center break-words font-semibold text-fg hover:text-primary hover:underline">{{ title }}</RouterLink>
            <span v-else class="min-w-0 flex-1 break-words font-semibold text-fg">{{ title }}</span>
            <UiBadge v-if="level" tone="primary" class="shrink-0">{{ level }}</UiBadge>
        </div>
        <p v-if="summary" class="truncate text-sm text-muted-foreground" :title="summary">{{ summary }}</p>
        <div class="flex flex-wrap items-center gap-2">
            <UiBadge v-if="status" :tone="status === 'Learned' ? 'success' : 'neutral'" class="max-w-full break-words">{{ status }}</UiBadge>
            <slot />
        </div>
        <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2"><slot name="actions" /></div>
    </article>
</template>
