<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PageState from '../components/ui/PageState.vue';
import UiButton from '../shared/ui/UiButton.vue';
import UiSectionHeader from '../shared/ui/UiSectionHeader.vue';
import GrammarRuleExamples from '../widgets/grammar/GrammarRuleExamples.vue';
import GrammarRuleHeader from '../widgets/grammar/GrammarRuleHeader.vue';
import MarkdownContent from '../shared/ui/MarkdownContent.vue';
import ExercisePractice from '../widgets/grammar/ExercisePractice.vue';
import { grammarApi } from '../domains/content';
import { useAuthStore } from '../domains/user';
import type { GrammarRule, GrammarRuleExercise } from '../types';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const ruleId = computed(() => route.params.id as string);

const loading = ref(true);
const error = ref('');
const rule = ref<GrammarRule | null>(null);
const exercises = ref<GrammarRuleExercise[]>([]);
const progressBusy = ref(false);

async function loadRule(): Promise<void> {
    loading.value = true;
    error.value = '';
    try {
        const [ruleData, exercisesData] = await Promise.all([
            grammarApi.getOne(ruleId.value),
            grammarApi.getExercises(ruleId.value).catch(() => ({ exercises: [] })),
        ]);
        rule.value = ruleData.rule;
        exercises.value = exercisesData.exercises;
    } catch {
        error.value = 'Grammar rule not found or unavailable.';
    } finally {
        loading.value = false;
    }
}

async function addToMyList(): Promise<void> {
    if (!rule.value) return;
    progressBusy.value = true;
    try {
        await grammarApi.startLearning(rule.value.id);
        rule.value.in_my_list = true;
    } finally {
        progressBusy.value = false;
    }
}

async function markLearned(): Promise<void> {
    if (!rule.value) return;
    progressBusy.value = true;
    try {
        await grammarApi.markLearned(rule.value.id);
        rule.value.in_my_list = true;
        rule.value.learned = true;
    } finally {
        progressBusy.value = false;
    }
}

async function removeFromMyList(): Promise<void> {
    if (!rule.value) return;
    progressBusy.value = true;
    try {
        await grammarApi.unmarkLearned(rule.value.id);
        rule.value.in_my_list = false;
        rule.value.learned = false;
    } finally {
        progressBusy.value = false;
    }
}

/** Back to where the learner came from (My grammar, a content page…); the grammar list on a direct visit. */
function goBack(): void {
    if (router.options.history.state.back) router.back();
    else router.push({ name: 'grammar' });
}

function practice(): void {
    document.getElementById('rule-exercises')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

/** Task 6.1 chat entry (same query params as AskAiButton), opened from the ⋯ menu. */
function discuss(): void {
    if (!rule.value) return;
    router.push({
        name: 'chat',
        query: { context_type: 'grammar', context_id: String(rule.value.id), context_title: rule.value.title },
    });
}

onMounted(loadRule);
</script>

<template>
    <PageState :loading="loading" :error="error">
        <template #retry>
            <UiButton variant="secondary" size="sm" @click="loadRule">Try again</UiButton>
        </template>
        <!-- VIK-41: compact header; full-width sections on the phone, cards from sm (as VIK-38). -->
        <div v-if="rule" class="space-y-6">
            <section class="sm:rounded-xl sm:border sm:border-border sm:bg-card sm:p-5" data-test="rule-header">
                <GrammarRuleHeader
                    :rule="rule"
                    :authenticated="authStore.isAuthenticated"
                    :busy="progressBusy"
                    @back="goBack"
                    @add="addToMyList"
                    @practice="practice"
                    @learned="markLearned"
                    @remove="removeFromMyList"
                    @discuss="discuss"
                />
            </section>

            <section class="space-y-3 border-t border-border pt-5 sm:rounded-xl sm:border sm:bg-card sm:p-5">
                <UiSectionHeader title="Explanation" :subtitle="rule.topic?.name" />
                <p v-if="rule.summary" class="text-base leading-7 text-muted-foreground">{{ rule.summary }}</p>
                <MarkdownContent :content="rule.body" />
            </section>

            <section class="space-y-3 border-t border-border pt-5 sm:rounded-xl sm:border sm:bg-card sm:p-5">
                <UiSectionHeader title="Examples" />
                <GrammarRuleExamples
                    :rule-id="rule.id"
                    :initial-examples="rule.examples ?? []"
                    :language="rule.language"
                    :authenticated="authStore.isAuthenticated"
                />
            </section>

            <section id="rule-exercises" class="scroll-mt-24 space-y-3 border-t border-border pt-5 sm:rounded-xl sm:border sm:bg-card sm:p-5">
                <UiSectionHeader title="Exercises" subtitle="Practice this rule" />
                <ExercisePractice v-if="exercises.length > 0" :exercises="exercises" />
                <p v-else class="text-sm text-muted-foreground">No exercises yet for this rule.</p>
            </section>
        </div>
    </PageState>
</template>
