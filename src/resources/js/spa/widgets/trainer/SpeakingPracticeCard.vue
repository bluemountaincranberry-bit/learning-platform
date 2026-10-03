<script setup lang="ts">
import { ref, watch } from 'vue';
import { Mic } from 'lucide-vue-next';
import UiButton from '../../shared/ui/UiButton.vue';
import UiCard from '../../shared/ui/UiCard.vue';
import UiSwitch from '../../shared/ui/UiSwitch.vue';
import SpeakButton from '../../shared/ui/SpeakButton.vue';
import { isSpeechRecognitionSupported, listenOnce } from '../../shared/lib/speechRecognition';
import { isSpeechSupported, speak } from '../../shared/lib/speech';
import type { SentencePracticeCard, SentencePracticeCheckResponse } from '../../domains/ai';

const props = withDefaults(defineProps<{ card: SentencePracticeCard; busy?: boolean; result: SentencePracticeCheckResponse | null }>(), {
    busy: false,
});

const emit = defineEmits<{
    (e: 'submit', answer: string): void;
    (e: 'next'): void;
}>();

const answer = ref('');
const listeningOnly = ref(false);
const listening = ref(false);
const micError = ref('');

watch(
    () => props.card,
    () => {
        answer.value = '';
        micError.value = '';
    },
);

function submit() {
    if (!answer.value.trim() || props.busy || props.result) return;
    emit('submit', answer.value.trim());
}

async function startListening() {
    micError.value = '';
    listening.value = true;
    try {
        const transcript = await listenOnce(props.card.answer_language);
        answer.value = answer.value.trim() ? `${answer.value.trim()} ${transcript}` : transcript;
    } catch {
        micError.value = "Couldn't hear that — try typing instead.";
    } finally {
        listening.value = false;
    }
}

function replay() {
    speak(props.card.prompt_sentence, props.card.prompt_language);
}
</script>

<template>
    <UiCard class="space-y-4 p-4 sm:space-y-5 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div><div class="text-[11px] font-semibold uppercase tracking-[0.2em] text-primary">Speaking practice</div><p class="mt-1 text-sm text-muted-foreground">Translate the sentence</p></div>
            <UiSwitch v-if="isSpeechSupported()" v-model="listeningOnly" label="Listen only" />
        </div>

        <div class="space-y-3 rounded-spa-lg border border-border bg-surface px-4 py-6 text-center sm:px-8 sm:py-8">
            <p v-if="!listeningOnly" class="text-xl leading-relaxed text-fg">{{ card.prompt_sentence }}</p>
            <p v-else class="text-sm text-muted-foreground">Listen, then write what you heard in {{ card.answer_language }}.</p>
            <div class="flex items-center justify-center gap-2">
                <SpeakButton :text="card.prompt_sentence" :language="card.prompt_language" />
                <UiButton v-if="listeningOnly" variant="ghost" size="sm" @click="replay">Play again</UiButton>
            </div>
            <p v-if="card.hint_words.length" class="text-xs text-muted-foreground">Hint: uses “{{ card.hint_words.join('”, “') }}”</p>
        </div>

        <div v-if="!result" class="space-y-3">
            <div class="flex items-start gap-2">
                <textarea
                    v-model="answer"
                    rows="3"
                    :disabled="busy"
                    :placeholder="`Write it in ${card.answer_language}...`"
                    class="flex w-full flex-1 rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    @keydown.enter.meta="submit"
                />
                <UiButton
                    v-if="isSpeechRecognitionSupported()"
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="h-10 w-10 shrink-0"
                    :disabled="busy || listening"
                    :title="listening ? 'Listening…' : 'Speak your answer'"
                    @click="startListening"
                >
                    <Mic :size="18" :class="listening ? 'animate-pulse text-primary' : ''" />
                </UiButton>
            </div>
            <p v-if="micError" class="text-xs text-warning">{{ micError }}</p>
            <UiButton class="w-full sm:w-auto" variant="primary" :disabled="busy || !answer.trim()" @click="submit">{{ busy ? 'Checking…' : 'Check answer' }}</UiButton>
        </div>

        <div v-else class="space-y-4">
            <div class="space-y-1">
                <div class="text-xs uppercase tracking-[0.14em] text-muted-foreground">Your answer</div>
                <p class="text-fg-secondary">{{ answer }}</p>
            </div>

            <div class="rounded-spa-lg border px-4 py-3 text-sm" :class="result.correct ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-500' : 'border-warning-border bg-warning-bg/70 text-warning-fg'">
                <div class="font-semibold">{{ result.correct ? 'Correct' : 'Needs work' }}</div>
                <p v-if="result.feedback" class="mt-1 text-fg-secondary">{{ result.feedback }}</p>
            </div>

            <div v-if="result.model_answer" class="space-y-1">
                <div class="flex items-center justify-center gap-2">
                    <span class="text-xs uppercase tracking-[0.14em] text-muted-foreground">Example</span>
                    <SpeakButton :text="result.model_answer" :language="card.answer_language" />
                </div>
                <p class="text-lg text-fg">{{ result.model_answer }}</p>
            </div>

            <div class="flex justify-center">
                <UiButton class="w-full sm:w-auto" variant="primary" @click="emit('next')">Next sentence</UiButton>
            </div>
        </div>
    </UiCard>
</template>
