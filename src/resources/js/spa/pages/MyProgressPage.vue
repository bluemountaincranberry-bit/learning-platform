<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import PageState from '../components/ui/PageState.vue';
import { useAuthStore } from '../domains/user';
import { progressStatsApi } from '../domains/learning';
import UiBadge from '../shared/ui/UiBadge.vue';
import UiButton from '../shared/ui/UiButton.vue';
import UiCard from '../shared/ui/UiCard.vue';
import UiEmptyState from '../shared/ui/UiEmptyState.vue';
import UiSectionHeader from '../shared/ui/UiSectionHeader.vue';
import UiStatTile from '../shared/ui/UiStatTile.vue';
import type { ProgressStatsResponse } from '../types';

const router = useRouter();
const authStore = useAuthStore();

const stats = ref<ProgressStatsResponse | null>(null);
const loading = ref(true);
const error = ref('');

async function fetchStats() {
    loading.value = true;
    error.value = '';
    try {
        stats.value = await progressStatsApi.getStats();
    } catch (e: unknown) {
        const err = e as { response?: { status?: number; data?: { message?: string } } };
        if (err.response?.status === 401) {
            router.push({ name: 'login', query: { redirect: '/my-progress' } });
            return;
        }
        error.value = err.response?.data?.message ?? 'Failed to load progress.';
    } finally {
        loading.value = false;
    }
}

function formatAccuracy(n: number) {
    return `${Number(n).toFixed(0)}%`;
}

function practiceRecommendation(contentId?: number, activity?: string) {
    if (contentId == null) return;
    router.push({ name: 'repetitions', query: { content_id: String(contentId), activity: activity ?? 'adaptive', return_to: 'my-progress' } });
}

onMounted(async () => {
    if (!authStore.isAuthenticated) {
        router.push({ name: 'login', query: { redirect: '/my-progress' } });
        return;
    }
    await fetchStats();
});
</script>

