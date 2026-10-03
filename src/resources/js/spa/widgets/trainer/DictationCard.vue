<script setup lang="ts">
import { ref } from 'vue';
import { Check, Lightbulb, Volume2 } from 'lucide-vue-next';
import UiButton from '../../shared/ui/UiButton.vue';
import UiCard from '../../shared/ui/UiCard.vue';
import UiInput from '../../shared/ui/UiInput.vue';
import { exerciseAttemptsApi } from '../../domains/learning';
import type { ExerciseAttempt } from '../../types';

const props = withDefaults(defineProps<{
    contentId: number;
    targetText: string;
    contentLexemeId?: number | null;
    transcriptSegmentId?: number | null;
    replay?: () => void;
}>(), { contentLexemeId: null, transcriptSegmentId: null });

const emit = defineEmits<{ (event: 'submitted', attempt: ExerciseAttempt): void }>();
const answer = ref('');
const hintUsed = ref(false);
const replayCount = ref(0);
const busy = ref(false);
const result = ref<ExerciseAttempt | null>(null);
const submitError = ref('');

function replay() { replayCount.value += 1; props.replay?.(); }

async function submit() {
    if (!answer.value.trim() || busy.value) return;
    busy.value = true;
    submitError.value = '';
    try {
        const payload = new FormData();
        payload.append('content_id', String(props.contentId));
        payload.append('exercise_type', 'dictation');
        payload.append('target_text', props.targetText);
        payload.append('user_text', answer.value);
        payload.append('hint_used', hintUsed.value ? '1' : '0');
        payload.append('replay_count', String(replayCount.value));
        if (props.contentLexemeId) payload.append('content_lexeme_id', String(props.contentLexemeId));
        if (props.transcriptSegmentId) payload.append('transcript_segment_id', String(props.transcriptSegmentId));
        const created = await exerciseAttemptsApi.create(payload);
        result.value = (await exerciseAttemptsApi.waitForCompletion(created.attempt.id)).attempt;
        if (result.value.status === 'failed') throw new Error('The exercise could not be processed.');
        emit('submitted', result.value);
    } catch (error) {
        submitError.value = error instanceof Error ? error.message : 'Unable to check this answer.';
    } finally { busy.value = false; }
}
</script>

<template>
    <UiCard class="space-y-4 p-3 sm:p-5">
        <div class="flex items-center gap-3">
            <UiButton class="min-h-10 shrink-0" size="sm" variant="secondary" @click="replay">
                <Volume2 :size="16" aria-hidden="true" /><span class="hidden sm:inline">Replay audio</span><span v-if="replayCount" class="text-muted-foreground">{{ replayCount }}</span>
            </UiButton>
            <div class="min-w-0 space-y-0.5">
                <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-primary">Dictation</p>
                <h3 class="text-base font-semibold leading-6 text-fg sm:text-lg">Listen and type what you hear</h3>
            </div>
        </div>
        <template v-if="!result">
            <div class="space-y-2">
                <label for="dictation-answer" class="text-sm font-medium text-fg">Your answer</label>
                <div class="dictation-input"><UiInput id="dictation-answer" v-model="answer" placeholder="Type what you hear…" @keyup.enter="submit" /></div>
            </div>
            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
                <UiButton class="min-h-10 justify-center text-muted-foreground sm:justify-start" size="sm" variant="ghost" :aria-pressed="hintUsed" @click="hintUsed = !hintUsed"><Lightbulb :size="16" aria-hidden="true" />{{ hintUsed ? 'Hint used' : 'Use a hint' }}</UiButton>
                <UiButton class="min-h-10 w-full sm:w-auto" size="sm" variant="primary" :disabled="busy || !answer.trim()" @click="submit"><Check :size="16" aria-hidden="true" />{{ busy ? 'Checking…' : 'Check answer' }}</UiButton>
            </div>
        </template>
        <div v-else class="space-y-3 rounded-xl border p-4 text-sm" :class="result.is_correct ? 'border-success/30 bg-success-bg/60' : 'border-warning-border bg-warning-bg/60'">
            <div class="flex items-center justify-between gap-3"><p class="font-semibold" :class="result.is_correct ? 'text-success-fg' : 'text-warning-fg'">{{ result.is_correct ? 'Correct' : 'Keep practicing' }}</p><p class="font-semibold">{{ result.score ?? '—' }}%</p></div>
            <p class="text-base font-medium text-fg"><span class="text-muted-foreground">Correct answer:</span> {{ targetText }}</p>
            <p><span class="text-muted-foreground">Your answer:</span> {{ result.user_text || '—' }}</p>
            <p v-if="result.error_type" class="text-muted-foreground">{{ result.error_type }}</p>
        </div>
        <p v-if="submitError" class="text-sm text-destructive">{{ submitError }}</p>
    </UiCard>
</template>

<style scoped>
.dictation-input :deep(input) {
    min-height: 48px;
    font-size: 16px;
}
</style>
