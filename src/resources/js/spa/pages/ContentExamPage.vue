<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { CheckCircle2, ChevronLeft, XCircle } from 'lucide-vue-next';
import PageState from '../components/ui/PageState.vue';
import { useAuthStore } from '../domains/user';
import { useContentExamSession } from '../domains/learning';
import UiButton from '../shared/ui/UiButton.vue';
import UiCard from '../shared/ui/UiCard.vue';
import UiEmptyState from '../shared/ui/UiEmptyState.vue';
import UiSectionHeader from '../shared/ui/UiSectionHeader.vue';
import SpeakingPracticeCard from '../widgets/trainer/SpeakingPracticeCard.vue';
import type { SentencePracticeCheckResponse } from '../domains/ai';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const contentId = Number(route.params.id);

const { phase, error, busy, currentCard, currentIndex, sessionTotal, finalAttempt, start, submitAnswer, next } =
    useContentExamSession(contentId);

const currentResult = ref<SentencePracticeCheckResponse | null>(null);
const cardNumber = computed(() => currentIndex.value + 1);

const progressDots = computed(() =>
    Array.from({ length: sessionTotal.value }, (_, i) => (i < currentIndex.value ? 'done' : i === currentIndex.value ? 'current' : 'upcoming')),
);

async function onSubmit(answer: string) {
    currentResult.value = await submitAnswer(answer);
}

async function onNext() {
    currentResult.value = null;
    await next();
}

function backToContent() {
    router.push({ name: 'catalog.details', params: { id: contentId } });
}

onMounted(() => {
    if (!authStore.isAuthenticated) {
        router.push({ name: 'login', query: { redirect: route.fullPath } });
        return;
    }
    void start();
});
</script>

<template>
    <div class="min-h-screen bg-background">
        <header class="sticky top-0 z-20 border-b border-border bg-background/95 backdrop-blur">
            <div class="mx-auto flex max-w-2xl items-center justify-between gap-3 px-4 py-3">
                <UiButton variant="ghost" size="icon" aria-label="Back to content" title="Back to content" @click="backToContent">
                    <ChevronLeft :size="20" />
                </UiButton>
                <div class="min-w-0 text-center">
                    <div class="truncate text-sm font-semibold text-fg">Ready check</div>
                    <div class="text-xs text-muted-foreground">Mixed content exam</div>
                </div>
                <span class="h-10 w-10" aria-hidden="true"></span>
            </div>
        </header>

        <main class="mx-auto flex min-h-[calc(100vh-65px)] max-w-2xl flex-col px-4 py-4 sm:py-6">
            <p class="text-center text-sm leading-6 text-muted-foreground">Mixed sentences from this content's words and grammar.</p>

            <p v-if="error && phase !== 'blocked'" class="mt-4 text-sm text-warning" role="alert">{{ error }}</p>

            <PageState v-if="phase === 'loading'" :loading="true" />

            <UiEmptyState v-else-if="phase === 'blocked'" title="Not ready for the exam yet" :description="error">
                <UiButton variant="secondary" size="sm" @click="backToContent">Back to content</UiButton>
            </UiEmptyState>

            <UiEmptyState v-else-if="phase === 'error'" title="Something went wrong" :description="error || 'Try again in a moment.'">
                <UiButton variant="secondary" size="sm" @click="start">Try again</UiButton>
            </UiEmptyState>

            <template v-else-if="phase === 'session' && currentCard">
                <div class="mt-4 space-y-2">
                    <div class="flex items-center gap-1">
                        <span
                            v-for="(dot, i) in progressDots"
                            :key="i"
                            class="h-1.5 flex-1 rounded-full transition-colors"
                            :class="{ 'bg-primary': dot === 'done', 'bg-primary/40': dot === 'current', 'bg-muted': dot === 'upcoming' }"
                        />
                    </div>
                    <div class="text-center text-sm text-muted-foreground">Question {{ cardNumber }} of {{ sessionTotal }}</div>
                </div>

                <div class="mt-4 flex flex-1 items-center">
                    <SpeakingPracticeCard class="w-full" :card="currentCard" :busy="busy" :result="currentResult" @submit="onSubmit" @next="onNext" />
                </div>
            </template>

            <template v-else-if="phase === 'summary' && finalAttempt">
                <UiCard class="mt-6 space-y-4" :class="finalAttempt.passed ? 'border-emerald-500/40 bg-emerald-500/10' : 'border-warning-border bg-warning-bg/70'">
                    <UiSectionHeader
                        :title="finalAttempt.passed ? 'Ready to watch!' : 'Not quite ready yet'"
                        :subtitle="`${finalAttempt.correct_count} / ${finalAttempt.total_cards} correct (${finalAttempt.score_pct}%, needed ${finalAttempt.pass_threshold_pct}%)`"
                    />
                    <div class="flex flex-wrap gap-2">
                        <UiButton variant="primary" @click="start">{{ finalAttempt.passed ? 'Retake exam' : 'Try again' }}</UiButton>
                        <UiButton variant="secondary" @click="backToContent">Back to content</UiButton>
                    </div>
                </UiCard>

                <UiCard class="mt-4 space-y-3">
                    <UiSectionHeader title="Review" subtitle="What you answered, right or wrong" />
                    <div class="space-y-3">
                        <div
                            v-for="(item, i) in finalAttempt.items"
                            :key="i"
                            class="flex gap-3 rounded-spa-lg border border-border bg-black/10 p-3"
                        >
                            <component :is="item.correct ? CheckCircle2 : XCircle" :size="18" :class="item.correct ? 'text-emerald-500' : 'text-warning'" class="mt-0.5 shrink-0" />
                            <div class="space-y-1 text-sm">
                                <p class="text-fg">{{ item.prompt_sentence }}</p>
                                <p class="text-muted-foreground">Your answer: {{ item.answer }}</p>
                                <p v-if="!item.correct && item.model_answer" class="text-fg-secondary">Example: {{ item.model_answer }}</p>
                            </div>
                        </div>
                    </div>
                </UiCard>
            </template>
        </main>
    </div>
</template>
