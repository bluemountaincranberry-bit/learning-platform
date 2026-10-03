<script setup lang="ts">
withDefaults(defineProps<{
    title: string;
    description?: string;
    eyebrow?: string;
}>(), {
    description: undefined,
    eyebrow: undefined,
});
</script>

<template>
    <header class="flex flex-col gap-4 border-b border-border pb-5 sm:flex-row sm:items-end sm:justify-between">
        <div class="space-y-1">
            <p v-if="eyebrow" class="text-xs font-semibold uppercase tracking-wide text-primary">{{ eyebrow }}</p>
            <h1 class="text-2xl font-semibold tracking-tight text-foreground">{{ title }}</h1>
            <p v-if="description" class="max-w-2xl text-sm leading-6 text-muted-foreground">{{ description }}</p>
        </div>
        <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </header>
</template>
