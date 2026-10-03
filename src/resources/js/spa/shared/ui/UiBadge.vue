<script setup lang="ts">
import { cva, type VariantProps } from 'class-variance-authority';
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const badgeVariants = cva(
    'inline-flex items-center rounded-sm border px-2.5 py-0.5 text-[11px] font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:ring-offset-background',
    {
        variants: {
            tone: {
                neutral: 'border-transparent bg-muted text-muted-foreground',
                success: 'border-transparent bg-success-bg text-success-fg',
                warning: 'border-transparent bg-amber-500/10 text-amber-700',
                danger: 'border-transparent bg-rose-500/10 text-rose-700',
                primary: 'border-transparent bg-primary/10 text-primary',
            },
        },
        defaultVariants: {
            tone: 'neutral',
        },
    },
);

type BadgeTone = NonNullable<VariantProps<typeof badgeVariants>['tone']>;

const props = withDefaults(defineProps<{
    tone?: BadgeTone;
}>(), {
    tone: 'neutral',
});

const classes = computed(() => cn(badgeVariants({ tone: props.tone })));
</script>

<template>
    <span :class="classes">
        <slot />
    </span>
</template>
