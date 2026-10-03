<script setup lang="ts">
import type { GrammarPracticeCheckResponse } from '../../../types';

/** Hint after the first wrong try; "Correct" or the answer once settled (VIK-32). */
defineProps<{
    hint: string | null;
    settled: GrammarPracticeCheckResponse | null;
}>();

const emit = defineEmits<{ seeRule: [] }>();
</script>

<template>
    <div v-if="settled?.correct" class="rounded-xl bg-success-bg px-4 py-3 text-sm text-success-fg" role="status" data-test="feedback-correct">
        <p class="font-semibold">Correct</p>
        <p v-if="settled.explanation" class="mt-0.5">{{ settled.explanation }}</p>
    </div>
    <div v-else-if="settled" class="rounded-xl bg-danger-bg px-4 py-3 text-sm text-fg" role="status" data-test="feedback-answer">
        <p class="font-semibold">Answer</p>
        <p class="mt-0.5 break-words text-base font-semibold text-success-fg">{{ settled.answer }}</p>
        <p v-if="settled.explanation" class="mt-1 text-muted-foreground">{{ settled.explanation }}</p>
        <button type="button" class="mt-1 inline-flex min-h-11 items-center text-sm font-medium text-primary" @click="emit('seeRule')">See rule ›</button>
    </div>
    <div v-else-if="hint" class="rounded-xl border border-warning-border bg-warning-bg px-4 py-3 text-sm text-warning-fg" role="status" data-test="feedback-hint">
        <p class="font-semibold">Hint</p>
        <p class="mt-0.5">{{ hint }}</p>
    </div>
</template>
