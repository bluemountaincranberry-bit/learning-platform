<script setup lang="ts">
defineProps<{
    label: string;
    value: string | number;
    detail?: string;
}>();
</script>

<template>
    <div class="rounded-xl border border-border bg-card px-4 py-3 text-card-foreground shadow-sm">
        <div class="text-[11px] uppercase tracking-[0.18em] text-muted-foreground">{{ label }}</div>
        <div class="mt-1 text-2xl font-semibold text-foreground">{{ value }}</div>
        <div v-if="detail" class="mt-1 text-sm text-muted-foreground">{{ detail }}</div>
    </div>
</template>
