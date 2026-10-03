<script setup lang="ts">
import { reactive } from 'vue';
import { Check, X, Eye } from 'lucide-vue-next';
import UiBadge from '../../shared/ui/UiBadge.vue';
import UiButton from '../../shared/ui/UiButton.vue';
import UiInput from '../../shared/ui/UiInput.vue';
import type { GrammarRuleExercise } from '../../types';

defineProps<{
    exercises: GrammarRuleExercise[];
}>();

interface ExerciseState {
    input: string;
    revealed: boolean;
    checked: boolean;
    correct: boolean;
    selectedIndex: number | null;
}

const state = reactive<Record<number, ExerciseState>>({});

function stateFor(exercise: GrammarRuleExercise): ExerciseState {
    if (!state[exercise.id]) {
        state[exercise.id] = { input: '', revealed: false, checked: false, correct: false, selectedIndex: null };
    }
    return state[exercise.id];
}

function normalize(value: string): string {
    return value.trim().toLowerCase().replace(/[.,!?;:]+$/g, '');
}

function checkCloze(exercise: GrammarRuleExercise): void {
    const s = stateFor(exercise);
    s.checked = true;
    s.correct = normalize(s.input) === normalize(exercise.answer ?? '');
}

function reveal(exercise: GrammarRuleExercise): void {
    stateFor(exercise).revealed = true;
}

function choose(exercise: GrammarRuleExercise, index: number): void {
    const s = stateFor(exercise);
    if (s.selectedIndex !== null) return;
    s.selectedIndex = index;
    s.correct = index === exercise.answer_index;
    s.checked = true;
}
</script>

<template>
    <div class="space-y-4">
        <div
            v-for="exercise in exercises"
            :key="exercise.id"
            class="rounded-spa border border-border bg-black/10 p-3"
        >
            <p class="text-sm text-fg">{{ exercise.prompt }}</p>

            <template v-if="exercise.type === 'cloze'">
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <UiInput
                        v-model="stateFor(exercise).input"
                        placeholder="Type your answer"
                        class="max-w-xs"
                        :disabled="stateFor(exercise).revealed"
                        @keyup.enter="checkCloze(exercise)"
                    />
                    <UiButton size="sm" variant="secondary" :disabled="stateFor(exercise).revealed" @click="checkCloze(exercise)">
                        Check
                    </UiButton>
                    <UiButton size="sm" variant="ghost" @click="reveal(exercise)">
                        <Eye :size="14" /> Reveal answer
                    </UiButton>
                    <UiBadge v-if="stateFor(exercise).checked && !stateFor(exercise).revealed" :tone="stateFor(exercise).correct ? 'success' : 'danger'">
                        <Check v-if="stateFor(exercise).correct" :size="12" />
                        <X v-else :size="12" />
                        {{ stateFor(exercise).correct ? 'Correct' : 'Not quite' }}
                    </UiBadge>
                </div>
                <p v-if="stateFor(exercise).revealed || (stateFor(exercise).checked && !stateFor(exercise).correct)" class="mt-2 text-sm text-muted-foreground">
                    Answer: <span class="font-medium text-fg">{{ exercise.answer }}</span>
                </p>
            </template>

            <template v-else-if="exercise.type === 'multiple_choice'">
                <div class="mt-3 flex flex-col gap-2">
                    <button
                        v-for="(option, index) in exercise.options ?? []"
                        :key="index"
                        type="button"
                        class="rounded-spa border px-3 py-2 text-left text-sm transition-colors"
                        :class="[
                            stateFor(exercise).selectedIndex === null
                                ? 'border-border bg-black/10 hover:border-primary'
                                : index === exercise.answer_index
                                    ? 'border-emerald-600 bg-emerald-500/10 text-emerald-700'
                                    : index === stateFor(exercise).selectedIndex
                                        ? 'border-rose-600 bg-rose-500/10 text-rose-700'
                                        : 'border-border bg-black/10 opacity-60',
                        ]"
                        :disabled="stateFor(exercise).selectedIndex !== null"
                        @click="choose(exercise, index)"
                    >
                        {{ option }}
                    </button>
                </div>
            </template>

            <p v-if="exercise.explanation && stateFor(exercise).checked" class="mt-2 text-sm text-muted-foreground">
                {{ exercise.explanation }}
            </p>
        </div>
    </div>
</template>
