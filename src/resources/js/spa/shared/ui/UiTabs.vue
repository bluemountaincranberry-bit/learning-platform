<script setup lang="ts">
import { cva } from 'class-variance-authority';
import { cn } from '@/lib/utils';

/**
 * Generic tab strip + panel switcher (task 7.7), used by
 * ContentDetailsPage.vue to turn its stacked Words/Grammar/Full text cards
 * into tabs. Deliberately dumb, like the other shared/ui primitives: it
 * owns no state of its own besides which tab is active (v-model), and
 * renders every panel's slot at all times behind v-show rather than
 * mounting/unmounting on switch — that's what keeps a consumer's own state
 * (e.g. StudyPage-style filters/selection inside a panel) alive across tab
 * switches without the consumer having to do anything special.
 */
export interface UiTabItem {
    key: string;
    label: string;
}

const props = defineProps<{
    tabs: UiTabItem[];
    modelValue: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [key: string];
}>();

const tabVariants = cva(
    'inline-flex items-center gap-1.5 border-b-2 px-3 py-2 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
    {
        variants: {
            active: {
                true: 'border-primary text-fg',
                false: 'border-transparent text-muted-foreground hover:text-fg',
            },
        },
        defaultVariants: {
            active: false,
        },
    },
);

function classesFor(key: string): string {
    return cn(tabVariants({ active: key === props.modelValue }));
}

function select(key: string): void {
    if (key !== props.modelValue) emit('update:modelValue', key);
}
</script>

<template>
    <div>
        <div role="tablist" class="flex flex-wrap gap-1 border-b border-border">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                role="tab"
                :aria-selected="tab.key === modelValue"
                :class="classesFor(tab.key)"
                @click="select(tab.key)"
            >
                {{ tab.label }}
            </button>
        </div>
        <div class="mt-4">
            <div v-for="tab in tabs" :key="tab.key" v-show="tab.key === modelValue" role="tabpanel">
                <slot :name="tab.key" />
            </div>
        </div>
    </div>
</template>
