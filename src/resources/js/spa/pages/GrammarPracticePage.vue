<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { MoreHorizontal, X } from 'lucide-vue-next';
import UiButton from '../shared/ui/UiButton.vue';
import UiDialog from '../shared/ui/UiDialog.vue';
import UiSpinner from '../shared/ui/UiSpinner.vue';
import MarkdownContent from '../shared/ui/MarkdownContent.vue';
import { grammarApi } from '../domains/content';
import { useGrammarPracticeRound } from '../domains/learning';
import type { GrammarPracticeLevel, GrammarRule } from '../types';
import BuildExercise from '../widgets/grammar/practice/BuildExercise.vue';
import ChooseExercise from '../widgets/grammar/practice/ChooseExercise.vue';
import ExerciseFeedback from '../widgets/grammar/practice/ExerciseFeedback.vue';
import TypedExercise from '../widgets/grammar/practice/TypedExercise.vue';
import { EXERCISE_TASK, LEVEL_LABEL } from '../widgets/grammar/practice/exerciseLabels';
import GrammarPracticeResult from '../widgets/grammar/GrammarPracticeResult.vue';

/**
 * Full-screen grammar practice round (VIK-31): preparing → one exercise per
 * screen → result. Closing early saves nothing.
 */
const route = useRoute();
const router = useRouter();

const ruleId = Number(route.params.id);
const level = (['easy', 'medium', 'hard'].includes(String(route.query.level)) ? route.query.level : 'medium') as GrammarPracticeLevel;
const count = [5, 10, 15].includes(Number(route.query.count)) ? Number(route.query.count) : 10;
const exerciseIds = typeof route.query.ids === 'string' && route.query.ids !== ''
    ? route.query.ids.split(',').map(Number).filter(Number.isFinite)
    : undefined;
const contentId = route.query.content ? Number(route.query.content) : null;
const from = typeof route.query.from === 'string' && route.query.from.startsWith('/') ? route.query.from : null;

const round = useGrammarPracticeRound({ ruleId, level, count, exerciseIds, contentId });
const { phase, current, index, total, hint, struckOptions, settled, busy, result, error, unavailableReason } = round;

const rule = ref<GrammarRule | null>(null);
const typed = ref('');
const menuOpen = ref(false);
const ruleSheetOpen = ref(false);
const leaveOpen = ref(false);
const markingLearned = ref(false);
const learnedNow = ref(false);

const progressPct = computed(() => (total.value === 0 ? 0 : Math.round(((index.value + (phase.value === 'settled' ? 1 : 0)) / total.value) * 100)));
const task = computed(() => (current.value ? current.value.instruction ?? EXERCISE_TASK[current.value.type] : ''));
const isTyped = computed(() => current.value !== null && current.value.type !== 'multiple_choice');
const inputState = computed<'idle' | 'wrong' | 'right'>(() => {
    if (settled.value) return settled.value.correct ? 'right' : 'wrong';
    return hint.value ? 'wrong' : 'idle';
});

watch(() => current.value?.id, () => { typed.value = ''; });

function back(): void {
    void router.push(from ?? { name: 'grammar.details', params: { id: ruleId } });
}

function close(): void {
    if (round.answeredCount.value > 0 && phase.value !== 'result') {
        leaveOpen.value = true;
        return;
    }
    back();
}

function check(): void {
    if (typed.value.trim() === '') return;
    void round.submit(typed.value);
}

async function report(): Promise<void> {
    menuOpen.value = false;
    await round.report();
}

async function skipFromMenu(): Promise<void> {
    menuOpen.value = false;
    await round.showAnswer();
}

function practiceMistakes(): void {
    if (!result.value) return;
    learnedNow.value = false;
    void round.restart(result.value.toReview.map((item) => item.exerciseId));
}

async function markLearned(): Promise<void> {
    markingLearned.value = true;
    try {
        await grammarApi.markLearned(ruleId);
        learnedNow.value = true;
    } finally {
        markingLearned.value = false;
    }
}

async function openRule(): Promise<void> {
    ruleSheetOpen.value = true;
    if (rule.value) return;
    try {
        rule.value = (await grammarApi.getOne(ruleId)).rule;
    } catch {
        rule.value = null;
    }
}

