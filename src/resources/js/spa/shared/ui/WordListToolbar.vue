<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import UiButton from './UiButton.vue';
import SelectField from './SelectField.vue';
import type { LexemeWithLearned, BulkLexemeActionResponse } from '../../types';

/**
 * Task 7.8: extracted from StudyPage.vue so the level filter + status filter
 * + bulk-selection + bulk-action-bar UI (tasks 7.3-7.6) is available on any
 * page that lists a content's words, not just StudyPage.vue. Owns:
 *  - status filter (all/in-review/learned, defaults to all)
 *  - level filter select (A1-C2 + "no level" + "all", per 7.3)
 *  - "select all under current filter" + selection count + clear (per 7.4)
 *  - the bulk-action bar itself, calling the bulk-mark/bulk-start functions
 *    the host page already gets from useLexemes() (per 7.5)
 *
 * Deliberately does NOT own the search box (search predates this component
 * on both host pages) or the `<WordListItem>` rendering (host pages differ
 * in which per-item actions they wire up) — only `searchQuery` is accepted,
 * as an optional extra filter, so "select all" still means "all rows
 * currently visible" even while a search term is active, matching
 * StudyPage.vue's pre-extraction behavior exactly. A `#search` slot is
 * offered so the host page's own search input can sit below the status and
 * level controls.
 */

const props = withDefaults(
    defineProps<{
        /** Full, unfiltered word list for this content (e.g. `lexemes.value` from useLexemes()). */
        lexemes: LexemeWithLearned[];
        /** Current selection, keyed by ContentLexeme.id. Two-way via v-model:selected-ids. */
        selectedIds: Set<number>;
        /** Bulk mark-as-learned action from the host page's own useLexemes() instance. */
        bulkMarkLearned: (ids: number[]) => Promise<BulkLexemeActionResponse | null>;
        /** Bulk add-to-learning action from the host page's own useLexemes() instance. */
        bulkStartLearning: (ids: number[]) => Promise<BulkLexemeActionResponse | null>;
        bulkActionPending?: boolean;
        /** Optional extra text filter (e.g. the host page's own search box), applied on top of status+level. */
        searchQuery?: string;
        /**
         * The learner's own CEFR level (profile.user.current_level), if the
         * host page has one. Pre-selects that level chip so a learner opens
         * a word list already scoped to words they can plausibly tackle,
         * instead of the unfiltered dump — same "default once real data
         * arrives, but never override an explicit choice" idiom as the
         * status default above.
         */
        defaultLevel?: string | null;
    }>(),
    { bulkActionPending: false, searchQuery: '', defaultLevel: null },
);

const emit = defineEmits<{
    'update:selectedIds': [ids: Set<number>];
    /** Fires whenever the status+level+search-filtered list changes. */
    'update:filtered': [list: LexemeWithLearned[]];
    /** The host page owns navigation (it has the router + content id) — this just hands back which ids to practice. */
    'practice-selected': [ids: number[]];
    /** Same as 'practice-selected', but for the sentence-translation context mode instead of the adaptive queue. */
    'practice-context': [ids: number[]];
}>();

type StatusFilter = 'all' | 'new' | 'in-review' | 'learned' | 'skipped';
const filterStatus = ref<StatusFilter>('all');

// Task 7.3: CEFR level filter, independent of (and combined with) the status filter.
type LevelFilter = 'all' | 'A1' | 'A2' | 'B1' | 'B2' | 'C1' | 'C2' | 'none';
const LEVELS: Exclude<LevelFilter, 'all' | 'none'>[] = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];
const filterLevel = ref<LevelFilter>('all');

type CategoryFilter = 'all' | NonNullable<LexemeWithLearned['learning_category']>;
const filterCategory = ref<CategoryFilter>('all');
const CATEGORY_LABELS: Record<Exclude<CategoryFilter, 'all'>, string> = {
    essential: 'Essential',
    useful_phrase: 'Useful phrases',
    grammar_pattern: 'Grammar patterns',
    recommended: 'Recommended',
    known: 'Already known',
    rare: 'Rare',
    noise: 'Names / noise',
};

const levelChosenByUser = ref(false);

function setLevel(level: LevelFilter) {
    levelChosenByUser.value = true;
    filterLevel.value = level;
}

watch(
    () => props.defaultLevel,
    (level) => {
        if (levelChosenByUser.value || !level) return;
        if ((LEVELS as string[]).includes(level)) filterLevel.value = level as LevelFilter;
    },
    { immediate: true },
);

// "To learn" means actively in the spaced-repetition queue (in_review) —
// not merely "not learned yet", which used to include every untouched word
// and made the tab redundant with "All minus Learned".
const inReview = computed(() => props.lexemes.filter((l) => l.in_review && !l.learned));
const learned = computed(() => props.lexemes.filter((l) => l.learned));
// Words the learner marked "not interested" — hidden from every other tab,
// recoverable only here.
const skipped = computed(() => props.lexemes.filter((l) => l.skipped));
// "New" is the actually-undiscovered words: extracted from this content but
// neither queued for review, marked learned, nor skipped yet. This is the
// bucket a learner opening a fresh piece of content actually wants to land
// on — "All" buries it under everything already triaged, "To learn" only
// shows what was already added to review.
const newWords = computed(() => props.lexemes.filter((l) => !l.learned && !l.in_review && !l.skipped));

