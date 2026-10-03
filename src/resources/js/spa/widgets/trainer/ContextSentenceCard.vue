<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Mic } from 'lucide-vue-next';
import UiButton from '../../shared/ui/UiButton.vue';
import UiCard from '../../shared/ui/UiCard.vue';
import SpeakButton from '../../shared/ui/SpeakButton.vue';
import { isSpeechRecognitionSupported, listenOnce } from '../../shared/lib/speechRecognition';
import type { ContextCard } from '../../domains/content';

const props = withDefaults(defineProps<{ card: ContextCard; busy?: boolean; generating?: boolean }>(), {
    busy: false,
    generating: false,
});

const emit = defineEmits<{
    (e: 'result', correct: boolean): void;
    (e: 'regenerate'): void;
}>();

/** 'prompt': reading the native sentence, producing a translation. 'revealed': comparing against the real one and self-grading — same reveal-then-self-grade shape as the rest of the trainer (Review's reveal style, listen-recognize). */
const phase = ref<'prompt' | 'revealed'>('prompt');
const attempt = ref('');
const listening = ref(false);
const micError = ref('');

watch(
    () => props.card,
    () => {
        phase.value = 'prompt';
        attempt.value = '';
        micError.value = '';
    },
);

/**
 * Splits the reference sentence around the target word so it can be
 * highlighted once revealed. Tries an exact match first; if the word only
 * appears inflected (e.g. lexeme "learn" inside "learning"), falls back to
 * matching a word-boundary token that starts with the lexeme's stem — still
 * heuristic (no per-language morphology), but covers the common case of a
 * shared prefix instead of giving up and highlighting nothing.
 */
function findTargetMatch(sentence: string, word: string): { start: number; end: number } | null {
    const lower = sentence.toLowerCase();
    const wLower = word.toLowerCase();
    const exact = lower.indexOf(wLower);
    if (exact !== -1) return { start: exact, end: exact + word.length };

    const stem = wLower.slice(0, Math.max(4, Math.ceil(wLower.length * 0.7)));
    if (stem.length < 3) return null;
    const escaped = stem.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const match = sentence.match(new RegExp(`\\b${escaped}\\p{L}*`, 'iu'));
    if (!match || match.index === undefined) return null;
    return { start: match.index, end: match.index + match[0].length };
}

const targetParts = computed(() => {
    const range = findTargetMatch(props.card.sentenceTarget, props.card.lexemeDisplay);
    if (!range) return { before: '', match: '', after: props.card.sentenceTarget };
    return {
        before: props.card.sentenceTarget.slice(0, range.start),
        match: props.card.sentenceTarget.slice(range.start, range.end),
        after: props.card.sentenceTarget.slice(range.end),
    };
});

/** Strips punctuation/diacritics and splits into a word set — used only for the similarity nudge below, not for correctness (translations legitimately vary in wording). */
function wordSet(sentence: string): Set<string> {
    return new Set(
        sentence
            .toLowerCase()
            .normalize('NFD')
            .replace(/[̀-ͯ]/g, '')
            .replace(/[^\p{L}\p{N}\s]/gu, '')
            .split(/\s+/)
            .filter(Boolean),
    );
}

/** Word-overlap ratio between the learner's attempt and the reference sentence — a rough, forgiving similarity signal (word order and synonyms aren't accounted for) used only to *suggest* a grade, never to pick one automatically. Sentence-level correctness has too many valid phrasings to auto-decide. */
const suggestedGrade = computed<'correct' | 'unsure' | null>(() => {
    if (phase.value !== 'revealed') return null;
    const a = wordSet(attempt.value);
    const b = wordSet(props.card.sentenceTarget);
    if (a.size === 0 || b.size === 0) return null;
    let overlap = 0;
    for (const w of a) if (b.has(w)) overlap++;
    const ratio = overlap / Math.max(a.size, b.size);
    return ratio >= 0.6 ? 'correct' : 'unsure';
});

function reveal() {
    if (!attempt.value.trim()) return;
    phase.value = 'revealed';
}

