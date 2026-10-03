<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Check, ChevronLeft, RotateCcw } from 'lucide-vue-next';
import PageState from '../components/ui/PageState.vue';
import { useAuthStore } from '../domains/user';
import { useSentencePracticeSession } from '../domains/learning';
import UiButton from '../shared/ui/UiButton.vue';
import UiCard from '../shared/ui/UiCard.vue';
import UiEmptyState from '../shared/ui/UiEmptyState.vue';
import UiSectionHeader from '../shared/ui/UiSectionHeader.vue';
import UiSwitch from '../shared/ui/UiSwitch.vue';
import SpeakingPracticeCard from '../widgets/trainer/SpeakingPracticeCard.vue';
import type { SentencePracticeCheckMode, SentencePracticeCheckResponse, SentencePracticeDirection } from '../domains/ai';

type SentenceMode = 'read' | 'write-flexible' | 'write-exact' | 'reorder';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

// Present when reached from "Reinforce" on ContentDetailsPage — scopes
// generation to that content's own words/grammar instead of the learner's
// global recent-study pool (see useSentencePracticeSession's docblock).
const contentId = computed(() => {
    const raw = route.query.content_id;
    const id = Number(raw);
    return raw && !Number.isNaN(id) ? id : undefined;
});

const { phase, error, note, busy, currentCard, currentIndex, sessionTotal, correctCount, startSession, checkAnswer, advance, restart } =
    useSentencePracticeSession(contentId.value);

const cardNumber = computed(() => currentIndex.value + 1);
const currentResult = ref<SentencePracticeCheckResponse | null>(null);
const selectedMode = ref<SentenceMode>('write-flexible');
const revealTranslationAutomatically = ref(false);
const translationRevealed = ref(false);
const selectedTokens = ref<string[]>([]);
const shuffledTokens = ref<string[]>([]);

const progressDots = computed(() =>
    Array.from({ length: sessionTotal.value }, (_, i) => (i < currentIndex.value ? 'done' : i === currentIndex.value ? 'current' : 'upcoming')),
);

const isReadMode = computed(() => selectedMode.value === 'read');
const isReorderMode = computed(() => selectedMode.value === 'reorder');
const checkMode = computed<SentencePracticeCheckMode>(() => selectedMode.value === 'write-exact' ? 'exact' : 'flexible');

function shuffle<T>(items: T[]): T[] {
    return [...items].sort(() => Math.random() - 0.5);
}

function resetSentenceMode() {
    translationRevealed.value = revealTranslationAutomatically.value;
    selectedTokens.value = [];
    shuffledTokens.value = shuffle(currentCard.value?.answer_sentence?.split(/\s+/).filter(Boolean) ?? []);
}

watch(currentCard, resetSentenceMode);

async function submitAnswer(answer: string) {
    currentResult.value = await checkAnswer(answer, checkMode.value);
}

function next() {
    currentResult.value = null;
    translationRevealed.value = false;
    advance();
}

function choose(mode: SentenceMode) {
    selectedMode.value = mode;
    currentResult.value = null;
    void startSession('to_target' as SentencePracticeDirection);
}

function toggleTranslation() {
    translationRevealed.value = !translationRevealed.value;
}

function chooseToken(token: string, index: number) {
    if (currentResult.value) return;
    selectedTokens.value.push(token);
    shuffledTokens.value.splice(index, 1);
}

function undoToken() {
    const token = selectedTokens.value.pop();
    if (token) shuffledTokens.value.push(token);
}

function checkReorderedSentence() {
    if (!currentCard.value || selectedTokens.value.length === 0) return;
    const answer = selectedTokens.value.join(' ');
    const normalize = (value: string) => value.toLocaleLowerCase().replace(/[.,!?;:]/g, '').replace(/\s+/g, ' ').trim();
    const answerSentence = currentCard.value.answer_sentence ?? '';
    const correct = normalize(answer) === normalize(answerSentence);
    currentResult.value = {
        correct,
        feedback: correct ? 'The sentence is in the right order.' : 'Try comparing the word order with the full sentence.',
        model_answer: answerSentence,
    };
}

function backFromPractice() {
    if (route.query.return_to === 'repetitions') {
        router.push({ name: 'repetitions', query: { content_id: route.query.content_id, lexeme_ids: route.query.lexeme_ids } });
        return;
    }
    if (contentId.value) {
        router.push({ name: 'catalog.details', params: { id: contentId.value } });
        return;
    }
    router.push({ name: 'repetitions' });
}

