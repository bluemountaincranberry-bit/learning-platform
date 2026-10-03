<script setup lang="ts">
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { BookOpen, ChevronRight, Clapperboard, GraduationCap, LayoutGrid, Music, Youtube } from 'lucide-vue-next';
import { useAuthStore } from '../domains/user';
import { CEFR_LEVELS, useCatalog } from '../domains/content';
import UiBadge from '../shared/ui/UiBadge.vue';
import UiButton from '../shared/ui/UiButton.vue';
import UiCard from '../shared/ui/UiCard.vue';
import UiContentProgress from '../shared/ui/UiContentProgress.vue';
import UiEmptyState from '../shared/ui/UiEmptyState.vue';
import UiInput from '../shared/ui/UiInput.vue';
import UiSectionHeader from '../shared/ui/UiSectionHeader.vue';
import SelectField from '../shared/ui/SelectField.vue';

const authStore = useAuthStore();
const router = useRouter();
const { loading, error, items, selectedType, selectedLevel, selectedScope, searchQuery, loadCatalog } = useCatalog({
    scope: authStore.isAuthenticated ? 'mine' : 'all',
});

const TYPE_OPTIONS = [
    { value: 'all', label: 'All', icon: LayoutGrid },
    { value: 'youtube', label: 'YouTube', icon: Youtube },
    { value: 'song', label: 'Song', icon: Music },
    { value: 'movie', label: 'Movie', icon: Clapperboard },
    { value: 'book', label: 'Book', icon: BookOpen },
    { value: 'grammar', label: 'Grammar', icon: GraduationCap },
];

// Falls back to the generic catalog icon for any content type that isn't in
// TYPE_OPTIONS (e.g. new source types added on the backend before the
// frontend filter list catches up).
function typeIcon(type: string) {
    return TYPE_OPTIONS.find((option) => option.value === type)?.icon ?? BookOpen;
}

const LEVEL_OPTIONS = [
    { value: 'all', label: 'All' },
    ...CEFR_LEVELS.map((level) => ({ value: level, label: level })),
];

const SCOPE_OPTIONS: { value: 'mine' | 'all'; label: string }[] = [
    { value: 'mine', label: 'My content' },
    { value: 'all', label: 'All catalog' },
];

function isMine(item: { created_by: number | null }): boolean {
    return authStore.isAuthenticated && item.created_by === authStore.user?.id;
}

function openCatalogItem(id: number): void {
    router.push({ name: 'catalog.details', params: { id } });
}

const emptyStateCopy = computed(() =>
    selectedScope.value === 'mine'
        ? { title: "You haven't added anything yet", description: 'Submit a source, or switch to the full catalog.' }
        : { title: 'No content available', description: 'Adjust filters or add a new source.' },
);

</script>

