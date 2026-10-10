<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { Check, Dumbbell, Plus } from 'lucide-vue-next';
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
import WordRow from '../shared/ui/WordRow.vue';
import PracticeQueueToggle from '../shared/ui/PracticeQueueToggle.vue';
import UiInput from '../shared/ui/UiInput.vue';
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
const addLemma = ref('');
const addLanguage = ref('en');
const addPending = ref(false);
const addMessage = ref('');
const addError = ref('');

const activeStatus = ref<MyWordStatus>('in_learning');
const filterLevel = ref('all');
const search = ref('');
const selectedIds = ref<number[]>([]);

const startingReviewId = ref<number | null>(null);
const explainingId = ref<number | null>(null);
const bulkPending = ref(false);
const confirmingMyWordsKnown = ref(false);
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
const selectedCanonicalLexemeIds = computed(() => selectedRows.value.map((row) => row.lexeme_id));
const selectedNotInPractice = computed(() => selectedRows.value.filter((row) => !row.in_review));
const selectedInPractice = computed(() => selectedRows.value.filter((row) => row.in_review));
const selectedUnknown = computed(() => selectedRows.value.filter((row) => row.status !== 'known'));
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
    selectedIds.value = [];
    meta.value = { ...meta.value, current_page: page };
    fetchWords();
}

function setPerPage(perPage: number) {
    selectedIds.value = [];
    meta.value = { ...meta.value, per_page: perPage, current_page: 1 };
    fetchWords();
}

function toggleSelected(row: MyWordItem) {
    confirmingMyWordsKnown.value = false;
    selectedIds.value = selectedIds.value.includes(row.id)
        ? selectedIds.value.filter((id) => id !== row.id)
        : [...selectedIds.value, row.id];
}

function toggleAllVisible() {
    confirmingMyWordsKnown.value = false;
    selectedIds.value = allVisibleSelected.value ? [] : items.value.map((row) => row.id);
}

async function startReview(row: MyWordItem) {
    if (row.in_review) return;
    startingReviewId.value = row.id;
    try {
        await myWordsApi.startLearning(row.lexeme_id);
        await fetchWords();
    } catch {
        error.value = 'Failed to add to learning.';
    } finally {
        startingReviewId.value = null;
    }
}

async function stopReview(row: MyWordItem) {
    startingReviewId.value = row.id;
    try {
        await myWordsApi.stopLearning(row.lexeme_id);
        await fetchWords();
    } catch {
        error.value = 'Failed to stop learning.';
    } finally {
        startingReviewId.value = null;
    }
}

async function addPersonalWord() {
    const lemma = addLemma.value.trim();
    const language = addLanguage.value.trim();
    if (lemma === '' || language === '') return;
    addPending.value = true;
    addError.value = '';
    addMessage.value = '';
    try {
        await myWordsApi.addWord({ lemma, language });
        addLemma.value = '';
        activeStatus.value = 'in_learning';
        addMessage.value = `Added “${lemma}” to learning.`;
        await fetchWords();
    } catch (e: unknown) {
        const err = e as { response?: { data?: { message?: string; errors?: { lemma?: string[]; language?: string[] } } } };
        addError.value = err.response?.data?.errors?.lemma?.[0]
            ?? err.response?.data?.errors?.language?.[0]
            ?? err.response?.data?.message
            ?? 'Could not add this word.';
    } finally {
        addPending.value = false;
    }
}

function practiceSelected() {
    if (selectedCanonicalLexemeIds.value.length === 0) return;
    router.push({ name: 'repetitions', query: { lexeme_ids: selectedCanonicalLexemeIds.value.join(','), return_to: 'my-words' } });
}

async function bulkQueueAction(rows: MyWordItem[], action: 'start' | 'stop' | 'known') {
    if (rows.length === 0) return;
    bulkPending.value = true;
    error.value = '';
    const settled = await Promise.allSettled(rows.map((row) => action === 'start'
        ? myWordsApi.startLearning(row.lexeme_id)
        : action === 'stop' ? myWordsApi.stopLearning(row.lexeme_id) : myWordsApi.markKnown(row.lexeme_id)));
    const failed = settled.filter((result) => result.status === 'rejected').length;
    await fetchWords();
        if (failed === 0) selectedIds.value = [];
    else error.value = `${failed} selected word${failed === 1 ? '' : 's'} could not be updated. Review the selection and try again.`;
        confirmingMyWordsKnown.value = false;
    bulkPending.value = false;
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
    confirmingMyWordsKnown.value = false;
    fetchWords();
});
</script>

