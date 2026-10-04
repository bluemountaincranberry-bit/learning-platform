<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PageState from '../components/ui/PageState.vue';
import { useAuthStore } from '../domains/user';
import { useContent, useLexemes } from '../domains/content';
import AskAiButton from '../shared/ui/AskAiButton.vue';
import UiBadge from '../shared/ui/UiBadge.vue';
import UiButton from '../shared/ui/UiButton.vue';
import ExplainDialog from '../shared/ui/ExplainDialog.vue';
import UiCard from '../shared/ui/UiCard.vue';
import UiEmptyState from '../shared/ui/UiEmptyState.vue';
import WordListItem from '../shared/ui/WordListItem.vue';
import WordListToolbar from '../shared/ui/WordListToolbar.vue';
import type { LexemeWithLearned } from '../types';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const contentId = computed(() => String(route.params.id));
const { loading: contentLoading, error: contentError, content, loadContent } = useContent();
const {
    loading: lexemesLoading,
    error: lexemesError,
    lexemes,
    toLearn,
    learned,
    loadLexemes,
    markLearned: markLearnedAction,
    unmarkLearned: unmarkLearnedAction,
    markingId,
    startLearning: startLearningAction,
    stopLearning: stopLearningAction,
    skip: skipAction,
    unskip: unskipAction,
    startingReviewId,
    explainingId,
    fetchingExamplesId,
    explainError,
    aiUnavailable,
    bulkActionPending,
    bulkMarkLearned,
    bulkStartLearning,
    explainLexeme,
    fetchMoreExamples,
} = useLexemes();

const loading = computed(() => contentLoading.value || lexemesLoading.value);
const error = computed(() => contentError.value || lexemesError.value);
const explanationModal = ref<{
    lexemeText: string;
    translation: string | null;
    language: string | null;
    explanation: string;
    loading: boolean;
    error: string;
} | null>(null);
const explainTarget = ref<LexemeWithLearned | null>(null);

// Task 7.8: level filter, status filter, "select all", and the bulk-action
// bar now live in WordListToolbar.vue (shared with ContentDetailsPage.vue).
// This page just keeps the toolbar's filtered output and the selection set.
const filteredLexemes = ref<LexemeWithLearned[]>([]);

// Task 7.4: bulk selection, keyed by ContentLexeme.id — the same id space
// the single-item mark-learned/start-learning endpoints already use, so it
// feeds straight into the bulk endpoints without translation.
const selectedIds = ref<Set<number>>(new Set());

function toggleSelect(lexeme: LexemeWithLearned) {
    const next = new Set(selectedIds.value);
    if (next.has(lexeme.id)) next.delete(lexeme.id);
    else next.add(lexeme.id);
    selectedIds.value = next;
}

function practiceSelected(ids: number[]) {
    if (ids.length === 0) return;
    router.push({ name: 'repetitions', query: { content_id: String(contentId.value), lexeme_ids: ids.join(','), return_to: 'study' } });
}

function practiceContext(ids: number[]) {
    if (ids.length === 0) return;
    router.push({ name: 'context-practice', query: { content_id: String(contentId.value), lexeme_ids: ids.join(',') } });
}

async function markLearned(lexeme: LexemeWithLearned) {
    await markLearnedAction(lexeme);
}

async function unmarkLearned(lexeme: LexemeWithLearned) {
    await unmarkLearnedAction(lexeme);
}

async function startReview(lexeme: LexemeWithLearned) {
    await startLearningAction(lexeme);
}

async function stopReview(lexeme: LexemeWithLearned) {
    await stopLearningAction(lexeme);
}

async function skip(lexeme: LexemeWithLearned) {
    await skipAction(lexeme);
}

async function unskip(lexeme: LexemeWithLearned) {
    await unskipAction(lexeme);
}

async function explain(lexeme: LexemeWithLearned) {
    explainTarget.value = lexeme;
    explanationModal.value = {
        lexemeText: lexeme.text,
        translation: lexeme.translation ?? null,
        language: content.value?.language ?? null,
        explanation: '',
        loading: true,
        error: '',
    };
    const result = await explainLexeme(lexeme);
    if (explanationModal.value === null) return;
    if (result) {
        explanationModal.value = { ...explanationModal.value, explanation: result.explanation, loading: false };
    } else {
        explanationModal.value = { ...explanationModal.value, loading: false, error: explainError.value || 'Failed to get explanation.' };
    }
}

function retryExplanation() {
    if (explainTarget.value) void explain(explainTarget.value);
}

function closeExplanation() {
    explanationModal.value = null;
}

// Task 6.1: context for AskAiButton.
const aiContext = computed(() => ({
    type: 'content' as const,
    id: contentId.value ?? '',
    title: content.value?.title ?? '',
}));

