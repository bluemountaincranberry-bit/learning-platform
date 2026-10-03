<script setup lang="ts">
import { onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { CheckCircle2, Circle, Lock } from 'lucide-vue-next';
import { useContentReadiness } from '../../domains/content';
import UiButton from '../../shared/ui/UiButton.vue';
import UiCard from '../../shared/ui/UiCard.vue';
import UiSectionHeader from '../../shared/ui/UiSectionHeader.vue';

/**
 * "Ready to watch" quest tracker — words/grammar steps are computed live
 * server-side (ContentReadinessService::stepsStatus(), not stored), the
 * exam step reflects the latest content_exam_attempts row. Deliberately a
 * badge/nudge, not a hard gate: the video itself is never hidden, only this
 * card's own state changes.
 */
const props = defineProps<{ contentId: number }>();

const router = useRouter();
const { loading, readiness, load } = useContentReadiness(props.contentId);

onMounted(load);

function goStudyWords() {
    router.push({ name: 'catalog.study', params: { id: props.contentId } });
}

function goGrammar() {
    router.push({ name: 'grammar' });
}

function goExam() {
    router.push({ name: 'catalog.exam', params: { id: props.contentId } });
}
</script>

<template>
    <UiCard v-if="!loading && readiness" class="space-y-4">
        <UiSectionHeader
            title="Ready to watch"
            :subtitle="readiness.ready ? `Passed — ${readiness.latest_attempt?.score_pct}%` : 'Learn the words and grammar, then take the exam'"
        />

        <div class="space-y-2">
            <div class="flex items-center justify-between gap-3 rounded-spa-lg border border-border bg-black/10 px-4 py-3">
                <div class="flex items-center gap-2">
                    <component :is="readiness.steps.words.complete ? CheckCircle2 : Circle" :size="18" :class="readiness.steps.words.complete ? 'text-emerald-500' : 'text-muted-foreground'" />
                    <span class="text-sm text-fg">Words — {{ readiness.steps.words.learned }}/{{ readiness.steps.words.total }}</span>
                </div>
                <UiButton v-if="!readiness.steps.words.complete" variant="ghost" size="sm" @click="goStudyWords">Study</UiButton>
            </div>

            <div class="flex items-center justify-between gap-3 rounded-spa-lg border border-border bg-black/10 px-4 py-3">
                <div class="flex items-center gap-2">
                    <component :is="readiness.steps.grammar.complete ? CheckCircle2 : Circle" :size="18" :class="readiness.steps.grammar.complete ? 'text-emerald-500' : 'text-muted-foreground'" />
                    <span class="text-sm text-fg">Grammar — {{ readiness.steps.grammar.learned }}/{{ readiness.steps.grammar.total }}</span>
                </div>
                <UiButton v-if="!readiness.steps.grammar.complete && readiness.steps.grammar.total > 0" variant="ghost" size="sm" @click="goGrammar">Study</UiButton>
            </div>

            <div class="flex items-center justify-between gap-3 rounded-spa-lg border border-border bg-black/10 px-4 py-3">
                <div class="flex items-center gap-2">
                    <component
                        :is="readiness.ready ? CheckCircle2 : readiness.steps.exam_unlocked ? Circle : Lock"
                        :size="18"
                        :class="readiness.ready ? 'text-emerald-500' : 'text-muted-foreground'"
                    />
                    <span class="text-sm text-fg">Exam{{ readiness.latest_attempt && !readiness.ready ? ` — last try ${readiness.latest_attempt.score_pct}%` : '' }}</span>
                </div>
                <UiButton v-if="readiness.steps.exam_unlocked" variant="primary" size="sm" @click="goExam">
                    {{ readiness.ready ? 'Retake' : readiness.latest_attempt ? 'Try again' : 'Start exam' }}
                </UiButton>
            </div>
        </div>
    </UiCard>
</template>
