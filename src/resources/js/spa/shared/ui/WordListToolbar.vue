<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { DropdownMenuContent, DropdownMenuItem, DropdownMenuPortal, DropdownMenuRoot, DropdownMenuTrigger } from 'reka-ui';
import { MoreHorizontal, SlidersHorizontal, X } from 'lucide-vue-next';
import UiButton from './UiButton.vue';
import UiDialog from './UiDialog.vue';
import UiInput from './UiInput.vue';
import SelectField from './SelectField.vue';
import type { LexemeWithLearned, BulkLexemeActionResponse } from '../../types';

/**
 * Filters, selection and bulk actions for a content's word list, shared by
 * ContentDetailsPage.vue and StudyPage.vue (task 7.8). Owns:
 *  - status segments New / To learn / Learned / (Not interested) / All
 *  - level, learning type and search, behind one "Filter" bottom sheet with
 *    removable chips for the active ones (VIK-38)
 *  - "select all under current filter" + the sticky bulk-action bar
 *
 * The `<WordListItem>` rows stay in the host page (pages differ in which
 * per-item actions they wire up); the default slot is rendered between the
 * filters and the sticky bar so the bar sticks to the bottom of the list.
 *
 * VIK-38: no level/type is pre-selected — Vika picks them on purpose.
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
    }>(),
    { bulkActionPending: false },
);

const emit = defineEmits<{
    'update:selectedIds': [ids: Set<number>];
    /** Fires whenever the status+level+type+search-filtered list changes. */
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

const searchQuery = ref('');

/** The filters that live in the Filter sheet and show up as chips. */
type FilterKey = 'level' | 'category' | 'search';

// "To learn" means actively in the spaced-repetition queue (in_review) —
// not merely "not learned yet", which used to include every untouched word
// and made the tab redundant with "All minus Learned".
const inReview = computed(() => props.lexemes.filter((l) => l.in_review && !l.learned));
const learned = computed(() => props.lexemes.filter((l) => l.learned));
// Words the learner marked "not interested" — hidden from every other tab,
// recoverable only here.
const skipped = computed(() => props.lexemes.filter((l) => l.skipped));
// "New" is the actually-undiscovered words: extracted from this content but
// neither queued for review, marked learned, nor skipped yet.
const newWords = computed(() => props.lexemes.filter((l) => !l.learned && !l.in_review && !l.skipped));

const statusSegments = computed(() => [
    { key: 'new' as const, label: 'New', count: newWords.value.length },
    { key: 'in-review' as const, label: 'To learn', count: inReview.value.length },
    { key: 'learned' as const, label: 'Learned', count: learned.value.length },
    ...(skipped.value.length > 0 ? [{ key: 'skipped' as const, label: 'Hidden', count: skipped.value.length }] : []),
    { key: 'all' as const, label: 'All', count: props.lexemes.length },
]);

const statusFilteredLexemes = computed(() => {
    if (filterStatus.value === 'new') return newWords.value;
    if (filterStatus.value === 'in-review') return inReview.value;
    if (filterStatus.value === 'learned') return learned.value;
    if (filterStatus.value === 'skipped') return skipped.value;
    return props.lexemes;
});

// Default to "New" the first time real data arrives, so opening a content's
// word list lands on what's actually undiscovered. Only applies until the
// learner picks a segment themselves — after that their choice sticks.
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
// consistent with how the status counts work.
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
    set: (value: string) => (filterLevel.value = value as LevelFilter),
});

const levelOptions = computed(() => [
    { value: 'all', label: `Any level (${statusFilteredLexemes.value.length})` },
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
    { value: 'all', label: `Any type (${statusFilteredLexemes.value.length})` },
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

    const q = searchQuery.value.trim().toLowerCase();
    if (q) {
        list = list.filter(
            (l) => l.text.toLowerCase().includes(q) || (l.translation ?? '').toLowerCase().includes(q),
        );
    }
    return list;
});

watch(filteredLexemes, (list) => emit('update:filtered', list), { immediate: true });

