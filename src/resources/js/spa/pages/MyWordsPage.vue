<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { Check, Dumbbell, Plus, Undo2 } from 'lucide-vue-next';
import PageState from '../components/ui/PageState.vue';
import { useAuthStore } from '../domains/user';
import { contentApi } from '../domains/content';
import { myWordsApi, type MyWordItem, type MyWordsParams, type MyWordStatus } from '../domains/learning';
import MyWordsSettingsPanel from './my-words/MyWordsSettingsPanel.vue';
import UiBadge from '../shared/ui/UiBadge.vue';
import UiButton from '../shared/ui/UiButton.vue';
import ExplainDialog from '../shared/ui/ExplainDialog.vue';
import UiCard from '../shared/ui/UiCard.vue';
import UiEmptyState from '../shared/ui/UiEmptyState.vue';
import SelectField from '../shared/ui/SelectField.vue';
import SpeakButton from '../shared/ui/SpeakButton.vue';
import WordExamples from '../shared/ui/WordExamples.vue';
import { groupAssociationsByType } from '../shared/lexemeAssociations';

const router = useRouter();
const authStore = useAuthStore();

const PER_PAGE_OPTIONS = [10, 15, 25, 50];

const items = ref<MyWordItem[]>([]);
const meta = ref<{ current_page: number; per_page: number; total: number; last_page?: number }>({
    current_page: 1,
    per_page: 15,
    total: 0,
});
const loading = ref(true);
const error = ref('');

const activeStatus = ref<MyWordStatus>('in_learning');
const filterLevel = ref('all');
const search = ref('');
const selectedIds = ref<number[]>([]);

const startingReviewId = ref<number | null>(null);
const markingKnownId = ref<number | null>(null);
const unmarkingId = ref<number | null>(null);
const explainingId = ref<number | null>(null);
const bulkPending = ref(false);
const explainError = ref('');
const aiUnavailable = ref(false);
const explanationModal = ref<{
    lexemeText: string;
    translation: string | null;
    language: string | null;
    explanation: string;
    loading: boolean;
    regenerating: boolean;
    error: string;
} | null>(null);
const explainTarget = ref<MyWordItem | null>(null);

const totalPages = computed(() => meta.value.last_page ?? Math.max(1, Math.ceil(meta.value.total / meta.value.per_page)));
const canPrev = computed(() => meta.value.current_page > 1);
const canNext = computed(() => meta.value.current_page < totalPages.value);
const selectedRows = computed(() => items.value.filter((row) => selectedIds.value.includes(row.id)));
const selectedContentLexemeIds = computed(() => selectedRows.value.map((row) => row.content_lexeme_id).filter((id): id is number => id !== null));
const allVisibleSelected = computed(() => items.value.length > 0 && items.value.every((row) => selectedIds.value.includes(row.id)));

const queryParams = computed<MyWordsParams>(() => {
    const q: MyWordsParams = {
        status: activeStatus.value,
        page: meta.value.current_page,
        per_page: meta.value.per_page,
    };
    if (filterLevel.value !== 'all') q.level = filterLevel.value;
    if (search.value.trim() !== '') q.search = search.value.trim();
    return q;
});

function associationGroupsOf(row: MyWordItem) {
    return groupAssociationsByType(row.associations);
}

function primaryContext(row: MyWordItem) {
    return row.contexts[0] ?? null;
}

function statusTone(row: MyWordItem): 'primary' | 'success' | 'neutral' {
    if (row.status === 'in_learning') return 'primary';
    if (row.status === 'known') return 'success';
    return 'neutral';
}

function statusLabel(row: MyWordItem): string {
    if (row.status === 'in_learning') return 'In learning';
    if (row.status === 'known') return 'Known';
    return 'New';
}

async function fetchWords() {
    loading.value = true;
    error.value = '';
    try {
        const data = await myWordsApi.getList(queryParams.value);
        items.value = data.data ?? [];
        meta.value = data.meta ?? meta.value;
        selectedIds.value = selectedIds.value.filter((id) => items.value.some((row) => row.id === id));
    } catch (e: unknown) {
        const err = e as { response?: { status?: number; data?: { message?: string } } };
        if (err.response?.status === 401) {
            router.push({ name: 'login', query: { redirect: '/my-words' } });
            return;
        }
        error.value = err.response?.data?.message ?? 'Failed to load words.';
    } finally {
        loading.value = false;
    }
}

