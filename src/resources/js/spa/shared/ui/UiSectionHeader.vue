<script setup lang="ts">
defineProps<{
    title: string;
    subtitle?: string;
}>();
</script>

<template>
    <header class="flex flex-wrap items-end justify-between gap-3">
        <div class="space-y-1">
            <h2 class="text-lg font-semibold text-foreground">{{ title }}</h2>
            <p v-if="subtitle" class="text-sm text-muted-foreground">{{ subtitle }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </header>
</template>