onMounted(async () => {
    void round.start();
    try {
        rule.value = (await grammarApi.getOne(ruleId)).rule;
    } catch {
        rule.value = null;
    }
});
</script>

<template>
    <div class="mx-auto flex min-h-[100dvh] w-full max-w-xl flex-col bg-background">
        <header class="flex items-center gap-3 px-4 pb-2 pt-[max(0.75rem,env(safe-area-inset-top))]">
            <button type="button" class="-ml-2 flex h-11 w-11 shrink-0 items-center justify-center rounded text-muted-foreground" aria-label="Close practice" data-test="close" @click="close">
                <X :size="22" />
            </button>
            <template v-if="phase === 'answering' || phase === 'settled'">
                <div class="h-2 min-w-0 flex-1 overflow-hidden rounded-full bg-surface-alt" role="progressbar" :aria-valuenow="index + 1" :aria-valuemax="total">
                    <i class="block h-full rounded-full bg-primary transition-all" :style="{ width: `${progressPct}%` }" />
                </div>
                <span class="shrink-0 text-sm text-muted-foreground" data-test="progress">{{ index + 1 }} / {{ total }}</span>
                <button type="button" class="-mr-2 flex h-11 w-11 shrink-0 items-center justify-center rounded text-muted-foreground" aria-label="More" data-test="menu" @click="menuOpen = true">
                    <MoreHorizontal :size="22" />
                </button>
            </template>
            <span v-else class="min-w-0 truncate text-sm text-muted-foreground">{{ rule?.title }}<template v-if="phase === 'result'"> · {{ LEVEL_LABEL[level] }}</template></span>
        </header>

        <!-- Preparing / loading -->
        <main v-if="phase === 'loading' || phase === 'preparing'" class="flex flex-1 flex-col items-center justify-center px-6 text-center" data-test="preparing">
            <UiSpinner />
            <template v-if="phase === 'preparing'">
                <p class="mt-4 font-semibold text-fg">Preparing exercises…</p>
                <p class="mt-1 text-sm text-muted-foreground">Usually about 10 seconds. The round starts as soon as 5 are ready.</p>
                <UiButton variant="ghost" class="mt-6 text-primary" @click="openRule">Read the rule meanwhile</UiButton>
            </template>
        </main>

        <!-- Can't prepare -->
        <main v-else-if="phase === 'unavailable' || phase === 'error'" class="flex flex-1 flex-col items-center justify-center px-6 text-center" data-test="unavailable">
            <p class="font-semibold text-fg">{{ phase === 'error' ? error : "Exercises can't be prepared right now" }}</p>
            <p v-if="unavailableReason === 'limited'" class="mt-1 text-sm text-muted-foreground">You've had a lot of new exercises for this rule today. Come back tomorrow for more.</p>
            <p v-else-if="phase === 'unavailable'" class="mt-1 text-sm text-muted-foreground">The rule itself is still there to read.</p>
            <div class="mt-6 grid w-full max-w-xs gap-2">
                <UiButton v-if="unavailableReason !== 'limited'" variant="primary" size="lg" @click="round.retry">Try again</UiButton>
                <UiButton variant="secondary" size="lg" @click="back">Back to the rule</UiButton>
            </div>
        </main>

        <!-- Exercise -->
        <template v-else-if="(phase === 'answering' || phase === 'settled') && current">
            <main class="flex-1 overflow-y-auto px-4 pb-4">
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" class="min-h-11 text-sm font-medium text-primary" data-test="rule-link" @click="openRule">{{ rule?.title ?? 'Rule' }} ›</button>
                    <span v-if="current.origin === 'ai'" class="rounded-full border border-border bg-surface-alt px-2 py-0.5 text-[11px] text-muted-foreground" title="Written by AI. Report it if something is wrong.">AI</span>
                </div>
                <p class="mb-2 mt-1 text-xs font-medium uppercase tracking-wide text-muted-foreground" data-test="task">{{ task }}</p>

                <ChooseExercise
                    v-if="current.type === 'multiple_choice'"
                    :exercise="current"
                    :struck="struckOptions"
                    :answer="settled?.answer ?? null"
                    :disabled="busy || phase === 'settled'"
                    @answer="round.submit"
                />
                <BuildExercise v-else-if="current.type === 'build'" v-model="typed" :exercise="current" :disabled="busy || phase === 'settled'" />
                <TypedExercise v-else v-model="typed" :exercise="current" :disabled="busy || phase === 'settled'" :state="inputState" @submit="phase === 'settled' ? round.next() : check()" />

                <div class="mt-4">
                    <ExerciseFeedback :hint="hint" :settled="settled" @see-rule="openRule" />
                </div>
                <p v-if="error" class="mt-3 text-sm text-danger">{{ error }}</p>
            </main>

            <footer class="sticky bottom-0 grid gap-1 border-t border-border bg-background px-4 pb-[max(1rem,env(safe-area-inset-bottom))] pt-3">
                <UiButton v-if="phase === 'settled'" variant="primary" size="lg" class="w-full" data-test="next" @click="round.next">
                    {{ index + 1 < total ? 'Next' : 'See result' }}
                </UiButton>
                <template v-else>
                    <UiButton v-if="isTyped" variant="primary" size="lg" class="w-full" :disabled="busy || typed.trim() === ''" data-test="check" @click="check">
                        {{ hint ? 'Try again' : 'Check' }}
                    </UiButton>
                    <UiButton variant="ghost" size="lg" class="w-full text-primary" :disabled="busy" data-test="show-answer" @click="round.showAnswer">Show answer</UiButton>
                </template>
            </footer>
        </template>

        <!-- Saving / result -->
        <main v-else-if="phase === 'saving'" class="flex flex-1 items-center justify-center"><UiSpinner /></main>
        <main v-else-if="phase === 'result'" class="flex-1 px-4 pb-[max(1.5rem,env(safe-area-inset-bottom))]">
            <GrammarPracticeResult
                v-if="result"
                :result="result"
                :marking-learned="markingLearned"
                :learned-now="learnedNow"
                @practice-mistakes="practiceMistakes"
                @done="back"
                @mark-learned="markLearned"
            />
            <div v-else class="pt-16 text-center">
                <p class="font-semibold text-fg">Nothing to score this time</p>
                <p class="mt-1 text-sm text-muted-foreground">Every exercise in the round was reported.</p>
                <UiButton variant="primary" size="lg" class="mt-6 w-full" @click="back">Done</UiButton>
            </div>
        </main>

        <UiDialog :open="menuOpen" title="This exercise" sheet @close="menuOpen = false">
            <div class="grid gap-2">
                <UiButton variant="secondary" size="lg" class="w-full" :disabled="busy" data-test="report" @click="report">Report a bad exercise</UiButton>
                <UiButton variant="ghost" size="lg" class="w-full" :disabled="busy || phase === 'settled'" @click="skipFromMenu">Skip (show the answer)</UiButton>
            </div>
            <p class="mt-3 text-xs text-muted-foreground">A reported exercise is hidden for you right away and replaced in this round.</p>
        </UiDialog>

        <UiDialog :open="ruleSheetOpen" :title="rule?.title ?? 'Rule'" sheet @close="ruleSheetOpen = false">
            <p v-if="rule?.summary" class="text-sm text-muted-foreground">{{ rule.summary }}</p>
            <MarkdownContent class="mt-3" :content="rule?.body" />
            <div v-if="rule?.examples?.length" class="mt-4 space-y-2">
                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Examples</p>
                <p v-for="(example, i) in rule.examples" :key="i" class="text-sm text-fg">{{ example.example }}</p>
            </div>
        </UiDialog>

        <UiDialog :open="leaveOpen" title="Leave the round?" sheet @close="leaveOpen = false">
            <p class="text-sm text-muted-foreground">Your answers in this round won't be saved.</p>
            <div class="mt-4 grid gap-2">
                <UiButton variant="primary" size="lg" class="w-full" @click="leaveOpen = false">Keep practicing</UiButton>
                <UiButton variant="ghost" size="lg" class="w-full" data-test="leave" @click="back">Leave</UiButton>
            </div>
        </UiDialog>
    </div>
</template>
