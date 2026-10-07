<script setup lang="ts">
import { ref, shallowRef, onMounted, onBeforeUnmount, computed, nextTick } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { RouterLink } from 'vue-router';
import PageState from '../components/ui/PageState.vue';
import UiButton from '../shared/ui/UiButton.vue';
import UiSectionHeader from '../shared/ui/UiSectionHeader.vue';
import GrammarRuleExamples from '../widgets/grammar/GrammarRuleExamples.vue';
import GrammarRuleHeader from '../widgets/grammar/GrammarRuleHeader.vue';
import MarkdownContent from '../shared/ui/MarkdownContent.vue';
import GrammarPracticeCard from '../widgets/grammar/GrammarPracticeCard.vue';
import { grammarApi } from '../domains/content';
import { useAuthStore } from '../domains/user';
import type { GrammarRule } from '../types';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const ruleId = computed(() => route.params.id as string);

const loading = ref(true);
const error = ref('');
const rule = ref<GrammarRule | null>(null);
const practiceCard = ref<InstanceType<typeof GrammarPracticeCard> | null>(null);
const practiceBlock = shallowRef<HTMLElement | null>(null);
// Sticky "Practice" on phones while reading; hidden once the block is on screen.
const practiceBlockVisible = ref(false);
let practiceObserver: IntersectionObserver | null = null;
const progressBusy = ref(false);

async function loadRule(): Promise<void> {
    loading.value = true;
    error.value = '';
    try {
        rule.value = (await grammarApi.getOne(ruleId.value)).rule;
    } catch {
        error.value = 'Grammar rule not found or unavailable.';
    } finally {
        loading.value = false;
    }
    await nextTick();
    observePracticeBlock();
}

function observePracticeBlock(): void {
    practiceObserver?.disconnect();
    if (!practiceBlock.value || typeof IntersectionObserver === 'undefined') return;
    practiceObserver = new IntersectionObserver(([entry]) => {
        practiceBlockVisible.value = entry.isIntersecting;
    });
    practiceObserver.observe(practiceBlock.value);
}

onBeforeUnmount(() => practiceObserver?.disconnect());

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
                <p v-if="rule.is_personal && rule.source_lesson" class="text-sm text-muted-foreground" data-test="personal-rule-source">
                    From lesson:
                    <RouterLink v-if="rule.source_lesson.id" :to="{ name: 'lesson.details', params: { id: rule.source_lesson.id } }" class="font-medium text-primary underline">
                        {{ rule.source_lesson.title ?? 'View lesson' }}
                    </RouterLink>
                    <span v-else>{{ rule.source_lesson.title ?? 'Lesson no longer available' }}</span>
                </p>
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


            <div id="rule-exercises" ref="practiceBlock" class="scroll-mb-24">
                <GrammarPracticeCard v-if="rule" ref="practiceCard" :rule-id="rule.id" :authenticated="authStore.isAuthenticated" />
            </div>

            <div
                v-if="rule && authStore.isAuthenticated && !practiceBlockVisible"
                class="fixed inset-x-4 bottom-[calc(4.5rem+env(safe-area-inset-bottom))] z-20 sm:hidden"
                data-test="sticky-practice"
            >
                <UiButton variant="primary" size="lg" class="w-full shadow-lg" @click="practiceCard?.practice()">Practice</UiButton>
            </div>
        </div>
    </PageState>
</template>