onMounted(async () => {
    if (!authStore.isAuthenticated) {
        router.push({ name: 'login', query: { redirect: route.fullPath } });
        return;
    }
    await loadContent(contentId.value);
    if (content.value) {
        try {
            await loadLexemes(contentId.value);
            const initialIds = String(route.query.lexeme_ids ?? '')
                .split(',')
                .map((id) => Number(id))
                .filter((id) => Number.isInteger(id) && id > 0);
            if (initialIds.length > 0) {
                const availableIds = new Set(lexemes.value.map((lexeme) => lexeme.id));
                selectedIds.value = new Set(initialIds.filter((id) => availableIds.has(id)));
            }
        } catch (e: unknown) {
            const status = (e as { response?: { status?: number } })?.response?.status;
            if (status === 401) {
                router.push({ name: 'login', query: { redirect: route.fullPath } });
            }
        }
    }
});
</script>

<template>
    <PageState :loading="loading" :error="error">
        <div class="space-y-6">
            <UiCard class="space-y-4">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="space-y-3">
                        <div class="flex flex-wrap gap-2">
                            <UiBadge tone="primary">study</UiBadge>
                            <UiBadge tone="neutral">{{ content?.language }}</UiBadge>
                            <UiBadge v-if="content?.level" tone="neutral">{{ content?.level }}</UiBadge>
                        </div>
                        <div>
                            <h2 class="text-2xl font-semibold text-fg">{{ content?.title }}</h2>
                            <p class="mt-2 text-sm leading-6 text-muted-foreground">
                                Choose what enters the learning flow. Learned and to-learn are visible together so the state stays obvious.
                            </p>
                        </div>
                    </div>

                    <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap">
                        <UiButton class="w-full sm:w-auto" variant="secondary" size="sm" @click="router.push({ name: 'catalog.details', params: { id: contentId } })">Back to content</UiButton>
                        <AskAiButton v-if="content" class="w-full sm:w-auto" :context="aiContext" label="Explain with AI" size="sm" />
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 text-sm text-muted-foreground">
                    <span>{{ toLearn.length }} to learn</span>
                    <span>·</span>
                    <span>{{ learned.length }} already learned</span>
                    <span>·</span>
                    <span>{{ toLearn.length + learned.length }} total</span>
                </div>
                <p v-if="explainError" class="text-sm text-warning" role="alert">{{ explainError }}</p>
            </UiCard>

            <!-- VIK-38: edge-to-edge on phones (bleeds through main's px-4), card from sm up. -->
            <section class="-mx-4 sm:mx-0 sm:rounded-xl sm:border sm:border-border sm:bg-card sm:p-5">
                <WordListToolbar
                    :lexemes="lexemes"
                    v-model:selected-ids="selectedIds"
                    :bulk-mark-learned="bulkMarkLearned"
                    :bulk-start-learning="bulkStartLearning"
                    :bulk-action-pending="bulkActionPending"
                    @update:filtered="filteredLexemes = $event"
                    @practice-selected="practiceSelected"
                    @practice-context="practiceContext"
                >
                    <div v-if="filteredLexemes.length > 0" class="mt-2 divide-y divide-border border-y border-border sm:overflow-hidden sm:rounded-lg sm:border">
                        <WordListItem
                            v-for="lexeme in filteredLexemes"
                            :key="lexeme.id"
                            :lexeme="lexeme"
                            :language="content?.language"
                            :marking="markingId === lexeme.id"
                            :starting-review="startingReviewId === lexeme.id"
                            :explaining="explainingId === lexeme.id"
                            :fetching-examples="fetchingExamplesId === lexeme.id"
                            :ai-unavailable="aiUnavailable"
                            selectable
                            :selected="selectedIds.has(lexeme.id)"
                            @mark-learned="markLearned"
                            @unmark-learned="unmarkLearned"
                            @start-review="startReview"
                            @stop-review="stopReview"
                            @explain="explain"
                            @toggle-select="toggleSelect"
                            @skip="skip"
                            @unskip="unskip"
                            @more-examples="fetchMoreExamples"
                        />
                    </div>
                    <UiEmptyState
                        v-else
                        class="mx-4 mt-3 sm:mx-0"
                        :title="lexemes.length === 0 ? 'Nothing to learn here yet' : 'No words match'"
                        :description="lexemes.length === 0 ? 'Either all words are already learned or the content has no extracted units.' : 'Try a different filter or search term.'"
                    />
                </WordListToolbar>
            </section>

            <ExplainDialog
                v-if="explanationModal"
                :open="true"
                :lexeme-text="explanationModal.lexemeText"
                :translation="explanationModal.translation"
                :language="explanationModal.language"
                :explanation="explanationModal.explanation"
                :loading="explanationModal.loading"
                :error="explanationModal.error"
                @close="closeExplanation"
                @retry="retryExplanation"
            />
        </div>
    </PageState>
</template>
