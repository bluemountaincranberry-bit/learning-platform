<script setup lang="ts">
import type { GrammarPracticeExercise } from '../../../types';
import ExercisePrompt from './ExercisePrompt.vue';

/**
 * Choose the form (Easy). Tapping an option answers at once; after a wrong
 * first try that option is struck out and tapping another one is the retry.
 */
const props = defineProps<{
    exercise: GrammarPracticeExercise;
    struck: number[];
    /** The correct option text once the exercise is settled. */
    answer: string | null;
    disabled: boolean;
}>();

const emit = defineEmits<{ answer: [given: string] }>();

function optionClass(option: string, index: number): string {
    if (props.answer !== null && option === props.answer) return 'border-success bg-success-bg text-success-fg';
    if (props.struck.includes(index)) return 'border-border bg-surface-alt text-muted-foreground line-through opacity-60';
    return 'border-border bg-surface-alt text-fg hover:border-primary';
}
</script>

<template>
    <div class="space-y-5">
        <ExercisePrompt :prompt="exercise.prompt" :filled="answer" />
        <div class="grid gap-2">
            <button
                v-for="(option, index) in exercise.options ?? []"
                :key="index"
                type="button"
                class="min-h-12 w-full break-words rounded-xl border px-4 py-3 text-left text-base transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-default"
                :class="optionClass(option, index)"
                :disabled="disabled || struck.includes(index)"
                data-test="option"
                @click="emit('answer', String(index))"
            >
                {{ option }}
            </button>
        </div>
    </div>
</template>
