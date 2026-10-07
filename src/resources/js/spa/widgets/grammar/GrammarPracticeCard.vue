<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import UiButton from '../../shared/ui/UiButton.vue';
import { grammarPracticeApi, grammarPracticeMinutes, useGrammarPracticeSetting } from '../../domains/learning';
import type { GrammarPracticeOverview } from '../../types';
import GrammarPracticeSettingSheet from './GrammarPracticeSettingSheet.vue';
import { LEVEL_LABEL } from './practice/exerciseLabels';

/**
 * The exercises block on a rule page (VIK-31): three lines above one button
 * — what, how much, how it went last time — and "Change" for the rest.
 */
const props = defineProps<{ ruleId: number; authenticated: boolean; contentId?: number | null }>();

const router = useRouter();
const { setting } = useGrammarPracticeSetting();
const overview = ref<GrammarPracticeOverview | null>(null);
const sheetOpen = ref(false);

const settingLine = computed(() =>
    `${LEVEL_LABEL[setting.value.level]} · ${setting.value.count} exercises · ~${grammarPracticeMinutes(setting.value.count)} min`,
);

const lastLine = computed(() => {
    const last = overview.value?.lastResult;
    if (!last) return null;
    return `Last time ${last.correctCount}/${last.scoredCount} · ${timeAgo(last.completedAt)}`;
});

const cannotPrepare = computed(() => overview.value !== null && overview.value.availableCount === 0 && !overview.value.canGenerate);

function timeAgo(iso: string): string {
    const days = Math.floor((Date.now() - new Date(iso).getTime()) / 86_400_000);
    if (days <= 0) return 'today';
    if (days === 1) return 'yesterday';
    return `${days} days ago`;
}

function practice(): void {
    sheetOpen.value = false;
    void router.push({
        name: 'grammar.practice',
        params: { id: props.ruleId },
        query: {
            level: setting.value.level,
            count: String(setting.value.count),
            from: router.currentRoute.value.fullPath,
            ...(props.contentId ? { content: String(props.contentId) } : {}),
        },
    });
}

function login(): void {
    void router.push({ name: 'login', query: { redirect: router.currentRoute.value.fullPath } });
}

onMounted(async () => {
    if (!props.authenticated) return;
    try {
        overview.value = await grammarPracticeApi.getOverview(props.ruleId);
    } catch {
        overview.value = null;
    }
});

defineExpose({ practice });
</script>

<template>
    <section class="rounded-spa-lg border border-primary/60 bg-card p-4" aria-labelledby="practice-title" data-test="practice-card">
        <h3 id="practice-title" class="text-base font-semibold text-fg">Practice this rule</h3>
        <template v-if="authenticated">
            <p class="mt-1 text-sm text-fg" data-test="setting-line">{{ settingLine }}</p>
            <p v-if="lastLine" class="text-sm text-muted-foreground" data-test="last-line">{{ lastLine }}</p>
            <p v-if="cannotPrepare" class="mt-2 text-sm text-muted-foreground">Exercises can't be prepared right now.</p>
            <UiButton variant="primary" size="lg" class="mt-3 w-full" :disabled="cannotPrepare" data-test="practice" @click="practice">Practice</UiButton>
            <div class="mt-1 flex items-center justify-between gap-2">
                <button type="button" class="min-h-11 text-sm font-medium text-primary" data-test="change" @click="sheetOpen = true">Change</button>
                <span v-if="overview?.confidenceCalculated != null" class="text-sm text-muted-foreground">
                    Practice says {{ Math.round(overview.confidenceCalculated) }}%
                </span>
            </div>
            <GrammarPracticeSettingSheet :open="sheetOpen" @close="sheetOpen = false" @practice="practice" />
        </template>
        <template v-else>
            <p class="mt-1 text-sm text-muted-foreground">Log in to practice with exercises and track your progress.</p>
            <UiButton variant="primary" size="lg" class="mt-3 w-full" @click="login">Log in to practice</UiButton>
        </template>
    </section>
</template>
