<script setup lang="ts">
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

defineOptions({ inheritAttrs: false });

const props = defineProps<{
    modelValue: string;
    placeholder: string;
    disabled?: boolean;
    ariaLabel: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
    submit: [];
}>();

const textarea = ref<HTMLTextAreaElement | null>(null);

function resizeToContent(): void {
    const element = textarea.value;
    if (!element) return;
    element.style.height = 'auto';
    const maxHeight = Math.floor(window.innerHeight * 0.5);
    const height = Math.min(element.scrollHeight, maxHeight);
    element.style.height = `${height}px`;
    element.style.overflowY = element.scrollHeight > maxHeight ? 'auto' : 'hidden';
}

function updateValue(event: Event): void {
    emit('update:modelValue', (event.target as HTMLTextAreaElement).value);
    resizeToContent();
}

watch(() => props.modelValue, async () => {
    await nextTick();
    resizeToContent();
});

onMounted(() => {
    resizeToContent();
    window.addEventListener('resize', resizeToContent);
});

onUnmounted(() => window.removeEventListener('resize', resizeToContent));
</script>

<template>
    <textarea
        ref="textarea"
        v-bind="$attrs"
        :value="modelValue"
        :placeholder="placeholder"
        :disabled="disabled"
        :aria-label="ariaLabel"
        rows="1"
        class="min-h-11 max-h-[50dvh] w-full resize-none overflow-y-hidden rounded-md border border-input bg-background px-3 py-2.5 text-base leading-7 text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
        @input="updateValue"
        @keydown.enter.exact.prevent="emit('submit')"
    />
</template>
