<script setup lang="ts">
import { ref, watch } from 'vue';

/**
 * Clozemaster-style production input: tap the whole target word from a small
 * set of options instead of typing it. Sibling to TypedAnswerInput — used for
 * Cloze when the 'choose-word' answer style is active and enough word-form
 * distractors were available to build `options`.
 */
const props = withDefaults(
    defineProps<{
        target: string;
        options: string[];
        busy?: boolean;
    }>(),
    { busy: false },
);

const emit = defineEmits<{
    (e: 'result', payload: { correct: boolean; value: string }): void;
}>();

const chosen = ref<string | null>(null);

const normalizedTarget = () => props.target.trim().toLocaleLowerCase();

watch(
    () => props.target,
    () => {
        chosen.value = null;
    },
);

function optionClass(option: string): string {
    if (!chosen.value) return 'border-border bg-black/10 hover:bg-black/20';
    const isCorrect = option.trim().toLocaleLowerCase() === normalizedTarget();
    const isChosen = option === chosen.value;
    if (isCorrect) return 'border-emerald-500 bg-emerald-500/10';
    if (isChosen) return 'border-warning bg-warning/10';
    return 'border-border opacity-50';
}

function choose(option: string) {
    if (chosen.value || props.busy) return;
    chosen.value = option;
    emit('result', { correct: option.trim().toLocaleLowerCase() === normalizedTarget(), value: option });
}
</script>

<template>
    <div class="space-y-3">
        <p class="text-center text-xs leading-5 text-muted-foreground">Choose the word that makes the whole sentence correct in meaning.</p>
        <div class="grid grid-cols-2 gap-2">
            <button
                v-for="opt in options"
                :key="opt"
                type="button"
                class="rounded-spa border p-3 text-sm font-medium text-fg transition-colors"
                :class="optionClass(opt)"
                :disabled="!!chosen || busy"
                @click="choose(opt)"
            >
                <span class="flex items-center gap-2 text-left"><span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-black/10 text-[11px] font-semibold text-fg-secondary">{{ String.fromCharCode(65 + options.indexOf(opt)) }}</span><span class="min-w-0 break-words">{{ opt }}</span></span>
            </button>
        </div>
        <p v-if="chosen" class="text-center text-xs leading-5" :class="chosen.trim().toLocaleLowerCase() === normalizedTarget() ? 'text-success-fg' : 'text-warning-fg'">
            {{ chosen.trim().toLocaleLowerCase() === normalizedTarget() ? 'Correct — this is the only option that fits the sentence.' : `The correct answer is “${props.target}”.` }}
        </p>
    </div>
</template>