// Chips for the filters hidden in the sheet, so an active filter is never invisible.
const activeChips = computed(() => {
    const chips: { key: FilterKey; label: string }[] = [];
    if (filterLevel.value !== 'all') chips.push({ key: 'level', label: filterLevel.value === 'none' ? 'No level' : filterLevel.value });
    if (filterCategory.value !== 'all') chips.push({ key: 'category', label: CATEGORY_LABELS[filterCategory.value] });
    if (searchQuery.value.trim()) chips.push({ key: 'search', label: `“${searchQuery.value.trim()}”` });
    return chips;
});

function clearChip(key: FilterKey) {
    if (key === 'level') filterLevel.value = 'all';
    else if (key === 'category') filterCategory.value = 'all';
    else searchQuery.value = '';
}

function resetFilters() {
    filterLevel.value = 'all';
    filterCategory.value = 'all';
    searchQuery.value = '';
}

const filterSheetOpen = ref(false);

// Task 7.4: "select all" means "all rows visible under the current
// status/level/type/search filter" — both host pages render their full word
// list at once (no pagination), so that's unambiguous.
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

// Task 7.5: bulk actions. Reports "N of M" so a partial failure (see
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

// If the selection changes while a bulk-mark confirmation is pending, the
// count it's confirming is stale — drop back to the plain bar instead of
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

const menuItemClass =
    'flex min-h-11 cursor-default select-none items-center gap-2 rounded-md px-3 text-sm text-foreground outline-none transition-colors data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-40';
</script>