<template>
    <div class="space-y-6">
        <PageState :loading="loading" :error="error">
            <template v-if="stats">
                <UiCard class="space-y-4">
                    <UiSectionHeader title="My progress" subtitle="Learning-oriented overview" />
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        <UiStatTile label="Total learned" :value="stats.overview.total_learned" />
                        <UiStatTile label="Today" :value="stats.overview.today_count" />
                        <UiStatTile label="Streak" :value="`${stats.overview.streak}d`" />
                        <UiStatTile label="Daily goal" :value="stats.overview.daily_goal ?? '—'" />
                    </div>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <UiStatTile label="Points total" :value="stats.points.total" />
                        <UiStatTile label="Points today" :value="stats.points.today" />
                        <UiStatTile label="Points this week" :value="stats.points.week" />
                    </div>
                </UiCard>

                <div class="grid gap-4 xl:grid-cols-2">
                    <UiCard class="space-y-4">
                        <UiSectionHeader title="By content" subtitle="Strongest and weakest materials" />
                        <div class="grid gap-3 md:grid-cols-2">
                            <div class="rounded-spa-lg border border-border bg-black/10 p-4">
                                <div class="text-sm text-muted-foreground">Your strongest</div>
                                <div v-if="stats.by_content.best.length" class="mt-3 space-y-2">
                                    <div v-for="item in stats.by_content.best" :key="item.content_id" class="flex items-center justify-between gap-3">
                                        <RouterLink :to="{ name: 'catalog.details', params: { id: item.content_id } }" class="truncate text-sm text-primary underline">
                                            {{ item.title }}
                                        </RouterLink>
                                        <span class="text-sm text-muted-foreground">{{ formatAccuracy(item.accuracy) }}</span>
                                    </div>
                                </div>
                                <UiEmptyState v-else title="No strong content yet" description="Study to see your best materials here." />
                            </div>
                            <div class="rounded-spa-lg border border-border bg-black/10 p-4">
                                <div class="text-sm text-muted-foreground">Need more practice</div>
                                <div v-if="stats.by_content.weak.length" class="mt-3 space-y-2">
                                    <div v-for="item in stats.by_content.weak" :key="item.content_id" class="flex items-center justify-between gap-3">
                                        <RouterLink :to="{ name: 'catalog.details', params: { id: item.content_id } }" class="truncate text-sm text-primary underline">
                                            {{ item.title }}
                                        </RouterLink>
                                        <span class="text-sm text-muted-foreground">{{ formatAccuracy(item.accuracy) }}</span>
                                    </div>
                                </div>
                                <UiEmptyState v-else title="No weak content" description="Keep it up." />
                            </div>
                        </div>
                    </UiCard>

                    <UiCard class="space-y-4">
                        <UiSectionHeader title="By language and level" subtitle="Accuracy across your current mix" />
                        <div v-if="stats.by_language_level.length" class="overflow-x-auto">
                            <table class="w-full border-collapse text-sm">
                                <thead class="bg-surface-alt">
                                    <tr>
                                        <th class="border-b border-border-strong px-3 py-2 text-left font-semibold text-fg">Language</th>
                                        <th class="border-b border-border-strong px-3 py-2 text-left font-semibold text-fg">Level</th>
                                        <th class="border-b border-border-strong px-3 py-2 text-right font-semibold text-fg">Answers</th>
                                        <th class="border-b border-border-strong px-3 py-2 text-right font-semibold text-fg">Accuracy</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(row, i) in stats.by_language_level" :key="i" class="border-b border-border last:border-b-0">
                                        <td class="px-3 py-2">{{ row.language }}</td>
                                        <td class="px-3 py-2">{{ row.level ?? '—' }}</td>
                                        <td class="px-3 py-2 text-right">{{ row.total_answers }}</td>
                                        <td class="px-3 py-2 text-right">{{ formatAccuracy(row.accuracy) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <UiEmptyState v-else title="No progress by level yet" description="The table will populate after reviews and self-checks." />
                    </UiCard>
                </div>

                <UiCard class="space-y-4">
                    <UiSectionHeader title="Skill practice" subtitle="Accuracy by the ability you are training" />
                    <div v-if="stats.skill_accuracy.length" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div v-for="skill in stats.skill_accuracy" :key="skill.skill" class="rounded-spa-lg border border-border bg-black/10 p-4">
                            <div class="flex items-center justify-between gap-2"><span class="text-sm font-medium capitalize">{{ skill.skill }}</span><span class="text-sm font-semibold text-primary">{{ formatAccuracy(skill.accuracy) }}</span></div>
                            <div class="mt-3 h-2 overflow-hidden rounded-full bg-muted"><div class="h-full rounded-full bg-primary transition-all" :style="{ width: `${skill.accuracy}%` }" /></div>
                            <p class="mt-2 text-xs text-muted-foreground">{{ skill.correct }} / {{ skill.attempts }} correct</p>
                        </div>
                    </div>
                    <UiEmptyState v-else title="No skill data yet" description="Complete dictation, recall, or speaking exercises to see this breakdown." />
                </UiCard>

                <UiCard class="space-y-4">
                    <UiSectionHeader title="Retention checkpoints" subtitle="How well earlier learning is holding up" />
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div v-for="days in [1, 7, 30]" :key="days" class="rounded-spa-lg border border-border bg-black/10 p-4 text-center">
                            <p class="text-xs uppercase tracking-wide text-muted-foreground">{{ days }} day{{ days === 1 ? '' : 's' }}</p>
                            <p class="mt-2 text-2xl font-semibold text-fg">{{ formatAccuracy(stats.retention[String(days) as '1' | '7' | '30'].accuracy) }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">{{ stats.retention[String(days) as '1' | '7' | '30'].correct }} / {{ stats.retention[String(days) as '1' | '7' | '30'].attempts }} correct</p>
                        </div>
                    </div>
                </UiCard>

                <div class="grid gap-4 xl:grid-cols-2">
                    <UiCard v-if="stats.weak_words.length" class="space-y-4">
                        <UiSectionHeader title="Weak words" subtitle="Most-missed words from your reviews, worst first" />
                        <div class="flex flex-wrap gap-2">
                            <UiBadge v-for="(w, i) in stats.weak_words" :key="i" tone="warning">
                                <RouterLink :to="{ name: 'catalog.details', params: { id: w.content_id } }" class="text-warning-fg underline" :title="w.hint">
                                    {{ w.lexeme }}
                                </RouterLink>
                            </UiBadge>
                        </div>
                    </UiCard>

                    <UiCard v-if="stats.weak_grammar_topics.length" class="space-y-4">
                        <UiSectionHeader title="Weak grammar topics" subtitle="Where your missed words cluster grammatically" />
                        <div class="space-y-2">
                            <div v-for="topic in stats.weak_grammar_topics" :key="topic.grammar_rule_id" class="flex items-center justify-between gap-3">
                                <RouterLink :to="{ name: 'grammar.details', params: { id: topic.grammar_rule_id } }" class="truncate text-sm text-primary underline">
                                    {{ topic.title }}
                                </RouterLink>
                                <span class="text-sm text-muted-foreground">{{ topic.mistake_count }} missed</span>
                            </div>
                        </div>
                    </UiCard>
                </div>

                <UiCard class="space-y-4 border-warning-border bg-warning-bg/70">
                    <UiSectionHeader title="Recommendations" subtitle="Next steps from the progress model" />
                    <div v-if="stats.recommendations.length" class="space-y-2 text-sm">
                        <div v-for="(rec, i) in stats.recommendations" :key="i" class="text-fg">
                            <template v-if="rec.content_id != null">
                                <RouterLink :to="{ name: 'catalog.details', params: { id: rec.content_id } }" class="text-primary underline">
                                    {{ rec.title ?? 'Content' }}
                                </RouterLink>
                                <span v-if="rec.hint" class="ml-1 text-muted-foreground">- {{ rec.hint }}</span>
                                <UiBadge v-if="rec.activity" class="ml-2" tone="neutral">{{ rec.activity }}</UiBadge>
                                <UiButton v-if="rec.activity" class="ml-2" size="sm" variant="ghost" @click="practiceRecommendation(rec.content_id, rec.activity)">Practice</UiButton>
                            </template>
                            <span v-else>{{ rec.hint ?? rec.type }}</span>
                        </div>
                    </div>
                    <UiEmptyState v-else title="No specific recommendations" description="Keep studying and the queue will adjust itself." />
                    <UiButton variant="primary" @click="router.push({ name: 'catalog' })">Browse catalog</UiButton>
                </UiCard>
            </template>
        </PageState>
    </div>
</template>
