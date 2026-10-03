<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { Plus } from 'lucide-vue-next';
import { CEFR_LEVELS } from '../domains/content';
import { grammarApi } from '../domains/content';
import { useAuthStore } from '../domains/user';
import type { GrammarRule } from '../types';
import UiBadge from '../shared/ui/UiBadge.vue';
import UiButton from '../shared/ui/UiButton.vue';
import UiCard from '../shared/ui/UiCard.vue';
import UiEmptyState from '../shared/ui/UiEmptyState.vue';
import UiSectionHeader from '../shared/ui/UiSectionHeader.vue';
import SelectField from '../shared/ui/SelectField.vue';

const authStore = useAuthStore();
const router = useRouter();

const loading = ref(true);
const error = ref('');
const items = ref<GrammarRule[]>([]);
const selectedLevel = ref('all');

const LEVEL_OPTIONS = [
    { value: 'all', label: 'All' },
    ...CEFR_LEVELS.map((level) => ({ value: level, label: level })),
];

const query = computed(() => ({
    ...(selectedLevel.value !== 'all' ? { level: selectedLevel.value } : {}),
}));

async function loadCatalog(): Promise<void> {
    loading.value = true;
    error.value = '';
    try {
        const data = await grammarApi.getList(query.value);
        items.value = data.data ?? [];
    } catch {
        error.value = 'Failed to load grammar catalog.';
    } finally {
        loading.value = false;
    }
}

watch(selectedLevel, loadCatalog);
onMounted(loadCatalog);

function openGrammarRule(id: number): void {
    router.push({ name: 'grammar.details', params: { id } });
}

async function quickAddToMyList(item: GrammarRule): Promise<void> {
    await grammarApi.startLearning(item.id);
    item.in_my_list = true;
}
</script>

<template>
    <div class="space-y-6">
        <UiSectionHeader title="Grammar catalog" subtitle="Patterns and rules you will see in context" />

        <UiCard>
            <div class="max-w-xs">
                <SelectField v-model="selectedLevel" label="Level" placeholder="Choose level" :options="LEVEL_OPTIONS" />
            </div>
        </UiCard>

        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            <UiCard
                v-for="item in items"
                :key="item.id"
                class="group cursor-pointer transition-all hover:-translate-y-0.5 hover:border-primary hover:shadow-md focus-within:border-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                role="link"
                tabindex="0"
                :aria-label="`Open ${item.title}`"
                @click="openGrammarRule(item.id)"
                @keydown.enter.prevent="openGrammarRule(item.id)"
                @keydown.space.prevent="openGrammarRule(item.id)"
            >
                <div class="space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <span class="block truncate text-base font-semibold text-fg group-hover:text-primary">
                                {{ item.title }}
                            </span>
                            <div class="mt-1 text-sm text-muted-foreground">{{ item.topic?.name ?? item.language }}</div>
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            <UiBadge v-if="item.level" tone="primary">{{ item.level }}</UiBadge>
                            <UiBadge v-if="item.in_my_list" tone="primary" title="In your grammar list">
                                <Plus :size="12" />
                            </UiBadge>
                            <UiButton
                                v-else-if="authStore.isAuthenticated"
                                variant="ghost"
                                size="sm"
                                title="Add to my grammar"
                                @click.stop="quickAddToMyList(item)"
                            >
                                <Plus :size="14" />
                            </UiButton>
                        </div>
                    </div>
                    <p v-if="item.summary" class="text-sm text-muted-foreground line-clamp-2">{{ item.summary }}</p>
                    <div class="flex flex-wrap gap-2">
                        <UiBadge tone="neutral">{{ item.language }}</UiBadge>
                    </div>
                </div>
            </UiCard>
        </div>

        <UiEmptyState v-if="!loading && !error && items.length === 0" title="No grammar content yet" description="Grammar rules will appear here once they're extracted and published." />

        <div v-if="loading" class="grid gap-3 md:grid-cols-2 xl:grid-cols-3" aria-label="Loading grammar" aria-busy="true">
            <div v-for="n in 6" :key="n" class="h-36 animate-pulse rounded-xl border border-border bg-card/70"></div>
        </div>
        <div v-else-if="error" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-500/20 bg-amber-500/10 px-4 py-3 text-sm text-amber-800" role="alert">
            <span>{{ error }}</span>
            <UiButton variant="secondary" size="sm" @click="loadCatalog">Try again</UiButton>
        </div>
    </div>
</template>
