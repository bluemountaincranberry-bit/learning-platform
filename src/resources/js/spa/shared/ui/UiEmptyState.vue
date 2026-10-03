<script setup lang="ts">
defineProps<{
    title: string;
    description?: string;
}>();
</script>

<template>
    <div class="rounded-xl border border-dashed border-border bg-muted/25 p-6 text-sm text-muted-foreground">
        <div class="font-medium text-foreground">{{ title }}</div>
        <p v-if="description" class="mt-1 leading-6">{{ description }}</p>
        <div class="mt-3">
            <slot />
        </div>
    </div>
</template>
