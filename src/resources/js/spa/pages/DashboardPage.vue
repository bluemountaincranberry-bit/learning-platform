<script setup lang="ts">
import { onMounted, computed, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../domains/user';
import { useCategories, useCatalog, useRecommendedContents, useRecommendedLexemes } from '../domains/content';
import { progressStatsApi, srsApi } from '../domains/learning';
import UiBadge from '../shared/ui/UiBadge.vue';
import UiButton from '../shared/ui/UiButton.vue';
import UiCard from '../shared/ui/UiCard.vue';
import UiContentProgress from '../shared/ui/UiContentProgress.vue';
import UiEmptyState from '../shared/ui/UiEmptyState.vue';
import UiSectionHeader from '../shared/ui/UiSectionHeader.vue';
import UiStatTile from '../shared/ui/UiStatTile.vue';
import type { ProgressStatsResponse } from '../types';

const authStore = useAuthStore();
const router = useRouter();
const { categories, loadCategories } = useCategories();
const { items: catalogItems, loadCatalog } = useCatalog();
const { items: recommendedItems, loading: recommendedLoading, error: recommendedError } = useRecommendedContents(6);
const { items: recommendedLexemes, loading: recommendedLexemesLoading, error: recommendedLexemesError } = useRecommendedLexemes(8);

const stats = reactive({
    loading: false,
    error: '',
    data: null as ProgressStatsResponse | null,
});

const dueCount = ref<number | null>(null);

const isAuthenticated = computed(() => authStore.isAuthenticated);
const previewItems = computed(() => catalogItems.value.slice(0, 6));

async function loadStats() {
    stats.loading = true;
    stats.error = '';
    try {
        stats.data = await progressStatsApi.getStats();
    } catch {
        stats.error = 'Failed to load dashboard stats.';
    } finally {
        stats.loading = false;
    }
}

async function loadDueCount() {
    try {
        const data = await srsApi.getDue();
        dueCount.value = data.items?.length ?? 0;
    } catch {
        dueCount.value = null;
    }
}

onMounted(async () => {
    await Promise.all([loadCategories(), loadCatalog()]);
    if (isAuthenticated.value) {
        await Promise.all([loadStats(), loadDueCount()]);
    }
});
</script>

<template>
    <div class="space-y-6">
        <section class="grid gap-4 xl:grid-cols-[1.6fr_1fr]">
            <UiCard class="relative overflow-hidden">
                <div class="absolute inset-0 bg-[linear-gradient(180deg,rgba(124,58,237,0.08),transparent_45%)]"></div>
                <div class="relative space-y-5">
                    <div class="flex items-start justify-between gap-4">
                        <div class="space-y-2">
                            <div class="text-[11px] uppercase tracking-[0.3em] text-muted-foreground">Start surface</div>
                            <h2 class="text-3xl font-semibold text-fg">Build the next session from real content.</h2>
                            <p class="max-w-2xl text-sm leading-6 text-muted-foreground">
                                Open a film or YouTube source, inspect words and grammar, choose what to learn, study it, then review it later.
                            </p>
                        </div>
                        <UiBadge tone="primary">{{ isAuthenticated ? 'active' : 'guest' }}</UiBadge>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <UiButton variant="primary" @click="router.push({ name: 'catalog' })">Open catalog</UiButton>
                        <UiButton variant="secondary" @click="router.push({ name: 'add-youtube' })">Add YouTube</UiButton>
                        <UiButton variant="ghost" @click="router.push({ name: 'chat' })">Open AI chat</UiButton>
                    </div>
                </div>
            </UiCard>

            <UiCard class="space-y-4">
                <UiSectionHeader title="Today" subtitle="Current learning state" />
                <template v-if="isAuthenticated && stats.data">
                    <div class="grid grid-cols-2 gap-3">
                        <UiStatTile label="Learned" :value="stats.data.overview.total_learned" />
                        <UiStatTile label="Today" :value="stats.data.overview.today_count" />
                        <UiStatTile label="Streak" :value="`${stats.data.overview.streak}d`" />
                        <UiStatTile label="Goal" :value="stats.data.overview.daily_goal ?? '—'" />
                    </div>
                    <RouterLink
                        v-if="dueCount"
                        :to="{ name: 'repetitions' }"
                        class="flex items-center justify-between rounded-spa border border-primary/30 bg-primary/10 px-4 py-3 text-sm text-fg transition-colors hover:border-primary hover:bg-primary/20"
                    >
                        <span>{{ dueCount }} card{{ dueCount === 1 ? '' : 's' }} due for review</span>
                        <span class="text-primary">Review now →</span>
                    </RouterLink>
                </template>
                <template v-else-if="isAuthenticated && stats.loading">
                    <UiEmptyState
                        title="Loading learning state"
                        description="Dashboard metrics will appear as soon as the profile and stats are ready."
                    />
                </template>
                <template v-else-if="isAuthenticated">
                    <UiEmptyState title="Could not load dashboard metrics" description="The rest of the app still works. Try reloading the state." />
                    <div class="mt-3">
                        <UiButton variant="secondary" @click="loadStats">Retry</UiButton>
                    </div>
                </template>
                <template v-else>
                    <UiEmptyState
                        title="Sign in to unlock progress"
                        description="The dashboard becomes personal after login: streak, goal, due reviews, and recommended content."
                    >
                        <UiButton variant="primary" @click="router.push({ name: 'login' })">Login</UiButton>
                    </UiEmptyState>
                </template>
            </UiCard>
        </section>

        <section class="grid gap-4 lg:grid-cols-2">
            <UiCard>
                <UiSectionHeader title="Recommended content" subtitle="The next best items for this account" />
                <div v-if="recommendedLoading" class="text-sm text-muted-foreground">Loading...</div>
                <div v-else-if="recommendedError" class="text-sm text-warning">{{ recommendedError }}</div>
                <div v-else-if="recommendedItems.length === 0" class="mt-4">
                    <UiEmptyState title="No recommendations yet" description="Study a few items and the list will fill itself." />
                </div>
                <div v-else class="mt-4 grid gap-3">
                    <RouterLink
                        v-for="item in recommendedItems"
                        :key="item.id"
                        :to="{ name: 'catalog.details', params: { id: item.id } }"
                        class="rounded-spa-lg border border-border bg-black/10 p-3 transition-colors hover:border-primary hover:bg-surface-alt"
                    >
                        <div class="flex flex-wrap items-center gap-2">
                            <div class="font-medium text-fg">{{ item.title }}</div>
                            <UiBadge tone="neutral">{{ item.type }}</UiBadge>
                            <UiBadge v-if="item.level" tone="primary">{{ item.level }}</UiBadge>
                        </div>
                        <div class="mt-1 text-sm text-muted-foreground">{{ item.language }}</div>
                        <UiContentProgress class="mt-2" :learned-count="item.learned_count" :in-learning-count="item.in_learning_count" :total-lexemes="item.total_lexemes" />
                    </RouterLink>
                </div>
            </UiCard>

            <UiCard>
                <UiSectionHeader title="Catalog preview" subtitle="Fast entry into content" />
                <div class="mt-4 grid gap-3">
                    <RouterLink
                        v-for="item in previewItems"
                        :key="item.id"
                        :to="{ name: 'catalog.details', params: { id: item.id } }"
                        class="rounded-spa-lg border border-border bg-black/10 p-3 transition-colors hover:border-primary hover:bg-surface-alt"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="truncate font-medium text-fg">{{ item.title }}</div>
                                <div class="text-sm text-muted-foreground">{{ item.type }} · {{ item.language }}</div>
                                <UiContentProgress class="mt-2" :learned-count="item.learned_count" :in-learning-count="item.in_learning_count" :total-lexemes="item.total_lexemes" />
                            </div>
                            <UiBadge v-if="item.level" tone="neutral">{{ item.level }}</UiBadge>
                        </div>
                    </RouterLink>
                </div>
            </UiCard>
        </section>

        <section class="grid gap-4 lg:grid-cols-[1fr_1.1fr]">
            <UiCard>
                <UiSectionHeader title="Categories" subtitle="Quick browsing lanes" />
                <div class="mt-4 flex flex-wrap gap-2">
                    <RouterLink
                        v-for="category in categories"
                        :key="category"
                        :to="{ name: 'catalog' }"
                        class="rounded-sm border border-border bg-surface px-3 py-1.5 text-sm text-fg-secondary capitalize hover:border-primary hover:text-fg"
                    >
                        {{ category }}
                    </RouterLink>
                </div>
            </UiCard>

            <UiCard v-if="isAuthenticated && stats.data">
                <UiSectionHeader title="Weak points" subtitle="What needs attention next" />
                <div class="mt-4 space-y-3">
                    <div v-if="stats.data.weak_words.length === 0" class="text-sm text-muted-foreground">No weak words yet.</div>
                    <div v-else v-for="word in stats.data.weak_words.slice(0, 5)" :key="`${word.content_id}-${word.lexeme}`" class="flex items-center justify-between gap-3 rounded-spa border border-border bg-black/10 px-3 py-2">
                        <div class="min-w-0">
                            <div class="truncate font-medium text-fg">{{ word.lexeme }}</div>
                            <div class="truncate text-sm text-muted-foreground">{{ word.hint }}</div>
                        </div>
                        <RouterLink :to="{ name: 'catalog.details', params: { id: word.content_id } }" class="text-sm text-primary">
                            Open
                        </RouterLink>
                    </div>
                </div>
            </UiCard>
        </section>

        <UiCard v-if="isAuthenticated">
            <UiSectionHeader title="Words to learn next" subtitle="Ranked by similarity to what you've already learned, and how overdue their content is" />
            <div v-if="recommendedLexemesLoading" class="mt-4 text-sm text-muted-foreground">Loading...</div>
            <div v-else-if="recommendedLexemesError" class="mt-4 text-sm text-warning">{{ recommendedLexemesError }}</div>
            <div v-else-if="recommendedLexemes.length === 0" class="mt-4">
                <UiEmptyState title="No word recommendations yet" description="Learn a few words first so we have something to compare against." />
            </div>
            <div v-else class="mt-4 flex flex-wrap gap-2">
                <RouterLink
                    v-for="lexeme in recommendedLexemes"
                    :key="lexeme.id"
                    :to="{ name: 'catalog.details', params: { id: lexeme.content_id } }"
                    class="rounded-spa border border-border bg-black/10 px-3 py-1.5 text-sm text-fg-secondary transition-colors hover:border-primary hover:text-fg"
                >
                    {{ lexeme.text }}
                </RouterLink>
            </div>
        </UiCard>
    </div>
</template>
