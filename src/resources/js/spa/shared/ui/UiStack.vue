<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(defineProps<{
    direction?: 'vertical' | 'horizontal';
    gap?: 'sm' | 'md' | 'lg';
}>(), {
    direction: 'vertical',
    gap: 'md',
});

const classes = computed(() => cn(
    'flex',
    props.direction === 'vertical' ? 'flex-col' : 'flex-row flex-wrap items-center',
    { sm: 'gap-2', md: 'gap-4', lg: 'gap-6' }[props.gap],
));
</script>

<template>
    <div :class="classes">
        <slot />
    </div>
</template>