function isSentenceMode(value: unknown): value is SentenceMode {
    return value === 'read' || value === 'write-flexible' || value === 'write-exact' || value === 'reorder';
}

onMounted(() => {
    if (!authStore.isAuthenticated) {
        router.push({ name: 'login', query: { redirect: '/practice/speaking' } });
        return;
    }

    if (isSentenceMode(route.query.sentence_mode)) {
        selectedMode.value = route.query.sentence_mode;
        void startSession('to_target' as SentencePracticeDirection);
    }
});
</script>

<template>
    <div class="min-h-screen bg-background">
        <header class="sticky top-0 z-20 border-b border-border bg-background/95 backdrop-blur">
            <div class="mx-auto flex max-w-2xl items-center justify-between gap-3 px-4 py-3">
                <UiButton variant="ghost" size="icon" aria-label="Back to previous learning screen" title="Back to previous learning screen" @click="backFromPractice">
                    <ChevronLeft :size="20" />
                </UiButton>
                <div class="min-w-0 text-center">
                    <div class="truncate text-sm font-semibold text-fg">{{ contentId ? 'Reinforce' : 'Sentence practice' }}</div>
                    <div class="text-xs text-muted-foreground">Words in real sentences</div>
                </div>
                <span class="h-10 w-10" aria-hidden="true"></span>
            </div>
        </header>

        <main class="mx-auto flex min-h-[calc(100vh-65px)] max-w-2xl flex-col px-4 py-4 pb-24 sm:py-6 sm:pb-24 lg:pb-6">
            <p class="text-center text-sm leading-6 text-muted-foreground">
                {{ contentId ? `Sentences built from this content's words and grammar.` : `Sentences built from what you've recently studied.` }}
            </p>

            <p v-if="error" class="mt-4 text-sm text-warning" role="alert">{{ error }}</p>

            <UiCard v-if="phase === 'idle'" class="mt-6 space-y-5 p-4 sm:p-6">
                <div><h2 class="text-lg font-semibold text-fg">Choose how to practice</h2><p class="mt-1 text-sm text-muted-foreground">Use the same studied words in complete sentences.</p></div>
                <div class="grid gap-2 sm:grid-cols-2">
                    <button type="button" class="rounded-spa-lg border border-border bg-surface p-4 text-left transition hover:border-primary/50 hover:bg-primary/5" @click="choose('read')"><span class="block text-sm font-semibold text-fg">Read & reveal</span><span class="mt-1 block text-xs leading-5 text-muted-foreground">Read a sentence, then reveal its translation.</span></button>
                    <button type="button" class="rounded-spa-lg border border-border bg-surface p-4 text-left transition hover:border-primary/50 hover:bg-primary/5" @click="choose('write-flexible')"><span class="block text-sm font-semibold text-fg">Write · flexible</span><span class="mt-1 block text-xs leading-5 text-muted-foreground">Write a natural translation. AI checks the meaning.</span></button>
                    <button type="button" class="rounded-spa-lg border border-border bg-surface p-4 text-left transition hover:border-primary/50 hover:bg-primary/5" @click="choose('write-exact')"><span class="block text-sm font-semibold text-fg">Write · exact</span><span class="mt-1 block text-xs leading-5 text-muted-foreground">Match the model sentence and word order.</span></button>
                    <button type="button" class="rounded-spa-lg border border-border bg-surface p-4 text-left transition hover:border-primary/50 hover:bg-primary/5" @click="choose('reorder')"><span class="block text-sm font-semibold text-fg">Build the sentence</span><span class="mt-1 block text-xs leading-5 text-muted-foreground">Put the words in the correct order.</span></button>
                </div>
                <div class="border-t border-border pt-4">
                    <UiSwitch v-model="revealTranslationAutomatically" label="Show translation automatically" description="Otherwise tap the sentence or button to reveal it." />
                </div>
            </UiCard>

            <PageState v-else-if="phase === 'loading'" :loading="true" />

            <UiEmptyState v-else-if="phase === 'empty'" title="Nothing to practice yet" :description="note || 'Try again in a moment.'">
                <UiButton variant="secondary" size="sm" @click="phase = 'idle'">Choose again</UiButton>
            </UiEmptyState>

            <template v-else-if="phase === 'session' && currentCard">
                <div class="mt-4 space-y-2">
                    <div class="flex items-center justify-between text-xs font-medium text-muted-foreground"><span>Progress</span><span>{{ cardNumber }} / {{ sessionTotal }}</span></div>
                    <div class="flex items-center gap-1" aria-hidden="true">
                        <span
                            v-for="(dot, i) in progressDots"
                            :key="i"
                            class="h-1.5 flex-1 rounded-full transition-colors"
                            :class="{ 'bg-primary': dot === 'done', 'bg-primary/40': dot === 'current', 'bg-muted': dot === 'upcoming' }"
                        />
                    </div>
                </div>

                <div class="mt-4 flex flex-1 items-center">
                    <UiCard v-if="isReadMode" class="w-full space-y-5 p-4 sm:p-7">
                        <div><div class="text-[11px] font-semibold uppercase tracking-[0.2em] text-primary">Read & reveal</div><p class="mt-1 text-sm text-muted-foreground">Read the sentence, then reveal its translation.</p></div>
                        <button type="button" class="w-full rounded-spa-lg border border-border bg-surface-alt/50 px-4 py-7 text-center text-xl leading-relaxed text-fg transition hover:border-primary/40" @click="toggleTranslation">{{ currentCard.prompt_sentence }}</button>
                        <div v-if="translationRevealed" class="rounded-spa-lg bg-primary/5 px-4 py-4 text-center text-lg text-fg-secondary">{{ currentCard.answer_sentence }}</div>
                        <UiButton v-else class="w-full" variant="secondary" @click="toggleTranslation">Show translation</UiButton>
                        <UiButton v-if="translationRevealed" class="w-full" variant="primary" @click="next">I understand</UiButton>
                    </UiCard>
                    <UiCard v-else-if="isReorderMode" class="w-full space-y-5 p-4 sm:p-7">
                        <div><div class="text-[11px] font-semibold uppercase tracking-[0.2em] text-primary">Build the sentence</div><p class="mt-1 text-sm text-muted-foreground">{{ currentCard.prompt_sentence }}</p></div>
                        <div class="min-h-16 rounded-spa-lg border border-dashed border-primary/40 bg-primary/5 p-3 text-lg leading-8">
                            <span v-for="(token, index) in selectedTokens" :key="index" class="mr-1 inline-block rounded-md bg-primary/10 px-2 text-primary">{{ token }}</span>
                            <span v-if="selectedTokens.length === 0" class="text-sm text-muted-foreground">Tap words below to build the sentence</span>
                        </div>
                        <div class="flex flex-wrap justify-center gap-2">
                            <button v-for="(token, index) in shuffledTokens" :key="index + token" type="button" class="rounded-md border border-border bg-surface px-3 py-2 text-sm font-medium text-fg hover:border-primary/50" @click="chooseToken(token, index)">{{ token }}</button>
                        </div>
                        <div class="flex flex-wrap justify-center gap-2">
                            <UiButton variant="ghost" size="sm" :disabled="selectedTokens.length === 0" @click="undoToken"><RotateCcw :size="15" />Undo</UiButton>
                            <UiButton variant="primary" :disabled="selectedTokens.length === 0 || !!currentResult" @click="checkReorderedSentence"><Check :size="15" />Check order</UiButton>
                        </div>
                        <div v-if="currentResult" class="space-y-3 rounded-spa-lg bg-surface-alt/60 p-4 text-center">
                            <p :class="currentResult.correct ? 'text-success-fg' : 'text-warning-fg'">{{ currentResult.feedback }}</p>
                            <p v-if="!currentResult.correct" class="text-sm text-muted-foreground">{{ currentResult.model_answer }}</p>
                            <UiButton variant="primary" @click="next">Continue</UiButton>
                        </div>
                    </UiCard>
                    <SpeakingPracticeCard v-else class="w-full" :card="currentCard" :busy="busy" :result="currentResult" @submit="submitAnswer" @next="next" />
                </div>
            </template>

            <template v-else-if="phase === 'summary'">
                <UiCard class="mt-6 space-y-4">
                    <UiSectionHeader title="Session complete" subtitle="Here's what happened" />
                    <p class="text-sm text-muted-foreground">{{ correctCount }} / {{ sessionTotal }} correct</p>
                    <div class="flex flex-wrap gap-2">
                        <UiButton variant="primary" @click="restart">Practice again</UiButton>
                        <UiButton
                            variant="secondary"
                            @click="contentId ? router.push({ name: 'catalog.details', params: { id: contentId } }) : router.push({ name: 'repetitions' })"
                        >
                            {{ contentId ? 'Back to content' : 'Back to practice' }}
                        </UiButton>
                    </div>
                </UiCard>
            </template>
        </main>
    </div>
</template>
