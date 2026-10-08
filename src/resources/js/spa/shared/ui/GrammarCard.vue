<script setup lang="ts">
import { RouterLink } from 'vue-router';
import UiBadge from './UiBadge.vue';
import MarkdownContent from './MarkdownContent.vue';

defineProps<{ title: string; ruleId?: number | null; level?: string | null; summary?: string | null; body?: string | null; example?: string | null; exampleTranslation?: string | null; status?: string | null; statusTone?: 'neutral' | 'primary' | 'success' }>();
</script>

<template>
    <article class="min-w-0 space-y-2 rounded-spa border border-border bg-surface p-3">
        <div class="flex min-w-0 items-start gap-2">
            <RouterLink v-if="ruleId" :to="{ name: 'grammar.details', params: { id: ruleId } }" class="block min-h-11 min-w-0 flex-1 break-words py-2 font-semibold text-fg hover:text-primary hover:underline">{{ title }}</RouterLink>
            <span v-else class="min-w-0 flex-1 break-words font-semibold text-fg">{{ title }}</span>
            <UiBadge v-if="level" tone="primary" class="shrink-0">{{ level }}</UiBadge>
        </div>
        <p v-if="summary" class="truncate text-sm text-muted-foreground" :title="summary">{{ summary }}</p>
        <div v-if="example" class="space-y-1 rounded-md bg-black/5 px-3 py-2">
            <p class="break-words text-sm font-medium text-fg">{{ example }}</p>
            <p v-if="exampleTranslation" class="break-words text-sm text-fg-secondary">{{ exampleTranslation }}</p>
        </div>
        <details v-if="body" class="group">
            <summary class="flex min-h-11 cursor-pointer items-center text-sm font-medium text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">Rule details</summary>
            <MarkdownContent :content="body" />
        </details>
        <div class="flex flex-wrap items-center gap-2">
            <UiBadge v-if="status" :tone="statusTone ?? 'neutral'" class="max-w-full break-words">{{ status }}</UiBadge>
            <slot />
        </div>
        <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2"><slot name="actions" /></div>
    </article>
</template>
