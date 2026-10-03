<script setup lang="ts">
/**
 * Every focusLayout:true AI Builder page (catalog, graph canvas, prompt
 * editor) mounts this instead of the consumer SpaShell — it has no left
 * sidebar of its own, so without this strip these pages would carry no
 * visible identity or way back to Filament at all. Deliberately slim
 * (single row, muted) — this is chrome for an internal tool, not a brand
 * surface like the consumer sidebar it replaces.
 */
defineProps<{
    crumb?: string | null;
}>();
</script>

<template>
    <div class="flex h-9 shrink-0 items-center gap-2 border-b border-border bg-muted/40 px-4 text-xs">
        <a href="/admin/graph-definitions" class="flex items-center gap-1 font-medium text-muted-foreground transition-colors hover:text-fg">
            <span aria-hidden="true">←</span> Админка
        </a>
        <span class="text-muted-foreground/40">/</span>
        <RouterLink :to="{ name: 'admin.aiBuilder.catalog' }" class="font-semibold text-fg transition-colors hover:text-primary">
            AI Builder
        </RouterLink>
        <template v-if="crumb">
            <span class="text-muted-foreground/40">/</span>
            <span class="truncate font-mono text-muted-foreground">{{ crumb }}</span>
        </template>
    </div>
</template>
