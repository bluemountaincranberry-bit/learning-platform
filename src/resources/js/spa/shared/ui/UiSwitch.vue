<script setup lang="ts">
defineProps<{
    modelValue: boolean;
    label?: string;
    description?: string;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: boolean): void;
}>();
</script>

<template>
    <label class="flex cursor-pointer items-start justify-between gap-4 py-1">
        <span v-if="label || description" class="space-y-0.5">
            <span v-if="label" class="block text-sm font-medium text-fg">{{ label }}</span>
            <span v-if="description" class="block text-xs text-muted-foreground">{{ description }}</span>
        </span>
        <span class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors" :class="modelValue ? 'bg-primary' : 'bg-muted'">
            <span
                class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                :class="modelValue ? 'translate-x-6' : 'translate-x-1'"
            />
        </span>
        <input type="checkbox" class="sr-only" :checked="modelValue" @change="emit('update:modelValue', ($event.target as HTMLInputElement).checked)" />
    </label>
</template>
