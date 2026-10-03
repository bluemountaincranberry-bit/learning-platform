<script setup lang="ts">
import { nextTick, onMounted, ref, watch } from 'vue';
import type { GrammarPracticeExercise } from '../../../types';
import ExercisePrompt from './ExercisePrompt.vue';

/**
 * Fill the gap, transform and fix the mistake (Hard): type the answer.
 * Enter submits. `state` colours the field after a check.
 */
const props = defineProps<{
    exercise: GrammarPracticeExercise;
    disabled: boolean;
    state: 'idle' | 'wrong' | 'right';
}>();
const model = defineModel<string>({ required: true });
const emit = defineEmits<{ submit: [] }>();
const input = ref<HTMLInputElement | null>(null);

const placeholder: Record<string, string> = {
    cloze: 'Type the missing words',
    transform: 'Type the new sentence',
    fix: 'Type the correct sentence',
};

function focus(): void {
    void nextTick(() => input.value?.focus({ preventScroll: true }));
}

onMounted(focus);
watch(() => props.exercise.id, focus);
</script>

<template>
    <div class="space-y-5">
        <ExercisePrompt :prompt="exercise.prompt" />
        <input
            ref="input"
            v-model="model"
            type="text"
            autocapitalize="off"
            autocomplete="off"
            autocorrect="off"
            spellcheck="false"
            enterkeyhint="done"
            class="h-12 w-full min-w-0 rounded-xl border-2 bg-background px-4 text-lg text-fg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-100"
            :class="{ 'border-border': state === 'idle', 'border-danger': state === 'wrong', 'border-success': state === 'right' }"
            :placeholder="placeholder[exercise.type] ?? 'Type your answer'"
            :disabled="disabled"
            :aria-label="placeholder[exercise.type] ?? 'Your answer'"
            data-test="answer-input"
            @keydown.enter.prevent="emit('submit')"
        />
    </div>
</template>
