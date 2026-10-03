<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { CheckCircle2, XCircle } from 'lucide-vue-next';
import PageState from '../components/ui/PageState.vue';
import { useAuthStore } from '../domains/user';
import { grammarApi } from '../domains/content';
import { useGrammarPreExamSession } from '../domains/learning';
import type { GrammarRule } from '../types';
import type { GrammarPreExamType } from '../domains/learning';
import type { SentencePracticeCheckResponse } from '../domains/ai';
import UiButton from '../shared/ui/UiButton.vue';
import UiCard from '../shared/ui/UiCard.vue';
import UiEmptyState from '../shared/ui/UiEmptyState.vue';
import UiSectionHeader from '../shared/ui/UiSectionHeader.vue';
import SpeakingPracticeCard from '../widgets/trainer/SpeakingPracticeCard.vue';

// Confidence bar/threshold below which a topic is pre-checked as
// "needs review" — matches the read of low-confidence used elsewhere for
// this feature (ContentGrammarPreExamController's docblock, the plan this
// page implements).
const LOW_CONFIDENCE_THRESHOLD = 60;

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const contentId = Number(route.params.id);

const rulesLoading = ref(true);
const rules = ref<GrammarRule[]>([]);
const selectedIds = ref<Set<number>>(new Set());
const examType = ref<GrammarPreExamType>('pre');

const {
    phase,
    error,
    busy,
    currentCard,
    currentIndex,
    sessionTotal,
    attemptGroups,
    start,
    submitAnswer,
    next,
    reset,
} = useGrammarPreExamSession(contentId);

const currentResult = ref<SentencePracticeCheckResponse | null>(null);
const cardNumber = computed(() => currentIndex.value + 1);

const progressDots = computed(() =>
    Array.from({ length: sessionTotal.value }, (_, i) => (i < currentIndex.value ? 'done' : i === currentIndex.value ? 'current' : 'upcoming')),
);

function confidenceOf(rule: GrammarRule): number | null {
    return rule.confidence_calculated ?? rule.confidence_manual ?? null;
}

function toggleRule(ruleId: number) {
    const next = new Set(selectedIds.value);
    if (next.has(ruleId)) next.delete(ruleId);
    else next.add(ruleId);
    selectedIds.value = next;
}

function beforeConfidenceFor(ruleId: number): number | null {
    return confidenceOf(rules.value.find((r) => r.id === ruleId) as GrammarRule) ?? null;
}

async function loadRules() {
    rulesLoading.value = true;
    try {
        const data = await grammarApi.getForContent(contentId);
        rules.value = data.rules ?? [];
        selectedIds.value = new Set(
            rules.value.filter((r) => (confidenceOf(r) ?? 0) < LOW_CONFIDENCE_THRESHOLD).map((r) => r.id),
        );
    } catch {
        rules.value = [];
    } finally {
        rulesLoading.value = false;
    }
}

async function onStart() {
    await start(examType.value, Array.from(selectedIds.value));
}

async function onSubmit(answer: string) {
    currentResult.value = await submitAnswer(answer);
}

async function onNext() {
    // Only clear the shown result when actually moving to a new card — on
    // the last card this triggers finish() instead, and if that fails
    // (e.g. a transient network error) keeping the result visible lets the
    // learner just press Next again to retry, instead of losing their
    // answer and being forced to re-submit it.
    const isLastCard = currentIndex.value >= sessionTotal.value - 1;
    if (!isLastCard) currentResult.value = null;
    await next();
}

function onRestart() {
    reset();
    void loadRules();
}

function backToContent() {
    router.push({ name: 'catalog.details', params: { id: contentId } });
}

onMounted(() => {
    if (!authStore.isAuthenticated) {
        router.push({ name: 'login', query: { redirect: route.fullPath } });
        return;
    }
    void loadRules();
});
</script>