const statusFilteredLexemes = computed(() => {
    if (filterStatus.value === 'new') return newWords.value;
    if (filterStatus.value === 'in-review') return inReview.value;
    if (filterStatus.value === 'learned') return learned.value;
    if (filterStatus.value === 'skipped') return skipped.value;
    return props.lexemes;
});

// Default to "New" the first time real data arrives, so opening a content's
// word list lands on what's actually undiscovered instead of the full,
// mostly-irrelevant word dump. Only applies until the learner picks a tab
// themselves — after that their choice sticks, including across re-filters.
const statusChosenByUser = ref(false);

function setStatus(status: StatusFilter) {
    statusChosenByUser.value = true;
    filterStatus.value = status;
}

watch(
    () => props.lexemes,
    (list) => {
        if (statusChosenByUser.value || list.length === 0) return;
        filterStatus.value = list.some((l) => !l.learned && !l.in_review && !l.skipped) ? 'new' : 'all';
    },
    { immediate: true },
);

// Level counts are computed off the status-filtered list (not the full list),
// consistent with how the all/to-learn/learned counts work.
const levelCounts = computed(() => {
    const counts: Record<string, number> = { none: 0 };
    for (const level of LEVELS) counts[level] = 0;
    for (const l of statusFilteredLexemes.value) {
        if (l.level) counts[l.level] = (counts[l.level] ?? 0) + 1;
        else counts.none++;
    }
    return counts;
});

const levelInput = computed({
    get: () => filterLevel.value,
    set: (value: string) => setLevel(value as LevelFilter),
});

const levelOptions = computed(() => [
    { value: 'all', label: `All levels (${statusFilteredLexemes.value.length})` },
    ...LEVELS.map((level) => ({ value: level, label: `${level} (${levelCounts.value[level]})` })),
    { value: 'none', label: `No level (${levelCounts.value.none})` },
]);

const categoryCounts = computed(() => {
    const counts = Object.fromEntries(Object.keys(CATEGORY_LABELS).map((key) => [key, 0])) as Record<Exclude<CategoryFilter, 'all'>, number>;
    for (const lexeme of statusFilteredLexemes.value) {
        if (lexeme.learning_category && lexeme.learning_category in counts) counts[lexeme.learning_category] += 1;
    }
    return counts;
});

const categoryInput = computed({
    get: () => filterCategory.value,
    set: (value: string) => (filterCategory.value = value as CategoryFilter),
});

const categoryOptions = computed(() => [
    { value: 'all', label: `All learning types (${statusFilteredLexemes.value.length})` },
    ...Object.entries(CATEGORY_LABELS).map(([value, label]) => ({
        value,
        label: `${label} (${categoryCounts.value[value as Exclude<CategoryFilter, 'all'>]})`,
    })),
]);

const filteredLexemes = computed(() => {
    let list: LexemeWithLearned[] = statusFilteredLexemes.value;

    if (filterLevel.value === 'none') list = list.filter((l) => !l.level);
    else if (filterLevel.value !== 'all') list = list.filter((l) => l.level === filterLevel.value);
    if (filterCategory.value !== 'all') list = list.filter((l) => l.learning_category === filterCategory.value);

    const q = (props.searchQuery ?? '').trim().toLowerCase();
    if (q) {
        list = list.filter(
            (l) => l.text.toLowerCase().includes(q) || (l.translation ?? '').toLowerCase().includes(q),
        );
    }
    return list;
});

watch(filteredLexemes, (list) => emit('update:filtered', list), { immediate: true });

// Task 7.4: "select all" means "all rows visible under the current
// status/level/search filter" — both host pages render their full word list
// at once (no pagination), so that's unambiguous.
const allFilteredSelected = computed(
    () => filteredLexemes.value.length > 0 && filteredLexemes.value.every((l) => props.selectedIds.has(l.id)),
);

function toggleSelectAllFiltered() {
    if (allFilteredSelected.value) {
        const next = new Set(props.selectedIds);
        for (const l of filteredLexemes.value) next.delete(l.id);
        emit('update:selectedIds', next);
    } else {
        emit('update:selectedIds', new Set([...props.selectedIds, ...filteredLexemes.value.map((l) => l.id)]));
    }
}

function clearSelection() {
    emit('update:selectedIds', new Set());
}

// Task 7.5: bulk actions bar. Reports "N of M" so a partial failure (see
// BulkLexemeActionResponse) is visible rather than silently swallowed.
const bulkResultMessage = ref('');

function practiceSelected() {
    emit('practice-selected', [...props.selectedIds]);
}

function practiceContext() {
    emit('practice-context', [...props.selectedIds]);
}

