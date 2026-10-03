<script setup lang="ts">
withDefaults(defineProps<{
    size?: 'sm' | 'md' | 'lg';
}>(), {
    size: 'md',
});
</script>

<template>
    <span
        aria-label="Loading"
        role="status"
        class="inline-block animate-spin rounded-full border-2 border-muted border-t-primary"
        :class="{ 'h-4 w-4': size === 'sm', 'h-6 w-6': size === 'md', 'h-8 w-8': size === 'lg' }"
    />
</template>
