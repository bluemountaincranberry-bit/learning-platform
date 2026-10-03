<script setup lang="ts">
import { ref } from 'vue';
import { Check, Lightbulb, Mic, Play, Square } from 'lucide-vue-next';
import UiButton from '../../shared/ui/UiButton.vue';
import UiCard from '../../shared/ui/UiCard.vue';
import { exerciseAttemptsApi, useAudioRecorder } from '../../domains/learning';
import type { ExerciseAttempt } from '../../types';

const props = withDefaults(defineProps<{
    contentId: number;
    targetText: string;
    contentLexemeId?: number | null;
    transcriptSegmentId?: number | null;
    play?: () => void;
    showTarget?: boolean;
}>(), { contentLexemeId: null, transcriptSegmentId: null, showTarget: true });
const emit = defineEmits<{ (event: 'submitted', attempt: ExerciseAttempt): void }>();
const { isRecording, elapsedSeconds, audioBlob, error: recorderError, start, stop, reset } = useAudioRecorder();
const busy = ref(false);
const result = ref<ExerciseAttempt | null>(null);
const hintUsed = ref(false);
const submitError = ref('');

async function submit() {
    if (!audioBlob.value || busy.value) return;
    busy.value = true;
    submitError.value = '';
    try {
        const payload = new FormData();
        payload.append('content_id', String(props.contentId));
        payload.append('exercise_type', 'shadowing');
        payload.append('target_text', props.targetText);
        payload.append('hint_used', hintUsed.value ? '1' : '0');
        if (props.contentLexemeId) payload.append('content_lexeme_id', String(props.contentLexemeId));
        if (props.transcriptSegmentId) payload.append('transcript_segment_id', String(props.transcriptSegmentId));
        const extension = audioBlob.value.type.includes('wav') ? 'wav' : audioBlob.value.type.includes('mp4') ? 'mp4' : 'webm';
        payload.append('audio', audioBlob.value, `shadowing.${extension}`);
        const created = await exerciseAttemptsApi.create(payload);
        result.value = (await exerciseAttemptsApi.waitForCompletion(created.attempt.id)).attempt;
        if (result.value.status === 'failed') throw new Error('The exercise could not be processed.');
        emit('submitted', result.value);
    } catch (error) {
        submitError.value = error instanceof Error ? error.message : 'Unable to check pronunciation.';
    } finally { busy.value = false; }
}
</script>

<template>
    <UiCard class="space-y-4 p-3 sm:p-5">
        <div class="flex items-center gap-3">
            <UiButton class="min-h-10 shrink-0" size="sm" variant="secondary" @click="props.play?.()"><Play :size="16" aria-hidden="true" /><span class="hidden sm:inline">Play phrase</span></UiButton>
            <div class="min-w-0 space-y-0.5">
                <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-primary">Shadowing</p>
                <h3 class="text-base font-semibold leading-6 text-fg sm:text-lg">Listen, then repeat the phrase</h3>
            </div>
        </div>
        <p v-if="showTarget" class="rounded-lg bg-surface-alt/60 px-3 py-2.5 text-sm leading-6 text-fg">{{ targetText }}</p>
        <template v-if="!result">
            <div class="grid gap-2 sm:grid-cols-2">
                <UiButton v-if="!isRecording && !audioBlob" class="min-h-11 w-full sm:col-span-2" size="sm" variant="primary" @click="start"><Mic :size="16" aria-hidden="true" />Record your voice</UiButton>
                <UiButton v-else-if="isRecording" class="min-h-11 w-full sm:col-span-2" size="sm" variant="danger" @click="stop"><Square :size="15" aria-hidden="true" />Stop recording · {{ elapsedSeconds }}s</UiButton>
                <template v-else>
                    <UiButton class="min-h-11 w-full" size="sm" variant="secondary" @click="reset"><Mic :size="16" aria-hidden="true" />Record again</UiButton>
                    <UiButton class="min-h-11 w-full" size="sm" variant="primary" :disabled="busy" @click="submit"><Check :size="16" aria-hidden="true" />{{ busy ? 'Checking…' : 'Check pronunciation' }}</UiButton>
                </template>
            </div>
            <UiButton class="min-h-10 px-0 text-muted-foreground" size="sm" variant="ghost" :aria-pressed="hintUsed" @click="hintUsed = !hintUsed"><Lightbulb :size="16" aria-hidden="true" />{{ hintUsed ? 'Hint used' : 'Use a hint' }}</UiButton>
        </template>
        <p v-if="recorderError" class="text-sm text-destructive">{{ recorderError }}</p>
        <div v-if="result" class="space-y-3 rounded-xl border border-primary/20 bg-primary/10 p-4 text-sm">
            <div class="flex items-center justify-between gap-3"><p class="font-semibold">Pronunciation result</p><p class="font-semibold">{{ result.score ?? '—' }}%</p></div>
            <p class="text-base font-medium text-fg">{{ targetText }}</p>
            <p><span class="text-muted-foreground">Recognized:</span> {{ result.user_text || '—' }}</p>
            <div v-if="result.provider_result?.pronunciation" class="grid grid-cols-2 gap-2 text-xs text-muted-foreground"><span>Accuracy {{ result.provider_result.pronunciation.accuracy ?? '—' }}</span><span>Fluency {{ result.provider_result.pronunciation.fluency ?? '—' }}</span><span>Completeness {{ result.provider_result.pronunciation.completeness ?? '—' }}</span><span>Prosody {{ result.provider_result.pronunciation.prosody ?? '—' }}</span></div>
        </div>
        <p v-if="submitError" class="text-sm text-destructive">{{ submitError }}</p>
    </UiCard>
</template>
