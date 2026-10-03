<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, useId, watch } from 'vue';

const props = withDefaults(defineProps<{
    open: boolean;
    title: string;
    busy?: boolean;
}>(), { busy: false });

const emit = defineEmits<{ close: []; }>();
const dialogElement = ref<HTMLElement | null>(null);
const titleId = useId();
let previouslyFocused: HTMLElement | null = null;

function focusDialog(): void {
    dialogElement.value?.focus();
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape' && !props.busy) emit('close');
    if (event.key !== 'Tab' || !dialogElement.value) return;
    const focusable = Array.from(dialogElement.value.querySelectorAll<HTMLElement>('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])')).filter((element) => !element.hasAttribute('disabled'));
    if (focusable.length === 0) return;
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
}

watch(() => props.open, (open) => {
    if (open) {
        previouslyFocused = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        window.addEventListener('keydown', onKeydown);
        void nextTick(focusDialog);
    } else {
        window.removeEventListener('keydown', onKeydown);
        previouslyFocused?.focus();
        previouslyFocused = null;
    }
}, { immediate: true });

onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="!busy && emit('close')">
            <section ref="dialogElement" role="dialog" aria-modal="true" tabindex="-1" :aria-busy="busy" :aria-labelledby="titleId" class="w-full max-w-lg rounded-xl border border-border bg-card p-5 shadow-xl">
                <div class="flex items-center justify-between gap-4">
                    <h2 :id="titleId" class="text-base font-semibold text-foreground">{{ title }}</h2>
                    <button type="button" aria-label="Close dialog" class="rounded p-1 text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" :disabled="busy" @click="emit('close')">×</button>
                </div>
                <div class="mt-4"><slot /></div>
                <div v-if="$slots.footer" class="mt-5 flex justify-end gap-2"><slot name="footer" /></div>
            </section>
        </div>
    </Teleport>
</template>
