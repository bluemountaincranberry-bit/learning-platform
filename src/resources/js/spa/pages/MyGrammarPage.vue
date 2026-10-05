<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { Check, Undo2 } from 'lucide-vue-next';
import PageState from '../components/ui/PageState.vue';
import { useAuthStore } from '../domains/user';
import { learnedGrammarRulesApi } from '../domains/learning';
import { grammarApi } from '../domains/content';
import UiBadge from '../shared/ui/UiBadge.vue';
import GrammarCard from '../shared/ui/GrammarCard.vue';
import UiButton from '../shared/ui/UiButton.vue';
import UiCard from '../shared/ui/UiCard.vue';
import UiEmptyState from '../shared/ui/UiEmptyState.vue';
import UiSectionHeader from '../shared/ui/UiSectionHeader.vue';
import SelectField from '../shared/ui/SelectField.vue';
import type { LearnedGrammarRuleItem, LearnedGrammarRulesParams } from '../types';

const router = useRouter();
const authStore = useAuthStore();

const PER_PAGE_OPTIONS = [10, 15, 25, 50];

const items = ref<LearnedGrammarRuleItem[]>([]);
const meta = ref<{ current_page: number; per_page: number; total: number; last_page?: number }>({
    current_page: 1,
    per_page: 15,
    total: 0,
});
const loading = ref(true);
const error = ref('');

const markingId = ref<number | null>(null);
const removingId = ref<number | null>(null);

const filterStatus = ref('all');
const statusOptions = [
    { value: 'all', label: 'All' },
    { value: 'learning', label: 'Learning' },
    { value: 'learned', label: 'Learned' },
];

const totalPages = computed(() => meta.value.last_page ?? Math.max(1, Math.ceil(meta.value.total / meta.value.per_page)));
const canPrev = computed(() => meta.value.current_page > 1);
const canNext = computed(() => meta.value.current_page < totalPages.value);

const queryParams = computed<LearnedGrammarRulesParams>(() => {
    const q: LearnedGrammarRulesParams = {
        page: meta.value.current_page,
        per_page: meta.value.per_page,
    };
    if (filterStatus.value !== 'all') q.status = filterStatus.value as 'learning' | 'learned';
    return q;
});

async function fetchMyGrammar() {
    loading.value = true;
    error.value = '';
    try {
        const data = await learnedGrammarRulesApi.getList(queryParams.value);
        items.value = data.data ?? [];
        meta.value = data.meta ?? meta.value;
    } catch (e: unknown) {
        const err = e as { response?: { status?: number; data?: { message?: string } } };
        if (err.response?.status === 401) {
            router.push({ name: 'login', query: { redirect: '/my-grammar' } });
            return;
        }
        error.value = err.response?.data?.message ?? 'Failed to load your grammar list.';
    } finally {
        loading.value = false;
    }
}

function goToPage(page: number) {
    if (page < 1 || page > totalPages.value) return;
    meta.value = { ...meta.value, current_page: page };
    fetchMyGrammar();
}

function setPerPage(perPage: number) {
    meta.value = { ...meta.value, per_page: perPage, current_page: 1 };
    fetchMyGrammar();
}

async function markLearned(row: LearnedGrammarRuleItem) {
    markingId.value = row.id;
    try {
        await grammarApi.markLearned(row.grammar_rule_id);
        row.status = 'learned';
    } catch {
        error.value = 'Failed to mark as learned.';
    } finally {
        markingId.value = null;
    }
}

async function remove(row: LearnedGrammarRuleItem) {
    removingId.value = row.id;
    try {
        await grammarApi.unmarkLearned(row.grammar_rule_id);
        items.value = items.value.filter((item) => item.id !== row.id);
        meta.value = { ...meta.value, total: Math.max(0, meta.value.total - 1) };
    } catch {
        error.value = 'Failed to remove from your list.';
    } finally {
        removingId.value = null;
    }
}

function formatDate(iso: string) {
    try {
        return new Date(iso).toLocaleDateString();
    } catch {
        return iso;
    }
}

onMounted(async () => {
    if (!authStore.isAuthenticated) {
        router.push({ name: 'login', query: { redirect: '/my-grammar' } });
        return;
    }
    await fetchMyGrammar();
});

watch(filterStatus, () => {
    if (meta.value.current_page !== 1) {
        meta.value = { ...meta.value, current_page: 1 };
    }
    fetchMyGrammar();
});
</script>

<template>
    <div class="space-y-6">
        <UiCard>
            <UiSectionHeader title="My grammar" subtitle="Rules you've chosen to work on" />
            <div class="mt-4 max-w-xs">
                <SelectField v-model="filterStatus" label="Status" placeholder="Choose status" :options="statusOptions" />
            </div>
        </UiCard>

        <PageState :loading="loading" :error="error">
            <template #retry>
                <UiButton variant="secondary" size="sm" @click="fetchMyGrammar">Try again</UiButton>
            </template>
            <template v-if="items.length === 0 && !loading">
                <UiEmptyState title="No grammar in your list yet" description="Browse the catalog and add rules you want to learn.">
                    <UiButton variant="primary" @click="router.push({ name: 'grammar' })">Browse grammar catalog</UiButton>
                </UiEmptyState>
            </template>

            <template v-else>
                <UiCard class="space-y-3">
                    <GrammarCard v-for="row in items" :key="row.id" :title="row.title ?? 'Grammar rule'" :rule-id="row.grammar_rule_id" :level="row.level" :summary="row.summary" :status="row.status === 'learned' ? 'Learned' : 'Learning'" :status-tone="row.status === 'learned' ? 'success' : 'primary'">
                        <span class="text-xs text-muted-foreground">{{ row.status === 'learned' ? `Learned ${formatDate(row.learned_at ?? '')}` : `Started ${formatDate(row.started_at ?? '')}` }}</span>
                        <UiBadge v-if="row.topic" tone="neutral">{{ row.topic.name }}</UiBadge>
                        <template #actions>
                            <UiButton v-if="row.status !== 'learned'" variant="primary" size="touch" :disabled="markingId === row.id" @click="markLearned(row)"><Check :size="14" /> Mark as learned</UiButton>
                            <UiButton variant="ghost" size="touch" :disabled="removingId === row.id" @click="remove(row)"><Undo2 :size="14" /> Remove</UiButton>
                        </template>
                    </GrammarCard>
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
    </div>
</template>