// Marking words "known" in bulk skips any recall check entirely — a
// mis-click here (e.g. after "select all") silently corrupts the learner's
// own progress stats and there's no bulk undo. One extra tap to confirm is
// cheap insurance; the single-word version keeps its instant undo instead.
const confirmingBulkMark = ref(false);

function requestBulkMark() {
    confirmingBulkMark.value = true;
}

function cancelBulkMark() {
    confirmingBulkMark.value = false;
}

// If the selection changes while a bulk-mark confirmation is pending, the
// count it's confirming is stale — drop back to the plain button instead of
// confirming against words the learner no longer has selected.
watch(() => props.selectedIds, () => (confirmingBulkMark.value = false));

async function bulkMark() {
    confirmingBulkMark.value = false;
    const ids = [...props.selectedIds];
    const result = await props.bulkMarkLearned(ids);
    if (!result) return;
    bulkResultMessage.value = `Marked ${result.succeeded} of ${ids.length} as known${result.failed > 0 ? ` (${result.failed} failed)` : ''}.`;
    clearSelection();
}

async function bulkStart() {
    const ids = [...props.selectedIds];
    const result = await props.bulkStartLearning(ids);
    if (!result) return;
    bulkResultMessage.value = `Added ${result.succeeded} of ${ids.length} to learning${result.failed > 0 ? ` (${result.failed} failed)` : ''}.`;
    clearSelection();
}
</script>

<template>
    <div class="space-y-3">
        <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
            <UiButton class="w-full sm:w-auto" :variant="filterStatus === 'new' ? 'primary' : 'secondary'" size="sm" @click="setStatus('new')">New ({{ newWords.length }})</UiButton>
            <UiButton class="w-full sm:w-auto" :variant="filterStatus === 'in-review' ? 'primary' : 'secondary'" size="sm" @click="setStatus('in-review')">To learn ({{ inReview.length }})</UiButton>
            <UiButton class="w-full sm:w-auto" :variant="filterStatus === 'learned' ? 'primary' : 'secondary'" size="sm" @click="setStatus('learned')">Learned ({{ learned.length }})</UiButton>
            <UiButton v-if="skipped.length > 0" class="w-full sm:w-auto" :variant="filterStatus === 'skipped' ? 'primary' : 'secondary'" size="sm" @click="setStatus('skipped')">Not interested ({{ skipped.length }})</UiButton>
            <UiButton class="w-full sm:w-auto" :variant="filterStatus === 'all' ? 'primary' : 'secondary'" size="sm" @click="setStatus('all')">All ({{ props.lexemes.length }})</UiButton>
        </div>

        <SelectField v-model="levelInput" label="Level" :options="levelOptions" />

        <SelectField v-model="categoryInput" label="Learning type" :options="categoryOptions" />

        <slot name="search" />

        <div v-if="filteredLexemes.length > 0" class="flex flex-wrap items-center justify-between gap-2 text-sm">
            <label class="flex min-h-10 items-center gap-2 text-muted-foreground">
                <input
                    type="checkbox"
                    class="h-4 w-4 rounded border-border accent-primary"
                    :checked="allFilteredSelected"
                    aria-label="Select all words matching the current filter"
                    @change="toggleSelectAllFiltered"
                />
                Select all filtered ({{ filteredLexemes.length }})
            </label>
            <template v-if="selectedIds.size > 0">
                <span class="text-fg">{{ selectedIds.size }} selected</span>
                <UiButton variant="ghost" size="sm" @click="clearSelection">Clear</UiButton>
            </template>
        </div>

        <div
            v-if="selectedIds.size > 0"
            class="grid gap-2 rounded-spa border border-border bg-black/10 p-3 sm:flex sm:flex-wrap sm:items-center"
        >
            <span class="text-sm text-muted-foreground">{{ selectedIds.size }} selected</span>
            <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                <UiButton class="w-full sm:w-auto" variant="primary" size="sm" :disabled="bulkActionPending" @click="practiceSelected">Practice</UiButton>
                <UiButton class="w-full sm:w-auto" variant="secondary" size="sm" :disabled="bulkActionPending" @click="practiceContext">In context</UiButton>
                <UiButton class="w-full sm:w-auto" variant="secondary" size="sm" :disabled="bulkActionPending" @click="bulkStart">Add to learning</UiButton>
            </div>
            <template v-if="confirmingBulkMark">
                <span class="text-sm text-warning">Mark all {{ selectedIds.size }} as already known?</span>
                <UiButton variant="primary" size="sm" :disabled="bulkActionPending" @click="bulkMark">Confirm</UiButton>
                <UiButton variant="ghost" size="sm" :disabled="bulkActionPending" @click="cancelBulkMark">Cancel</UiButton>
            </template>
            <UiButton v-else variant="secondary" size="sm" :disabled="bulkActionPending" @click="requestBulkMark">Mark as known</UiButton>
        </div>
        <p v-if="bulkResultMessage" class="text-sm text-muted-foreground" role="status">{{ bulkResultMessage }}</p>
    </div>
</template>