<template>
    <div class="space-y-6">
        <section class="space-y-4">
            <UiSectionHeader title="Catalog" subtitle="Your content, or browse everything" />

            <div v-if="authStore.isAuthenticated" class="grid grid-cols-2 gap-1 rounded-md border border-border bg-muted/40 p-1">
                <button
                    v-for="option in SCOPE_OPTIONS"
                    :key="option.value"
                    type="button"
                    class="rounded-sm py-1.5 text-sm font-medium transition-colors"
                    :class="selectedScope === option.value ? 'bg-card text-fg shadow-sm' : 'text-muted-foreground hover:text-fg'"
                    :aria-pressed="selectedScope === option.value"
                    @click="selectedScope = option.value"
                >
                    {{ option.label }}
                </button>
            </div>

            <UiCard class="space-y-3">
                <label class="block">
                    <span class="sr-only">Search</span>
                    <UiInput v-model="searchQuery" type="search" placeholder="Title or topic" aria-label="Search catalog by title" />
                </label>

                <div class="-mx-1 flex gap-1.5 overflow-x-auto px-1 pb-0.5 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    <button
                        v-for="option in TYPE_OPTIONS"
                        :key="option.value"
                        type="button"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium transition-colors"
                        :class="selectedType === option.value
                            ? 'border-primary bg-primary/10 text-primary'
                            : 'border-border bg-background text-muted-foreground hover:border-primary/50 hover:text-fg'"
                        :aria-pressed="selectedType === option.value"
                        @click="selectedType = option.value"
                    >
                        <component :is="option.icon" :size="14" />
                        {{ option.label }}
                    </button>
                </div>

                <div class="max-w-xs">
                    <SelectField v-model="selectedLevel" label="Level" placeholder="Choose level" :options="LEVEL_OPTIONS" />
                </div>
            </UiCard>

            <div class="grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                <UiCard
                    v-for="item in items"
                    :key="item.id"
                    class="group cursor-pointer p-3 transition-all hover:-translate-y-0.5 hover:border-primary hover:shadow-md focus-within:border-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring sm:p-4"
                    role="link"
                    tabindex="0"
                    :aria-label="`Open ${item.title}`"
                    @click="openCatalogItem(item.id)"
                    @keydown.enter.prevent="openCatalogItem(item.id)"
                    @keydown.space.prevent="openCatalogItem(item.id)"
                >
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground">
                            <component :is="typeIcon(item.type)" :size="16" />
                        </div>

                        <div class="min-w-0 flex-1 space-y-1.5">
                            <div class="flex items-start justify-between gap-2">
                                <span class="block truncate text-sm font-semibold text-fg group-hover:text-primary">
                                    {{ item.title }}
                                </span>
                                <UiBadge v-if="item.level" tone="neutral" class="shrink-0">{{ item.level }}</UiBadge>
                            </div>

                            <div class="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
                                <span class="uppercase tracking-wide">{{ item.language }}</span>
                                <UiBadge tone="primary">{{ item.status }}</UiBadge>
                                <UiBadge v-if="item.ready_to_watch" tone="success">Ready ✓</UiBadge>
                                <UiBadge v-if="selectedScope === 'all' && isMine(item)" tone="primary">Yours</UiBadge>
                                <UiBadge v-if="!authStore.isAuthenticated" tone="neutral">Preview</UiBadge>
                            </div>

                            <UiContentProgress v-if="authStore.isAuthenticated" :learned-count="item.learned_count" :in-learning-count="item.in_learning_count" :total-lexemes="item.total_lexemes" />
                        </div>

                        <UiButton
                            variant="ghost"
                            size="icon"
                            class="shrink-0"
                            :aria-label="`Open ${item.title}`"
                            @click.stop="openCatalogItem(item.id)"
                        >
                            <ChevronRight :size="18" />
                        </UiButton>
                    </div>
                </UiCard>
            </div>

            <UiEmptyState
                v-if="!loading && !error && items.length === 0"
                :title="emptyStateCopy.title"
                :description="emptyStateCopy.description"
            >
                <div class="flex flex-wrap gap-2">
                    <UiButton variant="primary" @click="router.push({ name: 'add-youtube' })">Add YouTube</UiButton>
                    <UiButton v-if="selectedScope === 'mine'" variant="secondary" @click="selectedScope = 'all'">Show all catalog</UiButton>
                    <UiButton v-else variant="secondary" @click="router.push({ name: 'categories' })">Browse categories</UiButton>
                </div>
            </UiEmptyState>

            <div v-if="loading" class="grid gap-2 md:grid-cols-2 xl:grid-cols-3" aria-label="Loading catalog" aria-busy="true">
                <div v-for="n in 6" :key="n" class="h-32 animate-pulse rounded-xl border border-border bg-card/70"></div>
            </div>
            <div v-else-if="error" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-500/20 bg-amber-500/10 px-4 py-3 text-sm text-amber-800" role="alert">
                <span>{{ error }}</span>
                <UiButton variant="secondary" size="sm" @click="loadCatalog">Try again</UiButton>
            </div>
        </section>
    </div>
</template>