function goToPage(page: number) {
    if (page < 1 || page > totalPages.value) return;
    meta.value = { ...meta.value, current_page: page };
    fetchWords();
}

function setPerPage(perPage: number) {
    meta.value = { ...meta.value, per_page: perPage, current_page: 1 };
    fetchWords();
}

function toggleSelected(row: MyWordItem) {
    selectedIds.value = selectedIds.value.includes(row.id)
        ? selectedIds.value.filter((id) => id !== row.id)
        : [...selectedIds.value, row.id];
}

function toggleAllVisible() {
    selectedIds.value = allVisibleSelected.value ? [] : items.value.map((row) => row.id);
}

async function startReview(row: MyWordItem) {
    if (row.in_review || row.content_lexeme_id === null) return;
    startingReviewId.value = row.id;
    try {
        await contentApi.startLexemeLearning(row.content_lexeme_id);
        await fetchWords();
    } catch {
        error.value = 'Failed to add to learning.';
    } finally {
        startingReviewId.value = null;
    }
}

async function markKnown(row: MyWordItem) {
    if (row.status === 'known' || row.content_lexeme_id === null) return;
    markingKnownId.value = row.id;
    try {
        await contentApi.markLexemeLearned(row.content_lexeme_id);
        await fetchWords();
    } catch {
        error.value = 'Failed to mark as known.';
    } finally {
        markingKnownId.value = null;
    }
}

async function unmarkKnown(row: MyWordItem) {
    if (row.content_lexeme_id === null) return;
    unmarkingId.value = row.id;
    try {
        await contentApi.unmarkLexemeLearned(row.content_lexeme_id);
        await fetchWords();
    } catch {
        error.value = 'Failed to remove from known words.';
    } finally {
        unmarkingId.value = null;
    }
}

function practiceSelected() {
    if (selectedContentLexemeIds.value.length === 0) return;
    router.push({ name: 'repetitions', query: { lexeme_ids: selectedContentLexemeIds.value.join(','), return_to: 'my-words' } });
}

async function addSelectedToLearning() {
    if (selectedContentLexemeIds.value.length === 0) return;
    bulkPending.value = true;
    try {
        await contentApi.bulkStartLexemesLearning(selectedContentLexemeIds.value);
        selectedIds.value = [];
        await fetchWords();
    } catch {
        error.value = 'Failed to add selected words to learning.';
    } finally {
        bulkPending.value = false;
    }
}

async function markSelectedKnown() {
    if (selectedContentLexemeIds.value.length === 0) return;
    bulkPending.value = true;
    try {
        await contentApi.bulkMarkLexemesLearned(selectedContentLexemeIds.value);
        selectedIds.value = [];
        await fetchWords();
    } catch {
        error.value = 'Failed to mark selected words as known.';
    } finally {
        bulkPending.value = false;
    }
}

async function explain(row: MyWordItem, refresh = false) {
    if (row.content_lexeme_id === null) return;
    explainTarget.value = row;
    explainingId.value = row.id;
    if (!refresh) {
        explanationModal.value = {
            lexemeText: row.lexeme,
            translation: row.translation ?? null,
            language: row.language ?? null,
            explanation: '',
            loading: true,
            regenerating: false,
            error: '',
        };
    } else if (explanationModal.value) {
        explanationModal.value = { ...explanationModal.value, regenerating: true, error: '' };
    }
    explainError.value = '';
    try {
        const data = await contentApi.explainLexeme(row.content_lexeme_id, refresh);
        if (explanationModal.value !== null) {
            explanationModal.value = { ...explanationModal.value, explanation: data.explanation, loading: false, regenerating: false };
        }
    } catch (e: unknown) {
        const err = e as { response?: { status?: number; data?: { message?: string } } };
        if (err.response?.status === 503 || err.response?.status === 403) {
            aiUnavailable.value = true;
            explainError.value = 'AI temporarily unavailable.';
        } else {
            explainError.value = err.response?.data?.message ?? 'Failed to get explanation.';
        }
        if (explanationModal.value !== null) {
            explanationModal.value = { ...explanationModal.value, loading: false, regenerating: false, error: explainError.value };
        }
    } finally {
        explainingId.value = null;
    }
}

