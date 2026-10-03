<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ChevronLeft } from 'lucide-vue-next';
import PageState from '../components/ui/PageState.vue';
import { useAuthStore } from '../domains/user';
import { useContextPracticeSession } from '../domains/content';
import UiButton from '../shared/ui/UiButton.vue';
import UiCard from '../shared/ui/UiCard.vue';
import UiEmptyState from '../shared/ui/UiEmptyState.vue';
import UiSectionHeader from '../shared/ui/UiSectionHeader.vue';
import ContextSentenceCard from '../widgets/trainer/ContextSentenceCard.vue';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const {
    phase,
    error,
    busy,
    generating,
    contentTitle,
    currentCard,
    currentIndex,
    sessionTotal,
    correctCount,
    startSession,
    submitResult,
    generateNewSentence,
} = useContextPracticeSession();

const cardNumber = computed(() => currentIndex.value + 1);

const progressDots = computed(() =>
    Array.from({ length: sessionTotal.value }, (_, i) => (i < currentIndex.value ? 'done' : i === currentIndex.value ? 'current' : 'upcoming')),
);

function load() {
    const contentId = Number(route.query.content_id);
    const lexemeIds = String(route.query.lexeme_ids ?? '')
        .split(',')
        .map((id) => Number(id))
        .filter((id) => !Number.isNaN(id));

    if (!contentId || lexemeIds.length === 0) {
        router.replace({ name: 'catalog' });
        return;
    }
    void startSession(contentId, lexemeIds);
}

function backFromPractice() {
    if (route.query.return_to === 'repetitions') {
        router.push({ name: 'repetitions', query: { content_id: String(route.query.content_id ?? ''), lexeme_ids: String(route.query.lexeme_ids ?? '') } });
        return;
    }
    router.push({ name: 'catalog.details', params: { id: String(route.query.content_id ?? '') } });
}

onMounted(() => {
    if (!authStore.isAuthenticated) {
        router.push({ name: 'login', query: { redirect: route.fullPath } });
        return;
    }
    load();
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
                    <div class="truncate text-sm font-semibold text-fg">{{ contentTitle || 'Context practice' }}</div>
                    <div class="text-xs text-muted-foreground">Translate sentences</div>
                </div>
                <span class="h-10 w-10" aria-hidden="true"></span>
            </div>
        </header>

        <main class="mx-auto flex min-h-[calc(100vh-65px)] max-w-2xl flex-col px-4 py-4 pb-24 sm:py-6 sm:pb-24 lg:pb-6">
            <p class="text-center text-sm leading-6 text-muted-foreground">
                Read a sentence in your own language, produce it in the language you're learning, then compare.
            </p>

            <UiCard v-if="error && phase === 'empty'" class="mt-4 border-destructive/40 bg-destructive/5">
                <p class="text-sm font-medium text-destructive" role="alert">{{ error }}</p>
                <p class="mt-1 text-xs text-muted-foreground">The practice data could not be loaded. Check your connection or try again.</p>
                <UiButton class="mt-3" variant="secondary" size="sm" @click="load">Try again</UiButton>
            </UiCard>

            <PageState v-if="phase === 'loading'" :loading="true" />

            <UiEmptyState
                v-else-if="phase === 'empty' && !error"
                title="No context sentences available"
                description="None of the selected words have an example sentence to practice with yet."
            >
                <UiButton variant="secondary" size="sm" @click="backFromPractice">Go back</UiButton>
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
                    <ContextSentenceCard
                        class="w-full"
                        :card="currentCard"
                        :busy="busy"
                        :generating="generating"
                        @result="submitResult"
                        @regenerate="generateNewSentence"
                    />
                </div>
            </template>

            <template v-else-if="phase === 'summary'">
                <UiCard class="mt-6 space-y-4">
                    <UiSectionHeader title="Session complete" subtitle="Here's what happened" />
                    <p class="text-sm text-muted-foreground">{{ correctCount }} / {{ sessionTotal }} felt right</p>
                    <div class="flex flex-wrap gap-2">
                        <UiButton variant="primary" @click="load">Practice again</UiButton>
                        <UiButton variant="secondary" @click="router.push({ name: 'catalog' })">Catalog</UiButton>
                    </div>
                </UiCard>
            </template>
        </main>
    </div>
</template>
