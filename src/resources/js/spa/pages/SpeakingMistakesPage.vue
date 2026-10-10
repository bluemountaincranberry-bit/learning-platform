<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { ChevronLeft, RotateCcw, Archive, Dumbbell } from 'lucide-vue-next';
import PageState from '../components/ui/PageState.vue';
import { speakingMistakesApi, type SpeakingMistake, type SpeakingMistakesReport } from '../domains/learning';
import UiButton from '../shared/ui/UiButton.vue';
import UiCard from '../shared/ui/UiCard.vue';
import UiEmptyState from '../shared/ui/UiEmptyState.vue';
import UiSectionHeader from '../shared/ui/UiSectionHeader.vue';

const router = useRouter();
const report = ref<SpeakingMistakesReport | null>(null);
const status = ref<'active' | 'mastered' | 'hidden' | 'all'>('active');
const loading = ref(true);
const savingId = ref<number | null>(null);
const error = ref('');

async function load() {
    loading.value = true;
    error.value = '';
    try { report.value = await speakingMistakesApi.report(status.value); }
    catch { error.value = 'Could not load your speaking report.'; }
    finally { loading.value = false; }
}

async function changeStatus(mistake: SpeakingMistake, next: SpeakingMistake['status']) {
    savingId.value = mistake.id;
    try { await speakingMistakesApi.setStatus(mistake.id, next); await load(); }
    catch { error.value = 'Could not update this mistake.'; }
    finally { savingId.value = null; }
}

function practice(mistake: SpeakingMistake) {
    router.push({ name: 'speaking-practice', query: { mistake_ids: String(mistake.id) } });
}

function categoryLabel(value: string) {
    return value.replaceAll('_', ' ');
}

onMounted(load);
</script>

<template>
    <main class="mx-auto max-w-3xl space-y-5 px-4 py-5 pb-24 sm:py-7">
        <div class="flex items-center gap-3">
            <UiButton variant="ghost" size="icon" aria-label="Back to practice" @click="router.push({ name: 'repetitions' })"><ChevronLeft :size="20" /></UiButton>
            <UiSectionHeader title="My speaking mistakes" subtitle="See what repeats, track progress, and practise it again." />
        </div>
        <PageState v-if="loading" :loading="true" />
        <template v-else>
            <p v-if="error" role="alert" class="text-sm text-warning">{{ error }}</p>
            <div v-if="report" class="grid grid-cols-3 gap-2">
                <UiCard class="p-3 text-center"><div class="text-2xl font-semibold text-fg">{{ report.summary.active }}</div><div class="text-xs text-muted-foreground">Active</div></UiCard>
                <UiCard class="p-3 text-center"><div class="text-2xl font-semibold text-fg">{{ report.summary.mastered }}</div><div class="text-xs text-muted-foreground">Mastered</div></UiCard>
                <UiCard class="p-3 text-center"><div class="text-2xl font-semibold text-fg">{{ report.summary.hidden }}</div><div class="text-xs text-muted-foreground">Hidden</div></UiCard>
            </div>
            <UiCard v-if="report?.weekly_trend.length" class="space-y-3 p-4">
                <h2 class="text-sm font-semibold text-fg">Mistakes added over time</h2>
                <div class="flex h-20 items-end gap-2" aria-label="Weekly speaking mistake trend">
                    <div v-for="week in report.weekly_trend" :key="week.week" class="flex min-w-0 flex-1 flex-col items-center gap-1">
                        <span class="text-[10px] text-muted-foreground">{{ week.count }}</span>
                        <div class="w-full rounded-t bg-primary/70" :style="{ height: `${Math.max(6, Math.min(54, week.count * 12))}px` }" />
                        <span class="text-[9px] text-muted-foreground">{{ new Date(week.week).toLocaleDateString(undefined, { month: 'short', day: 'numeric' }) }}</span>
                    </div>
                </div>
            </UiCard>
            <div class="flex gap-1 overflow-x-auto rounded-lg border border-border bg-surface p-1" aria-label="Filter speaking mistakes">
                <button v-for="item in [{ value: 'active', label: 'Active' }, { value: 'mastered', label: 'Mastered' }, { value: 'hidden', label: 'Hidden' }, { value: 'all', label: 'All' }]" :key="item.value" type="button" class="min-h-10 flex-1 rounded-md px-3 text-xs font-medium" :class="status === item.value ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'" @click="status = item.value as typeof status; load()">{{ item.label }}</button>
            </div>
            <UiEmptyState v-if="!report?.mistakes.length" title="No mistakes here" description="Mistakes from speaking practice will appear here with examples and a path to practise them." />
            <div v-else class="space-y-3">
                <UiCard v-for="mistake in report.mistakes" :key="mistake.id" class="space-y-3 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="rounded-full bg-primary/10 px-2 py-1 text-xs capitalize text-primary">{{ categoryLabel(mistake.category) }}</span>
                        <span class="text-xs text-muted-foreground">{{ mistake.confidence === 'clear' ? 'Saved automatically' : 'Confirmed by you' }} · {{ mistake.source_type.replaceAll('_', ' ') }}</span>
                    </div>
                    <div v-if="mistake.prompt_text" class="text-xs text-muted-foreground">Prompt: {{ mistake.prompt_text }}</div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div><div class="text-[11px] uppercase tracking-wide text-muted-foreground">You said</div><p class="mt-1 break-words text-fg-secondary">{{ mistake.original_text }}</p></div>
                        <div><div class="text-[11px] uppercase tracking-wide text-muted-foreground">Correction</div><p class="mt-1 break-words font-medium text-fg">{{ mistake.corrected_text }}</p></div>
                    </div>
                    <p v-if="mistake.explanation" class="text-sm text-muted-foreground">{{ mistake.explanation }}</p>
                    <p v-if="mistake.status === 'active'" class="text-xs text-muted-foreground">{{ mistake.consecutive_correct }} of 3 correct answers toward mastering</p>
                    <div class="flex flex-wrap gap-2">
                        <UiButton v-if="mistake.status === 'active'" variant="primary" size="sm" @click="practice(mistake)"><Dumbbell :size="14" /> Practise</UiButton>
                        <UiButton v-if="mistake.status === 'active'" variant="ghost" size="sm" :disabled="savingId === mistake.id" @click="changeStatus(mistake, 'hidden')"><Archive :size="14" /> Remove from practice</UiButton>
                        <UiButton v-else-if="mistake.status === 'hidden'" variant="secondary" size="sm" :disabled="savingId === mistake.id" @click="changeStatus(mistake, 'active')"><RotateCcw :size="14" /> Restore</UiButton>
                        <UiButton v-else variant="secondary" size="sm" :disabled="savingId === mistake.id" @click="changeStatus(mistake, 'active')"><RotateCcw :size="14" /> Practise again</UiButton>
                    </div>
                </UiCard>
            </div>
        </template>
    </main>
</template>