function retryExplanation() {
    if (explainTarget.value) void explain(explainTarget.value);
}

function regenerateExplanation() {
    if (explainTarget.value) void explain(explainTarget.value, true);
}

function closeExplanation() {
    explanationModal.value = null;
}

function formatDate(iso: string | null) {
    if (iso === null) return '';
    try {
        return new Date(iso).toLocaleDateString();
    } catch {
        return iso;
    }
}

onMounted(async () => {
    if (!authStore.isAuthenticated) {
        router.push({ name: 'login', query: { redirect: '/my-words' } });
        return;
    }
    await fetchWords();
});

watch([activeStatus, filterLevel, search], () => {
    if (meta.value.current_page !== 1) {
        meta.value = { ...meta.value, current_page: 1 };
    }
    selectedIds.value = [];
    fetchWords();
});
</script>

<template>
    <div class="space-y-6">
        <MyWordsSettingsPanel
            v-model:status="activeStatus"
            v-model:search="search"
            v-model:level="filterLevel"
        />

        <UiCard v-if="selectedIds.length > 0" class="flex flex-wrap items-center justify-between gap-3">
            <span class="text-sm font-medium text-fg">{{ selectedIds.length }} selected</span>
            <div class="flex flex-wrap gap-2">
                <UiButton variant="primary" size="sm" :disabled="bulkPending || selectedContentLexemeIds.length === 0" @click="practiceSelected">
                    <Dumbbell :size="14" /> Practice selected
                </UiButton>
                <UiButton variant="secondary" size="sm" :disabled="bulkPending || selectedContentLexemeIds.length === 0" @click="addSelectedToLearning">
                    <Plus :size="14" /> Add to learning
                </UiButton>
                <UiButton variant="secondary" size="sm" :disabled="bulkPending || selectedContentLexemeIds.length === 0" @click="markSelectedKnown">
                    <Check :size="14" /> Mark known
                </UiButton>
            </div>
        </UiCard>

        <p v-if="explainError" class="text-sm text-warning" role="alert">{{ explainError }}</p>

        <PageState :loading="loading" :error="error">
            <template #retry>
                <UiButton variant="secondary" size="sm" @click="fetchWords">Try again</UiButton>
            </template>
            <template v-if="items.length === 0 && !loading">
                <UiEmptyState title="No words found" description="Change filters or open a content item to add new words.">
                    <UiButton variant="primary" @click="router.push({ name: 'catalog' })">Go to catalog</UiButton>
                </UiEmptyState>
            </template>

            <template v-else>
                <UiCard class="space-y-4">
                    <div class="flex items-center justify-between gap-3 border-b border-border pb-3">
                        <label class="inline-flex items-center gap-2 text-sm text-muted-foreground">
                            <input
                                type="checkbox"
                                class="h-4 w-4 rounded border-border bg-surface"
                                :checked="allVisibleSelected"
                                @change="toggleAllVisible"
                            />
                            Select visible
                        </label>
                        <span class="text-sm text-muted-foreground">{{ meta.total }} total</span>
                    </div>

                    <div
                        v-for="row in items"
                        :key="row.id"
                        class="grid gap-3 rounded-spa border border-border bg-surface-alt/45 p-4 transition-colors hover:border-primary/50 lg:grid-cols-[auto_1fr_auto]"
                    >
                        <label class="pt-1">
                            <input
                                type="checkbox"
                                class="h-4 w-4 rounded border-border bg-surface"
                                :checked="selectedIds.includes(row.id)"
                                @change="toggleSelected(row)"
                            />
                        </label>

                        <div class="min-w-0 space-y-1.5">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <SpeakButton :text="row.lexeme" :language="row.language" />
                                <RouterLink
                                    :to="{ name: 'word.details', params: { id: row.lexeme_id } }"
                                    class="font-medium text-fg hover:text-primary hover:underline"
                                >
                                    {{ row.lexeme }}
                                </RouterLink>
                                <UiBadge :tone="statusTone(row)">{{ statusLabel(row) }}</UiBadge>
                                <UiBadge tone="neutral">{{ row.level || 'n/a' }}</UiBadge>
                            </div>

                            <div v-if="row.translation" class="text-sm font-medium text-fg-secondary">{{ row.translation }}</div>

                            <div
                                v-for="group in associationGroupsOf(row)"
                                :key="group.type"
                                class="flex flex-wrap items-center gap-1.5"
                            >
                                <span class="text-xs text-muted-foreground">{{ group.label }}:</span>
                                <UiBadge v-for="item in group.items" :key="item" :tone="group.tone">{{ item }}</UiBadge>
                            </div>

                            <WordExamples :examples="row.examples" :fallback-example="row.example" :language="row.language" />

                            <div class="flex flex-wrap items-center gap-2 pt-1">
                                <span v-if="row.learned_at" class="text-xs text-muted-foreground">Known since {{ formatDate(row.learned_at) }}</span>
                                <template v-for="context in row.contexts.slice(0, 3)" :key="context.content_lexeme_id">
                                    <RouterLink
                                        v-if="context.content_id !== null"
                                        :to="{ name: 'catalog.details', params: { id: context.content_id } }"
                                        class="text-xs text-primary underline"
                                    >
                                        {{ context.content_title || 'Content' }}
                                    </RouterLink>
                                    <span v-else class="text-xs text-muted-foreground">{{ context.content_title || 'Content' }}</span>
                                </template>
                                <span v-if="row.contexts.length > 3" class="text-xs text-muted-foreground">+{{ row.contexts.length - 3 }} more</span>
                                <span v-if="!primaryContext(row)" class="text-xs text-muted-foreground">No content context</span>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-start gap-2 lg:justify-end">
                            <UiButton
                                v-if="!aiUnavailable"
                                variant="ghost"
                                size="sm"
                                :disabled="explainingId === row.id || row.content_lexeme_id === null"
                                title="Get a short AI explanation of this word, with an example"
                                @click="explain(row)"
                            >
                                {{ explainingId === row.id ? '...' : 'Explain' }}
                            </UiButton>
                            <UiButton
                                v-if="!row.in_review"
                                variant="secondary"
                                size="sm"
                                :disabled="startingReviewId === row.id || row.content_lexeme_id === null"
                                title="Add to spaced-repetition learning"
                                @click="startReview(row)"
                            >
                                <Plus :size="14" /> Add
                            </UiButton>
                            <UiButton
                                v-if="row.status !== 'known'"
                                variant="secondary"
                                size="sm"
                                :disabled="markingKnownId === row.id || row.content_lexeme_id === null"
                                title="Mark this word as already known"
                                @click="markKnown(row)"
                            >
                                <Check :size="14" /> Known
                            </UiButton>
                            <UiButton
                                v-if="row.status === 'known'"
                                variant="ghost"
                                size="sm"
                                :disabled="unmarkingId === row.id || row.content_lexeme_id === null"
                                title="Remove from known words"
                                @click="unmarkKnown(row)"
                            >
                                <Undo2 :size="14" /> Unlearn
                            </UiButton>
                        </div>
                    </div>
                </UiCard>

                <div class="flex flex-wrap items-center gap-4">
                    <span class="text-sm text-muted-foreground">Page {{ meta.current_page }} of {{ totalPages }} ({{ meta.total }} total)</span>
                    <div class="flex gap-2">
                        <UiButton variant="secondary" :disabled="!canPrev" @click="goToPage(meta.current_page - 1)">Prev</UiButton>
                        <UiButton variant="secondary" :disabled="!canNext" @click="goToPage(meta.current_page + 1)">Next</UiButton>
                    </div>
                    <div class="w-40">
                        <SelectField
                            :model-value="String(meta.per_page)"
                            label="Per page"
                            placeholder="Rows"
                            :options="PER_PAGE_OPTIONS.map((n) => ({ value: String(n), label: `${n}` }))"
                            @update:modelValue="(value) => setPerPage(Number(value))"
                        />
                    </div>
                </div>
            </template>
        </PageState>

        <ExplainDialog
            v-if="explanationModal"
            :open="true"
            :lexeme-text="explanationModal.lexemeText"
            :translation="explanationModal.translation"
            :language="explanationModal.language"
            :explanation="explanationModal.explanation"
            :loading="explanationModal.loading"
            :error="explanationModal.error"
            show-regenerate
            :regenerating="explanationModal.regenerating"
            @close="closeExplanation"
            @retry="retryExplanation"
            @regenerate="regenerateExplanation"
        />
    </div>
</template>
