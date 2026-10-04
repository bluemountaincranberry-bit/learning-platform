<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { Plus } from 'lucide-vue-next';
import { CEFR_LEVELS } from '../domains/content';
import { grammarApi } from '../domains/content';
import { useAuthStore } from '../domains/user';
import type { GrammarRule } from '../types';
import GrammarCard from '../shared/ui/GrammarCard.vue';
import UiButton from '../shared/ui/UiButton.vue';
import UiCard from '../shared/ui/UiCard.vue';
import UiEmptyState from '../shared/ui/UiEmptyState.vue';
import UiSectionHeader from '../shared/ui/UiSectionHeader.vue';
import SelectField from '../shared/ui/SelectField.vue';

const authStore = useAuthStore();

const loading = ref(true);
const error = ref('');
const items = ref<GrammarRule[]>([]);
const selectedLevel = ref('all');
const addingId = ref<number | null>(null);

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

async function quickAddToMyList(item: GrammarRule): Promise<void> {
    if (addingId.value !== null) return;
    addingId.value = item.id;
    try {
        await grammarApi.startLearning(item.id);
        item.in_my_list = true;
    } catch {
        error.value = 'Failed to add to My grammar.';
    } finally {
        addingId.value = null;
    }
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
            <GrammarCard v-for="item in items" :key="item.id" :title="item.title" :rule-id="item.id" :level="item.level" :summary="item.summary" :status="item.in_my_list ? 'In My grammar' : ''">
                <span class="text-xs text-muted-foreground">{{ item.topic?.name ?? item.language }}</span>
                <template v-if="!item.in_my_list && authStore.isAuthenticated" #actions>
                    <UiButton variant="primary" size="touch" :disabled="addingId === item.id" @click="quickAddToMyList(item)"><Plus :size="16" /> Add to My grammar</UiButton>
                </template>
            </GrammarCard>
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