async function startListening() {
    micError.value = '';
    listening.value = true;
    try {
        const transcript = await listenOnce(props.card.language);
        attempt.value = attempt.value.trim() ? `${attempt.value.trim()} ${transcript}` : transcript;
    } catch {
        micError.value = "Couldn't hear that — try typing instead.";
    } finally {
        listening.value = false;
    }
}

function grade(correct: boolean) {
    emit('result', correct);
}

function regenerate() {
    emit('regenerate');
}
</script>

<template>
    <UiCard class="space-y-4 p-4 sm:space-y-5 sm:p-6">
        <div><div class="text-[11px] font-semibold uppercase tracking-[0.2em] text-primary">Context translation</div><p class="mt-1 text-sm text-muted-foreground">Use the target word in your answer</p></div>

        <div v-if="card.hasExample" class="space-y-3 rounded-spa-lg border border-border bg-surface px-4 py-6 text-center sm:px-8 sm:py-8">
            <p class="text-xl leading-relaxed text-fg">{{ card.sentenceNative }}</p>
            <p class="text-xs text-muted-foreground">Translate this sentence — try to use “{{ card.lexemeDisplay }}”</p>
        </div>

        <div v-else class="space-y-3 rounded-spa-lg border border-dashed border-border bg-surface px-6 py-8 text-center">
            <div class="space-y-1">
                <p class="text-sm font-medium text-fg">No example sentence for “{{ card.lexemeDisplay }}” yet</p>
                <p class="text-xs leading-5 text-muted-foreground">
                    Generate a sentence with AI and its translation to start this exercise.
                </p>
            </div>
            <UiButton variant="primary" size="sm" :disabled="generating" @click="regenerate">
                {{ generating ? 'Generating with AI…' : 'Generate with AI' }}
            </UiButton>
        </div>

        <template v-if="!card.hasExample" />

        <div v-else-if="phase === 'prompt'" class="space-y-3">
            <div class="flex items-start gap-2">
                <textarea
                    v-model="attempt"
                    rows="3"
                    :disabled="busy"
                    placeholder="Type your translation..."
                    class="flex w-full flex-1 rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
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
            <div class="flex flex-wrap items-center gap-3">
                <UiButton class="w-full sm:w-auto" variant="primary" :disabled="busy || !attempt.trim()" @click="reveal">Check answer</UiButton>
                <button
                    type="button"
                    class="text-xs text-muted-foreground underline-offset-2 hover:underline disabled:opacity-50"
                    :disabled="generating || busy"
                    @click="regenerate"
                >
                    {{ generating ? 'Generating with AI…' : 'Try another AI sentence' }}
                </button>
            </div>
        </div>

        <div v-else class="space-y-4">
            <div class="space-y-1">
                <div class="text-xs uppercase tracking-[0.14em] text-muted-foreground">Your answer</div>
                <p class="text-fg-secondary">{{ attempt }}</p>
            </div>

            <div class="space-y-1">
                <div class="flex items-center justify-center gap-2">
                    <span class="text-xs uppercase tracking-[0.14em] text-muted-foreground">Reference</span>
                    <SpeakButton :text="card.sentenceTarget" :language="card.language" />
                </div>
                <p class="text-lg text-fg">{{ targetParts.before }}<strong class="text-primary">{{ targetParts.match }}</strong>{{ targetParts.after }}</p>
            </div>

            <p v-if="suggestedGrade" class="text-center text-xs text-muted-foreground">
                {{ suggestedGrade === 'correct' ? 'Looks close to the reference — but you be the judge.' : 'Quite different from the reference — worth another look?' }}
            </p>

            <div class="flex flex-wrap justify-center gap-2">
                <UiButton
                    variant="secondary"
                    class="min-w-0 flex-1 sm:flex-none"
                    :class="suggestedGrade === 'unsure' ? 'ring-2 ring-warning' : ''"
                    :disabled="busy"
                    @click="grade(false)"
                >
                    Needs work
                </UiButton>
                <UiButton
                    variant="primary"
                    class="min-w-0 flex-1 sm:flex-none"
                    :class="suggestedGrade === 'correct' ? 'ring-2 ring-emerald-500' : ''"
                    :disabled="busy"
                    @click="grade(true)"
                >
                    Nailed it
                </UiButton>
            </div>
        </div>
    </UiCard>
</template>