<template>
    <div class="space-y-6">
        <form class="flex flex-col gap-3 rounded-lg border border-border bg-surface p-4 sm:flex-row sm:items-end" @submit.prevent="addPersonalWord">
            <label class="min-w-0 flex-1 space-y-1.5 text-sm font-medium text-fg">
                Add a word to practice
                <UiInput v-model="addLemma" aria-label="Word" placeholder="A word or phrase" maxlength="255" required />
            </label>
            <label class="w-full space-y-1.5 text-sm font-medium text-fg sm:w-28">
                Language
                <UiInput v-model="addLanguage" aria-label="Language" placeholder="en" maxlength="8" required />
            </label>
            <UiButton variant="primary" :disabled="addPending || addLemma.trim() === '' || addLanguage.trim() === ''">
                <Plus :size="14" /> {{ addPending ? 'Adding…' : 'Add word' }}
            </UiButton>
        </form>
        <p v-if="addMessage" class="text-sm text-success" role="status">{{ addMessage }}</p>
        <p v-if="addError" class="text-sm text-warning" role="alert">{{ addError }}</p>
        <MyWordsSettingsPanel
            v-model:status="activeStatus"
            v-model:search="search"
            v-model:level="filterLevel"
        />

        <UiCard v-if="selectedIds.length > 0 && confirmingMyWordsKnown" class="flex flex-wrap items-center justify-between gap-3 bg-warning-bg">
            <span class="text-sm text-warning-fg">Mark {{ selectedUnknown.length }} selected {{ selectedUnknown.length === 1 ? 'word' : 'words' }} as known?</span>
            <div class="flex gap-2">
                <UiButton variant="success" size="sm" :disabled="bulkPending || selectedUnknown.length === 0" @click="bulkQueueAction(selectedUnknown, 'known')">Confirm</UiButton>
                <UiButton variant="ghost" size="sm" :disabled="bulkPending" @click="confirmingMyWordsKnown = false">Cancel</UiButton>
            </div>
        </UiCard>
        <UiCard v-else-if="selectedIds.length > 0" class="space-y-2 p-3">
            <span class="text-sm font-medium text-fg">{{ selectedIds.length }} selected</span>
            <div class="grid grid-cols-4 gap-1.5">
                <UiButton variant="primary" size="touch" class="min-w-0 gap-0.5 px-0.5 text-xs" :disabled="bulkPending || selectedCanonicalLexemeIds.length === 0" aria-label="Practice selected words" @click="practiceSelected">
                    <Dumbbell :size="14" class="hidden shrink-0 sm:block" /> <span class="truncate">Practice</span>
                </UiButton>
                <UiButton variant="secondary" size="touch" class="min-w-0 gap-0.5 px-0.5 text-xs" :disabled="bulkPending || selectedNotInPractice.length === 0" aria-label="Add selected words to practice" @click="bulkQueueAction(selectedNotInPractice, 'start')">
                    <Plus :size="14" class="hidden shrink-0 sm:block" /> <span class="truncate">Add</span>
                </UiButton>
                <UiButton variant="secondary" size="touch" class="min-w-0 gap-0.5 px-0.5 text-xs" :disabled="bulkPending || selectedInPractice.length === 0" aria-label="Remove selected words from practice" @click="bulkQueueAction(selectedInPractice, 'stop')">
                    <Minus :size="14" class="hidden shrink-0 sm:block" /> <span class="truncate">Remove</span>
                </UiButton>
                <UiButton variant="success" size="touch" class="min-w-0 gap-0.5 px-0.5 text-xs" :disabled="bulkPending || selectedUnknown.length === 0" aria-label="Mark selected words as known" @click="confirmingMyWordsKnown = true">
                    <Check :size="14" class="hidden shrink-0 sm:block" /> <span class="truncate">Known</span>
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

                    <WordRow v-for="row in items" :key="row.id" :text="row.lexeme" :translation="row.translation" :level="row.level" :lexeme-id="row.lexeme_id" :language="row.language" :examples="row.examples" :example="row.example" selectable :selected="selectedIds.includes(row.id)" @toggle-select="toggleSelected(row)">
                        <template #row-actions>
                            <UiBadge :tone="statusTone(row)">{{ statusLabel(row) }}</UiBadge>
                            <PracticeQueueToggle :word="row.lexeme" :queued="row.in_review" :disabled="startingReviewId === row.id" @toggle="row.in_review ? stopReview(row) : startReview(row)" />
                        </template>
                            <div
                                v-for="group in associationGroupsOf(row)"
                                :key="group.type"
                                class="flex flex-wrap items-center gap-1.5"
                            >
                                <span class="text-xs text-muted-foreground">{{ group.label }}:</span>
                                <UiBadge v-for="item in group.items" :key="item" :tone="group.tone">{{ item }}</UiBadge>
                            </div>

                        <template #source>
                            <div class="flex flex-wrap items-center gap-2 pt-1">
                                <span v-if="row.learned_at" class="text-xs text-muted-foreground">Known since {{ formatDate(row.learned_at) }}</span>
                                <template v-for="context in row.contexts.slice(0, 3)" :key="context.content_lexeme_id">
                                    <RouterLink
                                        v-if="context.content_id !== null"
                                        :to="{ name: 'catalog.details', params: { id: context.content_id } }"
                                        class="inline-flex min-h-11 items-center text-xs text-primary underline"
                                    >
                                        {{ context.content_title || 'Content' }}
                                    </RouterLink>
                                    <span v-else class="text-xs text-muted-foreground">{{ context.content_title || 'Content' }}</span>
                                </template>
                                <span v-if="row.contexts.length > 3" class="text-xs text-muted-foreground">+{{ row.contexts.length - 3 }} more</span>
                                <span v-if="!primaryContext(row)" class="text-xs text-muted-foreground">No content context</span>
                            </div>
                        </template>
                        <template #actions>
                            <UiButton
                                v-if="!aiUnavailable"
                                variant="ghost"
                                size="touch"
                                :disabled="explainingId === row.id || row.content_lexeme_id === null"
                                title="Get a short AI explanation of this word, with an example"
                                @click="explain(row)"
                            >
                                {{ explainingId === row.id ? '...' : 'Explain' }}
                            </UiButton>
                        </template>
                    </WordRow>
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
