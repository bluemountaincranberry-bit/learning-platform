<script setup lang="ts">
import { cva, type VariantProps } from 'class-variance-authority';
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const buttonVariants = cva(
    'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:pointer-events-none disabled:opacity-50',
    {
        variants: {
            variant: {
                primary: 'bg-primary text-primary-foreground shadow-sm hover:bg-primary/90',
                secondary: 'border border-border bg-secondary text-secondary-foreground shadow-sm hover:bg-secondary/80',
                ghost: 'hover:bg-accent hover:text-accent-foreground',
                danger: 'bg-destructive text-destructive-foreground shadow-sm hover:bg-destructive/90',
                success: 'bg-success text-white shadow-sm hover:bg-success-fg',
            },
            size: {
                default: 'h-10 px-4 py-2',
                sm: 'h-9 rounded-md px-3',
                lg: 'h-11 rounded-md px-8',
                icon: 'h-10 w-10',
                // 44px minimum tap target for learner (phone) screens.
                touch: 'h-11 px-3',
                'icon-touch': 'h-11 w-11',
            },
        },
        defaultVariants: {
            variant: 'secondary',
            size: 'default',
        },
    },
);

type ButtonVariant = NonNullable<VariantProps<typeof buttonVariants>['variant']>;
type ButtonSize = NonNullable<VariantProps<typeof buttonVariants>['size']>;

const props = withDefaults(defineProps<{
    variant?: ButtonVariant;
    size?: ButtonSize;
    type?: 'button' | 'submit' | 'reset';
    disabled?: boolean;
}>(), {
    variant: 'secondary',
    size: 'default',
    type: 'button',
    disabled: false,
});

const classes = computed(() => cn(buttonVariants({ variant: props.variant, size: props.size })));
</script>

<template>
    <button :type="type" :disabled="disabled" :class="classes">
        <slot />
    </button>
</template>
