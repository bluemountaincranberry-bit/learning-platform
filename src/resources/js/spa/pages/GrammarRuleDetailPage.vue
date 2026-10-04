<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Check, Plus, Undo2 } from 'lucide-vue-next';
import PageState from '../components/ui/PageState.vue';
import AskAiButton from '../shared/ui/AskAiButton.vue';
import UiBadge from '../shared/ui/UiBadge.vue';
import UiButton from '../shared/ui/UiButton.vue';
import UiCard from '../shared/ui/UiCard.vue';
import UiSectionHeader from '../shared/ui/UiSectionHeader.vue';
import GrammarRuleExamples from '../widgets/grammar/GrammarRuleExamples.vue';
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

// Task 6.1: context for AskAiButton.
const aiContext = computed(() => ({
    type: 'grammar' as const,
    id: rule.value?.id ?? '',
    title: rule.value?.title ?? '',
}));

onMounted(loadRule);
</script>

<template>
    <PageState :loading="loading" :error="error">
        <template #retry>
            <UiButton variant="secondary" size="sm" @click="loadRule">Try again</UiButton>
        </template>
        <div class="space-y-6">
            <UiCard class="space-y-4">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="space-y-3">
                        <div class="flex flex-wrap gap-2">
                            <UiBadge tone="primary">grammar</UiBadge>
                            <UiBadge tone="neutral">{{ rule?.language }}</UiBadge>
                            <UiBadge v-if="rule?.level" tone="neutral">{{ rule?.level }}</UiBadge>
                            <UiBadge v-if="rule?.topic" tone="neutral">{{ rule?.topic.name }}</UiBadge>
                        </div>
                        <div>
                            <h2 class="text-2xl font-semibold text-fg">{{ rule?.title }}</h2>
                            <p v-if="rule?.summary" class="mt-2 text-sm leading-6 text-muted">{{ rule?.summary }}</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <AskAiButton v-if="rule" :context="aiContext" label="Discuss with AI" />
                        <UiButton variant="secondary" @click="router.push({ name: 'grammar' })">Back to grammar</UiButton>
                    </div>
                </div>
                <div v-if="authStore.isAuthenticated && rule" class="flex flex-wrap items-center gap-2 border-t border-border pt-4">
                    <template v-if="!rule.learned">
                        <UiBadge v-if="rule.in_my_list" tone="primary">In your grammar list</UiBadge>
                        <UiButton v-else variant="secondary" size="sm" :disabled="progressBusy" @click="addToMyList">
                            <Plus :size="14" /> Add to my grammar
                        </UiButton>
                        <UiButton variant="primary" size="sm" :disabled="progressBusy" @click="markLearned">
                            <Check :size="14" /> Mark as learned
                        </UiButton>
                        <UiButton v-if="rule.in_my_list" variant="ghost" size="sm" :disabled="progressBusy" @click="removeFromMyList">
                            <Undo2 :size="14" /> Remove
                        </UiButton>
                    </template>
                    <template v-else>
                        <UiBadge tone="success">Learned</UiBadge>
                        <UiButton variant="ghost" size="sm" :disabled="progressBusy" @click="removeFromMyList">
                            <Undo2 :size="14" /> Remove
                        </UiButton>
                    </template>
                </div>
            </UiCard>

            <UiCard class="space-y-4">
                <UiSectionHeader title="Explanation" />
                <MarkdownContent :content="rule?.body" />
            </UiCard>

            <UiCard v-if="rule" class="space-y-4">
                <UiSectionHeader title="Examples" />
                <div class="space-y-3">
                    <GrammarRuleExamples
                        :rule-id="rule.id"
                        :initial-examples="rule.examples ?? []"
                        :language="rule.language"
                        :authenticated="authStore.isAuthenticated"
                    />
                </div>
            </UiCard>

            <UiCard class="space-y-4">
                <UiSectionHeader title="Exercises" subtitle="Practice this rule" />
                <ExercisePractice v-if="exercises.length > 0" :exercises="exercises" />
                <p v-else class="text-sm text-muted-foreground">No exercises yet for this rule.</p>
            </UiCard>
        </div>
    </PageState>
</template>
