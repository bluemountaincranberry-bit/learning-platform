<script setup lang="ts">
import { computed } from 'vue';
import UiButton from '../../shared/ui/UiButton.vue';
import type { GrammarPracticeResult } from '../../types';
import { EXERCISE_TASK } from './practice/exerciseLabels';

/** Result screen: score, what changed for the rule, mistakes, next actions. */
const props = defineProps<{ result: GrammarPracticeResult; markingLearned: boolean; learnedNow: boolean }>();
const emit = defineEmits<{ practiceMistakes: []; done: []; markLearned: [] }>();

const confidenceAfter = computed(() => (props.result.confidenceAfter === null ? null : Math.round(props.result.confidenceAfter)));
const confidenceBefore = computed(() => (props.result.confidenceBefore === null ? null : Math.round(props.result.confidenceBefore)));
</script>

<template>
    <div class="space-y-4">
        <div>
            <p class="text-5xl font-bold text-fg" data-test="score">{{ Math.round(result.scorePct) }}%</p>
            <p class="mt-1 text-sm text-muted-foreground" data-test="counts">
                {{ result.firstTry }} first try · {{ result.afterHint }} after a hint · {{ result.missed }} missed
            </p>
        </div>

        <div v-if="confidenceAfter !== null" class="rounded-spa-lg border border-border p-4">
            <div class="flex items-center justify-between gap-2 text-sm">
                <span class="text-fg">Practice says</span>
                <span>
                    <span v-if="confidenceBefore !== null && confidenceBefore !== confidenceAfter" class="text-muted-foreground">{{ confidenceBefore }}% → </span>
                    <b class="text-fg" data-test="confidence-after">{{ confidenceAfter }}%</b>
                </span>
            </div>
            <div class="mt-2 h-2 overflow-hidden rounded-full bg-surface-alt">
                <i class="block h-full rounded-full bg-success" :style="{ width: `${confidenceAfter}%` }" />
            </div>
            <p class="mt-2 text-sm text-muted-foreground">{{ result.learned || learnedNow ? 'Learned' : 'In My grammar' }}</p>
        </div>

        <div v-if="result.toReview.length > 0">
            <p class="mb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground">To review</p>
            <div
                v-for="item in result.toReview"
                :key="item.exerciseId"
                class="border-t border-border py-3 text-sm"
                data-test="review-item"
            >
                <p class="text-xs text-muted-foreground">{{ item.instruction ?? EXERCISE_TASK[item.type] }}</p>
                <p class="break-words text-fg">{{ item.prompt }}</p>
                <p class="mt-0.5 break-words">
                    <s v-if="item.given" class="mr-1 text-danger">{{ item.given }}</s>
                    <span class="font-semibold text-success-fg">{{ item.answer }}</span>
                </p>
            </div>
        </div>

        <div class="grid gap-2 pt-2">
            <UiButton v-if="result.toReview.length > 0" variant="primary" size="lg" class="w-full" data-test="practice-mistakes" @click="emit('practiceMistakes')">
                Practice mistakes
            </UiButton>
            <UiButton :variant="result.toReview.length > 0 ? 'secondary' : 'primary'" size="lg" class="w-full" data-test="done" @click="emit('done')">Done</UiButton>
            <UiButton
                v-if="result.canMarkLearned && !learnedNow"
                variant="ghost"
                size="lg"
                class="w-full text-primary"
                :disabled="markingLearned"
                data-test="mark-learned"
                @click="emit('markLearned')"
            >
                Mark as learned
            </UiButton>
            <p v-if="learnedNow" class="text-center text-sm text-success-fg">Marked as learned</p>
        </div>
    </div>
</template>
