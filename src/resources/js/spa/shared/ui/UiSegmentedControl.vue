<script setup lang="ts">
export interface UiSegment {
    value: string;
    label: string;
    count?: number;
}

defineProps<{
    segments: UiSegment[];
    modelValue: string;
    ariaLabel: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();
</script>

<template>
    <div role="group" :aria-label="ariaLabel" class="flex min-w-0 rounded-lg bg-muted p-0.5">
        <button
            v-for="segment in segments"
            :key="segment.value"
            type="button"
            :aria-pressed="modelValue === segment.value"
            class="flex min-h-11 min-w-0 flex-1 flex-col items-center justify-center rounded-md px-1 text-xs font-medium leading-tight transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            :class="modelValue === segment.value ? 'bg-background text-fg shadow-sm' : 'text-muted-foreground hover:text-fg'"
            @click="emit('update:modelValue', segment.value)"
        >
            <span class="w-full truncate">{{ segment.label }}</span>
            <span v-if="segment.count !== undefined" class="tabular-nums" :class="modelValue === segment.value ? 'text-primary' : ''">{{ segment.count }}</span>
        </button>
    </div>
</template>