<template>
    <div>
        <div class="space-y-2 px-4 sm:px-0">
            <div role="group" aria-label="Word status" class="flex rounded-lg bg-muted p-0.5">
                <button
                    v-for="segment in statusSegments"
                    :key="segment.key"
                    type="button"
                    :aria-pressed="filterStatus === segment.key"
                    class="flex min-h-11 min-w-0 flex-1 flex-col items-center justify-center rounded-md px-1 text-xs font-medium leading-tight transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    :class="filterStatus === segment.key ? 'bg-background text-fg shadow-sm' : 'text-muted-foreground'"
                    @click="setStatus(segment.key)"
                >
                    <span class="w-full truncate">{{ segment.label }}</span>
                    <span class="tabular-nums" :class="filterStatus === segment.key ? 'text-primary' : ''">{{ segment.count }}</span>
                </button>
            </div>

            <div class="flex min-h-11 items-center gap-2">
                <UiButton variant="secondary" size="touch" class="shrink-0" :aria-label="`Filter words${activeChips.length ? ` (${activeChips.length} active)` : ''}`" @click="filterSheetOpen = true">
                    <SlidersHorizontal :size="16" />
                    Filter
                    <span v-if="activeChips.length" class="rounded-full bg-primary px-1.5 text-[11px] leading-5 text-primary-foreground">{{ activeChips.length }}</span>
                </UiButton>
                <div class="flex min-w-0 flex-1 gap-1 overflow-x-auto">
                    <button
                        v-for="chip in activeChips"
                        :key="chip.key"
                        type="button"
                        class="flex h-11 max-w-[10rem] shrink-0 items-center"
                        :aria-label="`Remove filter ${chip.label}`"
                        @click="clearChip(chip.key)"
                    >
                        <span class="inline-flex h-8 min-w-0 items-center gap-1 rounded-full bg-primary/10 pl-2.5 pr-1.5 text-xs font-medium text-primary">
                            <span class="truncate">{{ chip.label }}</span>
                            <X :size="14" class="shrink-0" />
                        </span>
                    </button>
                </div>
                <label v-if="filteredLexemes.length > 0 && selectedIds.size === 0" class="flex min-h-11 shrink-0 cursor-pointer items-center gap-2 pl-1 text-sm text-muted-foreground">
                    <span>All {{ filteredLexemes.length }}</span>
                    <input
                        type="checkbox"
                        class="h-5 w-5 rounded border-border accent-primary"
                        :checked="allFilteredSelected"
                        aria-label="Select all words matching the current filter"
                        @change="toggleSelectAllFiltered"
                    />
                </label>
            </div>
            <p v-if="bulkResultMessage" class="text-sm text-muted-foreground" role="status">{{ bulkResultMessage }}</p>
        </div>

        <slot />

        <div
            v-if="selectedIds.size > 0"
            class="sticky bottom-[4.75rem] z-20 mt-2 space-y-2 border-y border-border bg-card/95 p-3 shadow-[0_-8px_28px_rgba(15,23,42,0.08)] backdrop-blur sm:rounded-xl sm:border lg:bottom-4"
        >
            <div class="flex items-center gap-2 text-sm">
                <label class="flex min-h-11 cursor-pointer items-center gap-2 text-muted-foreground">
                    <input
                        type="checkbox"
                        class="h-5 w-5 rounded border-border accent-primary"
                        :checked="allFilteredSelected"
                        aria-label="Select all words matching the current filter"
                        @change="toggleSelectAllFiltered"
                    />
                    Select all {{ filteredLexemes.length }}
                </label>
                <span class="ml-auto font-medium text-fg">{{ selectedIds.size }} selected</span>
                <UiButton variant="ghost" size="icon-touch" aria-label="Clear selection" @click="clearSelection">
                    <X :size="18" />
                </UiButton>
            </div>

            <div v-if="confirmingBulkMark" class="flex flex-wrap items-center gap-2">
                <span class="flex-1 text-sm text-warning">Mark all {{ selectedIds.size }} as already known?</span>
                <UiButton variant="primary" size="touch" :disabled="bulkActionPending" @click="bulkMark">Confirm</UiButton>
                <UiButton variant="ghost" size="touch" :disabled="bulkActionPending" @click="confirmingBulkMark = false">Cancel</UiButton>
            </div>
            <div v-else class="flex items-center gap-2">
                <UiButton variant="primary" size="touch" class="flex-1" :disabled="bulkActionPending" @click="bulkStart">Add {{ selectedIds.size }} to learning</UiButton>
                <UiButton variant="secondary" size="touch" :disabled="bulkActionPending" @click="practiceSelected">Practice</UiButton>
                <DropdownMenuRoot>
                    <DropdownMenuTrigger
                        class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-md border border-border bg-secondary text-secondary-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50"
                        :disabled="bulkActionPending"
                        aria-label="More actions for selected words"
                    >
                        <MoreHorizontal :size="18" />
                    </DropdownMenuTrigger>
                    <DropdownMenuPortal>
                        <DropdownMenuContent
                            class="z-50 min-w-[220px] overflow-hidden rounded-md border border-border bg-popover p-1 text-popover-foreground shadow-[0_18px_50px_rgba(15,23,42,0.12)]"
                            align="end"
                            side="top"
                            :side-offset="6"
                        >
                            <DropdownMenuItem :class="menuItemClass" @select="practiceContext">Practice in sentences</DropdownMenuItem>
                            <DropdownMenuItem :class="menuItemClass" @select="confirmingBulkMark = true">Mark as known</DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenuPortal>
                </DropdownMenuRoot>
            </div>
        </div>

        <UiDialog :open="filterSheetOpen" title="Filter words" sheet @close="filterSheetOpen = false">
            <div class="space-y-4">
                <UiInput v-model="searchQuery" type="search" placeholder="Search word or translation..." aria-label="Search words" />
                <SelectField v-model="levelInput" label="Level" :options="levelOptions" />
                <SelectField v-model="categoryInput" label="Learning type" :options="categoryOptions" />
            </div>
            <template #footer>
                <UiButton variant="ghost" size="touch" :disabled="activeChips.length === 0" @click="resetFilters">Reset</UiButton>
                <UiButton variant="primary" size="touch" @click="filterSheetOpen = false">Show {{ filteredLexemes.length }} words</UiButton>
            </template>
        </UiDialog>
    </div>
</template>
