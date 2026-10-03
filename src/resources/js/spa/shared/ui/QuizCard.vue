<script setup lang="ts">
import { ref, computed } from 'vue';
import UiBadge from './UiBadge.vue';
import UiButton from './UiButton.vue';
import UiCard from './UiCard.vue';
import UiInput from './UiInput.vue';
import type { QuizQuestion } from '../types/QuizQuestion';

/**
 * Task 6.3/6.4: renders `GenerateQuizTool`'s draft questions (task 3.7) as
 * something the student can actually answer, instead of raw JSON in the
 * chat bubble. Task 6.4 is explicitly MVP-only: grading is instant and
 * entirely client-side (string comparison against `answer`) — nothing here
 * calls the API, writes to `user_lexeme_progress`, or touches SRS. That
 * matches the tool's own `draft_only` sideEffect: the draft stays a draft,
 * this is just a nicer way to look at it and self-check, not a real
 * exercise submission. SRS/progress integration is explicitly a separate
 * future task, not this one.
 */
const props = defineProps<{
    questions: QuizQuestion[];
}>();

const responses = ref<string[]>(props.questions.map(() => ''));
const submitted = ref(false);

function isCorrect(index: number): boolean {
    const question = props.questions[index];
    return responses.value[index].trim().toLowerCase() === question.answer.trim().toLowerCase();
}

const score = computed(() => props.questions.reduce((total, _q, i) => total + (isCorrect(i) ? 1 : 0), 0));

function selectChoice(index: number, choice: string): void {
    if (submitted.value) return;
    responses.value[index] = choice;
}

function checkAnswers(): void {
    submitted.value = true;
}

function retry(): void {
    responses.value = props.questions.map(() => '');
    submitted.value = false;
}
</script>

<template>
    <UiCard class="space-y-4 border-primary/40">
        <div class="flex items-center justify-between gap-2">
            <div class="text-xs uppercase tracking-[0.16em] text-primary">Draft quiz</div>
            <UiBadge v-if="submitted" :tone="score === questions.length ? 'success' : 'warning'">
                {{ score }}/{{ questions.length }}
            </UiBadge>
        </div>

        <div v-for="(question, i) in questions" :key="i" class="space-y-2 rounded-spa border border-border bg-black/10 p-3">
            <div class="text-sm text-fg">{{ i + 1 }}. {{ question.prompt }}</div>

            <div v-if="question.type === 'multiple_choice'" class="flex flex-wrap gap-2">
                <UiButton
                    v-for="choice in question.choices"
                    :key="choice"
                    size="sm"
                    :variant="responses[i] === choice ? 'primary' : 'secondary'"
                    :disabled="submitted"
                    @click="selectChoice(i, choice)"
                >
                    {{ choice }}
                </UiButton>
            </div>
            <UiInput v-else v-model="responses[i]" :disabled="submitted" placeholder="Type your answer..." />

            <div v-if="submitted" class="text-sm" :class="isCorrect(i) ? 'text-emerald-500' : 'text-rose-500'">
                <template v-if="isCorrect(i)">Correct!</template>
                <template v-else>Not quite — the answer was "{{ question.answer }}".</template>
            </div>
        </div>

        <div class="flex gap-2">
            <UiButton v-if="!submitted" variant="primary" @click="checkAnswers">Check answers</UiButton>
            <UiButton v-else variant="secondary" @click="retry">Try again</UiButton>
        </div>

        <p class="text-xs text-muted-foreground">
            This is a draft — nothing here is recorded in your progress. Answer through the normal exercise flow for
            anything that should count.
        </p>
    </UiCard>
</template>