<template>
    <div class="space-y-6">
        <UiSectionHeader
            title="Grammar warm-up"
            subtitle="Check yourself on this content's grammar before (or after) you watch — pick topics, answer a few sentences, see where you stand."
        />

        <p v-if="error && phase !== 'select'" class="text-sm text-warning" role="alert">{{ error }}</p>

        <template v-if="phase === 'select'">
            <PageState v-if="rulesLoading" :loading="true" />

            <UiEmptyState
                v-else-if="rules.length === 0"
                title="No grammar linked to this content"
                description="There's nothing to warm up on yet — this content has no grammar rules attached."
            >
                <UiButton variant="secondary" size="sm" @click="backToContent">Back to content</UiButton>
            </UiEmptyState>

            <template v-else>
                <UiCard class="space-y-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <UiButton :variant="examType === 'pre' ? 'primary' : 'secondary'" size="sm" @click="examType = 'pre'">Before watching</UiButton>
                        <UiButton :variant="examType === 'post' ? 'primary' : 'secondary'" size="sm" @click="examType = 'post'">After watching</UiButton>
                    </div>

                    <div class="space-y-2">
                        <label
                            v-for="rule in rules"
                            :key="rule.id"
                            class="flex cursor-pointer items-center justify-between gap-3 rounded-spa-lg border border-border bg-black/10 px-4 py-3"
                        >
                            <span class="flex items-center gap-3">
                                <input
                                    type="checkbox"
                                    class="h-4 w-4"
                                    :checked="selectedIds.has(rule.id)"
                                    @change="toggleRule(rule.id)"
                                />
                                <span class="text-sm text-fg">{{ rule.title }}</span>
                            </span>
                            <span class="flex items-center gap-3 text-xs text-muted-foreground">
                                <span v-if="confidenceOf(rule) !== null">Confidence: {{ Math.round(confidenceOf(rule) as number) }}%</span>
                                <span v-else>No data yet</span>
                            </span>
                        </label>
                    </div>

                    <UiButton variant="primary" :disabled="selectedIds.size === 0" @click="onStart">
                        Start warm-up ({{ selectedIds.size }} {{ selectedIds.size === 1 ? 'topic' : 'topics' }})
                    </UiButton>
                </UiCard>
            </template>
        </template>

        <PageState v-else-if="phase === 'loading'" :loading="true" />

        <UiEmptyState v-else-if="phase === 'empty'" title="Nothing to practice" description="AI couldn't build cards from these topics.">
            <UiButton variant="secondary" size="sm" @click="onRestart">Back to topics</UiButton>
        </UiEmptyState>

        <UiEmptyState v-else-if="phase === 'error'" title="Something went wrong" :description="error || 'Try again in a moment.'">
            <UiButton variant="secondary" size="sm" @click="onRestart">Back to topics</UiButton>
        </UiEmptyState>

        <template v-else-if="phase === 'session' && currentCard">
            <UiCard class="space-y-2">
                <div class="flex items-center gap-1">
                    <span
                        v-for="(dot, i) in progressDots"
                        :key="i"
                        class="h-1.5 flex-1 rounded-full transition-colors"
                        :class="{ 'bg-primary': dot === 'done', 'bg-primary/40': dot === 'current', 'bg-muted': dot === 'upcoming' }"
                    />
                </div>
                <div class="text-sm text-muted-foreground">Question {{ cardNumber }} of {{ sessionTotal }}</div>
            </UiCard>

            <SpeakingPracticeCard :card="currentCard" :busy="busy" :result="currentResult" @submit="onSubmit" @next="onNext" />
        </template>

        <template v-else-if="phase === 'summary'">
            <UiCard class="space-y-4">
                <UiSectionHeader title="Warm-up results" subtitle="Your updated confidence per topic" />
                <div class="space-y-3">
                    <div
                        v-for="group in attemptGroups"
                        :key="group.grammar_rule_id"
                        class="rounded-spa-lg border border-border bg-black/10 p-3"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-sm font-medium text-fg">
                                {{ rules.find((r) => r.id === group.grammar_rule_id)?.title ?? `Rule #${group.grammar_rule_id}` }}
                            </span>
                            <span class="text-sm text-muted-foreground">
                                {{ group.attempt.correct_count }}/{{ group.attempt.total_cards }} ({{ group.attempt.score_pct }}%)
                            </span>
                        </div>
                        <div v-if="group.confidence_calculated !== null" class="mt-1 text-xs text-muted-foreground">
                            Confidence:
                            <span v-if="beforeConfidenceFor(group.grammar_rule_id) !== null">{{ Math.round(beforeConfidenceFor(group.grammar_rule_id) as number) }}% &rarr;</span>
                            {{ Math.round(group.confidence_calculated) }}%
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <UiButton variant="primary" @click="onRestart">Warm up again</UiButton>
                    <UiButton variant="secondary" @click="backToContent">Back to content</UiButton>
                </div>
            </UiCard>

            <UiCard class="space-y-3">
                <UiSectionHeader title="Review" subtitle="What you answered, right or wrong" />
                <div class="space-y-3">
                    <template v-for="group in attemptGroups" :key="`review-${group.grammar_rule_id}`">
                        <div
                            v-for="(item, i) in group.attempt.items"
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
                    </template>
                </div>
            </UiCard>
        </template>
    </div>
</template>
