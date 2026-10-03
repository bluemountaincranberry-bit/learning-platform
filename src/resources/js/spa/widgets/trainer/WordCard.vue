<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue';
import { BookOpen, ChevronRight, RotateCw } from 'lucide-vue-next';
import UiButton from '../../shared/ui/UiButton.vue';
import UiCard from '../../shared/ui/UiCard.vue';
import UiBadge from '../../shared/ui/UiBadge.vue';
import SpeakButton from '../../shared/ui/SpeakButton.vue';
import WordExamples from '../../shared/ui/WordExamples.vue';
import TypedAnswerInput from './TypedAnswerInput.vue';
import WordChoiceInput from './WordChoiceInput.vue';
import { groupAssociationsByType } from '../../shared/lexemeAssociations';
import { useAnswerStylePreference, useTrainerSettings } from '../../domains/learning';
import { hasVoiceFor, speak } from '../../shared/lib/speech';
import { useProfileStore } from '../../domains/user';
import type { LexemeAssociationItem, LexemeExampleItem, LexemeDetail } from '../../types/lexeme';
import type { SessionCard } from '../../domains/learning';
import { contentApi } from '../../domains/content';
import { dictionaryApi } from '../../domains/content';

export interface WordCardWord {
    lexeme_display: string;
    /** Canonical dictionary entry id, when known — links to the word detail page. */
    lexeme_id?: number | null;
    part_of_speech?: string | null;
    level?: string | null;
    language?: string | null;
    translation?: string | null;
    example?: string | null;
    examples?: LexemeExampleItem[];
    associations?: LexemeAssociationItem[];
}

const props = withDefaults(
    defineProps<{
        card: SessionCard;
        busy?: boolean;
    }>(),
    { busy: false },
);

const emit = defineEmits<{
    (e: 'grade', payload: { grade: number; hintUsed: boolean }): void;
    (e: 'mark-learned'): void;
    (e: 'start-learning'): void;
    (e: 'reinforce-result', payload: { correct: boolean; hintUsed: boolean }): void;
}>();

const { answerStyle } = useAnswerStylePreference();
const { settings } = useTrainerSettings();
const profileStore = useProfileStore();

/** The learner's own native language (Settings → "Native language"), falling back to Russian to match the backend's default (see ContentService/TrainingSessionService). Drives listen-recognize's translation speech — never hardcoded to a single language. */
const nativeLanguage = computed(() => profileStore.profile?.user.translation_language ?? 'ru');
/** speechSynthesis fails silently with no matching voice — surface it instead of leaving an audio-first card looking broken. */
const nativeVoiceAvailable = computed(() => hasVoiceFor(nativeLanguage.value));

const gradeOptions = [
    { label: 'Again', value: 1 },
    { label: 'Got it', value: 3 },
    { label: 'Easy', value: 5 },
];

const activity = computed(() => props.card.activity);
const word = computed(() => props.card.word);
const associationGroups = computed(() => groupAssociationsByType(word.value.associations));
const partOfSpeechLabel = computed(() => {
    const value = word.value.part_of_speech;
    return value ? value.replaceAll('_', ' ') : null;
});

const activityLabel = computed(
    () =>
        ({
            review: 'Recall',
            learn: 'New word',
            'quick-check': 'Quick check',
            cloze: 'Fill the blank',
            listening: 'Listening',
            'listen-recognize': 'Listen',
        })[activity.value],
);

/** Review's 'reveal' style keeps the old Show-answer -> self-grade flow. Cloze/Listening always need a production step to check correctness, so there they just mean "type it". */
const productionStyle = computed(() => (answerStyle.value === 'tap-letters' ? 'tap-letters' : 'type'));

const revealed = ref(false);
const chosenOption = ref<string | null>(null);
const clozeAttemptValue = ref<string | null>(null);
/** Cloze only, when `hideSentenceUntilTap` is on: the sentence stays behind a tap-to-reveal gate at first. */
/**
 * Holds a quick-check/cloze/listening result between "answered" and the user
 * clicking Continue, so the correct/incorrect feedback text actually has a
 * moment to render before the card changes — emitting 'reinforce-result'
 * used to advance to the next card in the same tick, so it never did.
 */
const pendingResult = ref<{ correct: boolean; hintUsed: boolean; skipped: boolean } | null>(null);
const continuing = ref(false);
const productionHintUsed = ref(false);
const generatedClozeSentence = ref<string | null>(null);
const generatedClozeTarget = ref<string | null>(null);
const generatedClozeChoices = ref<string[]>([]);
const generatedClozeBefore = ref('');
const generatedClozeAfter = ref('');
const generatedClozeTranslation = ref<string | null>(null);
const generatingExample = ref(false);
const generationError = ref('');
const detailsModal = ref<{ loading: boolean; error: string; lexeme: LexemeDetail | null }>({ loading: false, error: '', lexeme: null });
const dragging = ref(false);
const committing = ref(false);
const dragX = ref(0);
const dragY = ref(0);
const pointerId = ref<number | null>(null);
let pointerStartX = 0;
let pointerStartY = 0;
let commitTimer: ReturnType<typeof setTimeout> | null = null;

/** Review's type/tap-letters production step should cue from the translation, not from the very word it's asking you to produce — showing the target word there turns recall into copying. Only applies before the answer is checked (word + pronunciation still appear once revealed, same as before), and only when there's actually a translation to cue from — with none, hiding the word would leave an unanswerable blank prompt, so it falls back to the old behavior. */
const hideWordUntilRevealed = computed(
    () => activity.value === 'review' && answerStyle.value !== 'reveal' && !revealed.value && !!word.value.translation,
);

/** Listen-recognize: sentence audio preferred over the isolated word, matching what a listener actually needs to parse. */
const listenPromptText = computed(() => word.value.example ?? word.value.examples?.[0]?.example ?? word.value.lexeme_display);

/** Cloze's own sentence, reassembled with the real word back in place (the card only ever renders it with a blank) — for hearing it pronounced as a whole, not just the isolated word. */
const clozeAnswer = computed(() => generatedClozeTarget.value ?? word.value.lexeme_display);
const clozeOptions = computed(() => {
    if (generatedClozeChoices.value.length > 0) return generatedClozeChoices.value;
    const options = props.card.options ?? [];
    if (!generatedClozeTarget.value) return options;
    const base = word.value.lexeme_display.trim().toLocaleLowerCase();
    return options.map((option) => option.trim().toLocaleLowerCase() === base ? clozeAnswer.value : option);
});
const clozeFullSentence = computed(() => `${props.card.clozeBefore ?? ''}${word.value.lexeme_display}${props.card.clozeAfter ?? ''}`);
const effectiveClozeSentence = computed(() => generatedClozeSentence.value ?? (props.card.clozeBefore !== undefined ? clozeFullSentence.value : ''));
const hasClozeSentence = computed(() => effectiveClozeSentence.value.trim().length > 0 && effectiveClozeSentence.value !== word.value.lexeme_display);

function splitGeneratedSentence(sentence: string, wordText: string): { before: string; after: string } | null {
    const index = sentence.toLocaleLowerCase().indexOf(wordText.toLocaleLowerCase());
    if (index < 0) return null;
    return { before: sentence.slice(0, index), after: sentence.slice(index + wordText.length) };
}

async function generateClozeExample(force = false) {
    if (!props.card.contentLexemeId || generatingExample.value) return;
    generatingExample.value = true;
    generationError.value = '';
    try {
        const generated = await contentApi.generateSentence(props.card.contentLexemeId, force);
        const parts = splitGeneratedSentence(generated.sentence, generated.target_form);
        if (!parts) throw new Error('The generated example was not usable.');
        generatedClozeTarget.value = generated.target_form;
        generatedClozeChoices.value = [generated.target_form, ...(generated.distractors ?? [])].filter((value, index, values) => values.findIndex((candidate) => candidate.toLocaleLowerCase() === value.toLocaleLowerCase()) === index);
        generatedClozeChoices.value = generatedClozeChoices.value.sort(() => Math.random() - 0.5);
        generatedClozeSentence.value = generated.sentence;
        generatedClozeBefore.value = parts.before;
        generatedClozeAfter.value = parts.after;
        generatedClozeTranslation.value = generated.translation;
    } catch (error) {
        generationError.value = error instanceof Error ? error.message : 'Unable to generate an example sentence.';
    } finally {
        generatingExample.value = false;
    }
}

async function openLexemeDetails() {
    if (!word.value.lexeme_id) return;
    detailsModal.value = { loading: true, error: '', lexeme: null };
    try {
        const data = await dictionaryApi.getOne(word.value.lexeme_id);
        detailsModal.value = { loading: false, error: '', lexeme: data.lexeme };
    } catch {
        detailsModal.value = { loading: false, error: 'Word details are unavailable right now.', lexeme: null };
    }
}

function closeLexemeDetails() {
    detailsModal.value = { loading: false, error: '', lexeme: null };
}

let autoRevealTimer: ReturnType<typeof setTimeout> | null = null;
function clearAutoRevealTimer() {
    if (autoRevealTimer !== null) {
        clearTimeout(autoRevealTimer);
        autoRevealTimer = null;
    }
}

watch(
    () => props.card,
    () => {
        revealed.value = false;
        chosenOption.value = null;
        clozeAttemptValue.value = null;
        pendingResult.value = null;
        continuing.value = false;
        productionHintUsed.value = false;
        generatedClozeSentence.value = null;
        generatedClozeTarget.value = null;
        generatedClozeChoices.value = [];
        generatedClozeBefore.value = '';
        generatedClozeAfter.value = '';
        generatedClozeTranslation.value = null;
        generationError.value = '';
        generatingExample.value = false;
        committing.value = false;
        resetDrag();
        clearAutoRevealTimer();

        if (activity.value === 'cloze') void generateClozeExample();

        if (activity.value === 'listen-recognize') {
            speak(listenPromptText.value, word.value.language);
            if (settings.value.listeningReveal === 'auto') {
                autoRevealTimer = setTimeout(() => revealListenRecognize(), settings.value.autoRevealSeconds * 1000);
            }
        }
    },
    { immediate: true },
);

watch(
    () => props.busy,
    (busy) => {
        // Keep the result visible while the parent submits it. If submission
        // fails and the card stays the same, allow the learner to retry.
        if (!busy && continuing.value && pendingResult.value) continuing.value = false;
    },
);

onUnmounted(() => {
    clearAutoRevealTimer();
    if (commitTimer !== null) clearTimeout(commitTimer);
});

watch(revealed, (value) => {
    if (value && settings.value.autoplayPronunciation) speak(word.value.lexeme_display, word.value.language);
});

const showTranslationPanel = computed(() => {
    if (activity.value === 'learn') return true;
    // Listen-recognize's translation reveal IS the exercise, so it stays immune to the hide-translation setting (which exists to make OTHER activities' reveal panel harder, not to hide the only answer this one has).
    if (activity.value === 'listen-recognize') return revealed.value;
    // A typed answer is already graded. Always show the learning material
    // before continuing, even when the learner hides translations by default.
    if (activity.value === 'review' && pendingResult.value) return true;
    // 'reveal' style's whole flow is built on this panel (click "Show answer" -> read it -> self-grade), so it stays immune to the hide-translation setting.
    if (activity.value === 'review' && answerStyle.value === 'reveal') return revealed.value;
    if ((activity.value === 'review' || activity.value === 'listening') && revealed.value) return settings.value.translationVisible;
    return false;
});

// Cloze always shows the sentence: the blank itself is the exercise, so a
// second reveal gate only adds friction and hides the context needed to answer.
// Swipe hints belong only to the self-grading state. Once an answer result is
// visible, the card is for reading feedback/examples and must not start a swipe.
const swipeEnabled = computed(
    () => ((activity.value === 'review' && revealed.value && !pendingResult.value) || activity.value === 'learn') && !props.busy && !committing.value,
);
const swipeAction = computed<'again' | 'got-it' | 'easy' | 'already-know' | 'learn' | null>(() => {
    if (activity.value === 'learn') {
        if (dragX.value > 24) return 'learn';
        if (dragX.value < -24) return 'already-know';
        return null;
    }
    if (dragY.value > 24 && dragY.value > Math.abs(dragX.value) * 0.75) return 'got-it';
    if (dragX.value > 24) return 'easy';
    if (dragX.value < -24) return 'again';
    return null;
});
const swipeGlow = computed(() => {
    if (swipeAction.value === 'again') return '0 0 0 3px rgb(239 68 68 / 0.18)';
    if (swipeAction.value === 'easy') return '0 0 0 3px rgb(34 197 94 / 0.18)';
    if (swipeAction.value === 'got-it') return '0 0 0 3px rgb(59 130 246 / 0.18)';
    if (swipeAction.value === 'already-know') return '0 0 0 3px rgb(59 130 246 / 0.18)';
    if (swipeAction.value === 'learn') return '0 0 0 3px rgb(124 58 237 / 0.18)';
    return undefined;
});

function onPointerDown(event: PointerEvent) {
    if (!swipeEnabled.value) return;
    const target = event.target as HTMLElement;
    if (target.closest('button, a, input, textarea')) return;
    dragging.value = true;
    pointerId.value = event.pointerId;
    pointerStartX = event.clientX;
    pointerStartY = event.clientY;
    (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
}

function onPointerMove(event: PointerEvent) {
    if (!dragging.value || pointerId.value !== event.pointerId) return;
    event.preventDefault();
    dragX.value = event.clientX - pointerStartX;
    dragY.value = event.clientY - pointerStartY;
}

function resetDrag() {
    dragging.value = false;
    dragX.value = 0;
    dragY.value = 0;
    pointerId.value = null;
}

function onPointerUp(event: PointerEvent) {
    if (!dragging.value || pointerId.value !== event.pointerId) return;
    const x = dragX.value;
    const y = dragY.value;
    const action = swipeAction.value;
    if (!action) {
        resetDrag();
        return;
    }

    committing.value = true;
    dragging.value = false;
    pointerId.value = null;
    if (action === 'again' || action === 'already-know') {
        dragX.value = -window.innerWidth * 0.9;
        dragY.value = y * 0.25;
    } else if (action === 'easy' || action === 'learn') {
        dragX.value = window.innerWidth * 0.9;
        dragY.value = y * 0.25;
    } else {
        dragX.value = x * 0.25;
        dragY.value = window.innerHeight * 0.9;
    }

    commitTimer = setTimeout(() => {
        committing.value = false;
        resetDrag();
        if (activity.value === 'learn') {
            if (action === 'already-know') emit('mark-learned');
            else emit('start-learning');
        } else {
            emit('grade', { grade: action === 'again' ? 1 : action === 'easy' ? 5 : 3, hintUsed: revealed.value });
        }
        commitTimer = null;
    }, 220);
}

function reveal() {
    revealed.value = true;
}

function revealListenRecognize() {
    if (revealed.value) return;
    clearAutoRevealTimer();
    revealed.value = true;
    speak(word.value.translation ?? '', nativeLanguage.value);
}

function onProductionResult(payload: { correct: boolean; value?: string; hintUsed?: boolean; skipped?: boolean }) {
    revealed.value = true;
    if (activity.value === 'cloze') clozeAttemptValue.value = payload.value ?? null;
    productionHintUsed.value = payload.hintUsed ?? false;
    pendingResult.value = {
        correct: payload.correct,
        hintUsed: productionHintUsed.value,
        skipped: payload.skipped ?? false,
    };
}

function optionClass(option: string): string {
    if (!chosenOption.value) return 'border-border bg-black/10 hover:bg-black/20';
    const isCorrect = option === word.value.translation;
    const isChosen = option === chosenOption.value;
    if (isCorrect) return 'border-emerald-500 bg-emerald-500/10';
    if (isChosen) return 'border-warning bg-warning/10';
    return 'border-border opacity-50';
}

function chooseOption(option: string) {
    if (chosenOption.value || props.busy) return;
    chosenOption.value = option;
    pendingResult.value = { correct: option === word.value.translation, hintUsed: false, skipped: false };
}

function continueAfterResult() {
    if (!pendingResult.value || continuing.value) return;
    const { correct, hintUsed } = pendingResult.value;
    continuing.value = true;
    if (activity.value === 'review') {
        emit('grade', { grade: correct ? 3 : 1, hintUsed });
        return;
    }
    emit('reinforce-result', { correct, hintUsed: hintUsed || (activity.value === 'listen-recognize' && revealed.value) });
}
</script>

<template>
    <UiCard
        class="relative mx-auto flex min-h-0 w-full max-w-3xl flex-col space-y-3 overflow-hidden select-none rounded-2xl p-3 sm:space-y-5 sm:p-5 lg:p-7"
        :class="{ 'cursor-grab': swipeEnabled && !dragging, 'cursor-grabbing': dragging }"
        :style="{
            transform: dragging || committing ? `translate(${dragX}px, ${dragY}px) rotate(${dragX / 18}deg)` : undefined,
            opacity: committing ? 0 : 1,
            boxShadow: swipeGlow,
            transition: dragging ? 'none' : 'transform 220ms cubic-bezier(0.22, 1, 0.36, 1), opacity 220ms ease-out, box-shadow 160ms ease-out',
            touchAction: swipeEnabled ? 'none' : 'auto',
        }"
        @pointerdown="onPointerDown"
        @pointermove="onPointerMove"
        @pointerup="onPointerUp"
        @pointercancel="resetDrag"
    >
        <div v-if="dragging" class="pointer-events-none absolute inset-0 z-10 text-xs font-bold uppercase tracking-[0.16em]">
            <span
                class="absolute left-5 top-5 rounded-full px-3 py-1.5 transition-all"
                :class="(swipeAction === 'again' || swipeAction === 'already-know') ? 'bg-destructive/15 text-destructive opacity-100 scale-105' : 'bg-muted text-muted-foreground opacity-45'"
            >
                {{ activity === 'learn' ? 'Already know' : 'Again' }}
            </span>
            <span
                class="absolute right-5 top-5 rounded-full px-3 py-1.5 transition-all"
                :class="(swipeAction === 'easy' || swipeAction === 'learn') ? 'bg-success/15 text-success-fg opacity-100 scale-105' : 'bg-muted text-muted-foreground opacity-45'"
            >
                {{ activity === 'learn' ? 'Learn this word' : 'Easy' }}
            </span>
            <span
                class="absolute bottom-5 left-1/2 -translate-x-1/2 rounded-full px-3 py-1.5 transition-all"
                :class="swipeAction === 'got-it' ? 'bg-primary/15 text-primary opacity-100 scale-105' : 'bg-muted text-muted-foreground opacity-45'"
            >
                Got it
            </span>
        </div>
        <div class="text-[11px] uppercase tracking-[0.28em] text-muted-foreground">{{ activityLabel }}</div>
        <p v-if="card.selectionReason" class="mt-2 text-xs text-muted-foreground">{{ card.selectionReason }}</p>

        <div class="flex min-h-0 flex-1 flex-col justify-center overflow-y-auto overflow-x-hidden rounded-spa-lg border border-border bg-surface px-3 py-5 text-center sm:px-8 sm:py-8 lg:px-12 lg:py-10">
            <template v-if="activity === 'cloze'">
                <div v-if="!hasClozeSentence" class="space-y-3">
                    <p class="text-sm text-muted-foreground">{{ generatingExample ? 'Generating a practice sentence…' : 'We could not generate a sentence yet.' }}</p>
                    <UiButton variant="primary" :disabled="generatingExample || !card.contentLexemeId" @click="generateClozeExample">
                        <RotateCw :size="16" aria-hidden="true" />
                        {{ generatingExample ? 'Generating…' : 'Try again' }}
                    </UiButton>
                </div>
                <div class="space-y-3">
                    <div class="flex items-center justify-center gap-2">
                        <p class="min-w-0 text-xl leading-relaxed text-fg">
                            {{ generatedClozeSentence ? generatedClozeBefore : card.clozeBefore }}<span
                                v-if="clozeAttemptValue"
                                class="mx-1 inline-block border-b-2 border-primary font-semibold text-primary align-bottom"
                            >{{ clozeAttemptValue }}</span><span v-else class="mx-1 inline-block min-w-[4.5rem] border-b-2 border-primary align-bottom">&nbsp;</span>{{ generatedClozeSentence ? generatedClozeAfter : card.clozeAfter }}
                        </p>
                        <!-- Only once answered — hearing the missing word spoken before that would give away spelling practice for free. -->
                        <SpeakButton v-if="revealed" :text="effectiveClozeSentence" :language="word.language" />
                    </div>
                    <UiButton size="sm" variant="ghost" :disabled="generatingExample || !card.contentLexemeId" @click="generateClozeExample(true)">
                        <RotateCw :size="15" aria-hidden="true" :class="generatingExample ? 'animate-spin' : ''" />
                        {{ generatingExample ? 'Generating…' : 'Regenerate example' }}
                    </UiButton>
                </div>
                <p v-if="generationError" class="mt-3 text-sm text-destructive">{{ generationError }}</p>
                <p v-if="generatedClozeTranslation" class="mt-3 text-sm text-muted-foreground">{{ generatedClozeTranslation }}</p>
            </template>

            <template v-else-if="activity === 'listening'">
                <div class="flex items-center justify-center gap-2">
                    <SpeakButton :text="word.lexeme_display" :language="word.language" />
                    <span v-if="!revealed" class="text-sm text-muted-foreground">Listen, then answer</span>
                </div>
            </template>

            <template v-else-if="activity === 'listen-recognize'">
                <button
                    type="button"
                    class="flex min-h-[7rem] w-full flex-col items-center justify-center gap-3 rounded-spa border border-dashed border-border px-4 py-6 transition-colors hover:bg-black/10 disabled:cursor-default"
                    :disabled="busy || revealed"
                    @click="revealListenRecognize"
                >
                    <SpeakButton :text="listenPromptText" :language="word.language" />
                    <span v-if="!revealed" class="text-sm text-muted-foreground">
                        {{ settings.listeningReveal === 'auto' ? 'Listening… tap to reveal early' : 'Tap to reveal' }}
                    </span>
                    <span v-else class="text-2xl font-semibold text-fg">{{ word.translation }}</span>
                    <span v-if="revealed && !nativeVoiceAvailable" class="text-xs text-muted-foreground">(no voice installed for this language — showing text only)</span>
                </button>
            </template>

            <template v-else-if="!(activity === 'review' && pendingResult)">
                <div v-if="hideWordUntilRevealed" class="space-y-1">
                    <div class="break-words text-xl font-semibold leading-tight text-fg sm:text-2xl">{{ word.translation }}</div>
                </div>
                <div v-else class="flex items-center justify-center gap-2">
                    <div class="break-words text-2xl font-semibold leading-tight text-fg sm:text-3xl lg:text-4xl">{{ word.lexeme_display }}</div>
                    <SpeakButton :text="word.lexeme_display" :language="word.language" />
                </div>
            </template>

            <div v-if="activity === 'listening' && revealed" class="mt-3 break-words text-2xl font-semibold leading-tight text-fg sm:text-3xl">{{ word.lexeme_display }}</div>
            <div v-if="activity === 'listen-recognize' && revealed" class="mt-3 text-lg text-fg-secondary">{{ word.lexeme_display }}</div>

            <div v-if="partOfSpeechLabel || word.level" class="mt-3 flex flex-wrap items-center justify-center gap-1.5">
                <UiBadge v-if="partOfSpeechLabel" tone="neutral">{{ partOfSpeechLabel }}</UiBadge>
                <UiBadge v-if="word.level" tone="neutral">{{ word.level }}</UiBadge>
            </div>

            <div
                v-if="activity === 'review' && pendingResult"
                class="mx-auto mt-4 w-full max-w-xl rounded-xl px-4 py-3 text-center"
                :class="pendingResult.correct ? '' : 'border border-warning-border bg-warning-bg/45'"
                role="status"
            >
                <p
                    class="text-[11px] font-semibold uppercase tracking-[0.18em]"
                    :class="pendingResult.correct ? 'text-success-fg' : 'text-warning-fg'"
                >
                    {{ pendingResult.correct ? (pendingResult.hintUsed ? 'Correct with a hint' : 'Correct') : pendingResult.skipped ? 'Let’s learn this one' : 'Not quite' }}
                </p>
                <p class="mt-3 break-words text-2xl font-semibold leading-tight text-fg sm:text-3xl">{{ word.lexeme_display }}</p>
            </div>

            <template v-if="showTranslationPanel">
                <div v-if="word.translation && activity !== 'listen-recognize'" class="mt-3 break-words text-base font-medium text-fg-secondary sm:text-lg">{{ word.translation }}</div>

                <div v-if="associationGroups.length > 0" class="mt-4 space-y-1.5">
                    <div v-for="group in associationGroups" :key="group.type" class="flex flex-wrap items-center justify-center gap-1.5">
                        <span class="text-xs text-muted-foreground">{{ group.label }}:</span>
                        <UiBadge v-for="item in group.items" :key="item" :tone="group.tone">{{ item }}</UiBadge>
                    </div>
                </div>

                <div v-if="(word.examples && word.examples.length > 0) || word.example" class="mt-5 w-full text-left">
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <span class="text-[11px] font-semibold uppercase tracking-[0.16em] text-muted-foreground">Example{{ word.examples && word.examples.length > 1 ? 's' : '' }}</span>
                        <span class="text-[11px] text-primary">Tap a sentence to translate</span>
                    </div>
                    <WordExamples
                        :examples="word.examples"
                        :fallback-example="word.example"
                        :language="word.language"
                        :truncate="false"
                        :collapsible="false"
                    />
                </div>

                <UiButton
                    v-if="word.lexeme_id"
                    variant="secondary"
                    size="sm"
                    class="mt-4 w-full sm:w-auto"
                    @click="openLexemeDetails"
                >
                    <BookOpen :size="16" aria-hidden="true" />
                    <span>View word details</span>
                    <ChevronRight :size="15" class="text-primary" aria-hidden="true" />
                </UiButton>
            </template>
        </div>

        <div class="shrink-0 space-y-3 border-t border-border pt-3 sm:pt-4">
            <template v-if="activity === 'review'">
                <template v-if="answerStyle === 'reveal'">
                    <UiButton v-if="!revealed" class="w-full sm:w-auto" variant="primary" :disabled="busy" @click="reveal">Show answer</UiButton>
                </template>
                <TypedAnswerInput v-else-if="!revealed" :target="word.lexeme_display" :style="productionStyle" :busy="busy" @result="onProductionResult" />

                <div v-if="revealed && !pendingResult" class="flex flex-wrap justify-center gap-2">
                    <UiButton
                        v-for="opt in gradeOptions"
                        :key="opt.value"
                        :variant="opt.value === 1 ? 'danger' : opt.value === 5 ? 'success' : 'primary'"
                        class="min-w-0 flex-1 sm:flex-none"
                        :disabled="busy"
                        @pointerdown.stop
                        @click="emit('grade', { grade: opt.value, hintUsed: productionHintUsed })"
                    >
                        {{ opt.label }}
                    </UiButton>
                </div>
            </template>

            <template v-else-if="activity === 'learn'">
                <div class="grid grid-cols-2 gap-2 sm:flex sm:justify-center">
                    <UiButton class="w-full sm:w-auto" variant="secondary" :disabled="busy" @click="emit('mark-learned')">Already know</UiButton>
                    <UiButton class="w-full sm:w-auto" variant="primary" :disabled="busy" @click="emit('start-learning')">Learn this word</UiButton>
                </div>
            </template>

            <template v-else-if="activity === 'quick-check'">
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <button
                        v-for="opt in card.options"
                        :key="opt"
                        type="button"
                        class="rounded-spa border p-3 text-sm font-medium text-fg transition-colors"
                        :class="optionClass(opt)"
                        :disabled="!!chosenOption || busy"
                        @click="chooseOption(opt)"
                    >
                        {{ opt }}
                    </button>
                </div>
            </template>

            <template v-else-if="activity === 'cloze' && hasClozeSentence && answerStyle === 'choose-word' && clozeOptions.length">
                <WordChoiceInput :target="clozeAnswer" :options="clozeOptions" :busy="busy" @result="onProductionResult" />
            </template>

            <template v-else-if="activity === 'cloze' && !hasClozeSentence" />

            <template v-else-if="activity === 'listen-recognize'">
                <div v-if="revealed" class="flex flex-wrap justify-center gap-2">
                    <UiButton variant="secondary" :disabled="busy" @click="emit('reinforce-result', { correct: false, hintUsed: activity === 'listen-recognize' && revealed })">Didn't know</UiButton>
                    <UiButton variant="primary" :disabled="busy" @click="emit('reinforce-result', { correct: true, hintUsed: activity === 'listen-recognize' && revealed })">Knew it</UiButton>
                </div>
            </template>

            <template v-else-if="activity !== 'cloze' || hasClozeSentence">
                <TypedAnswerInput :target="activity === 'cloze' ? clozeAnswer : word.lexeme_display" :style="productionStyle" :busy="busy" @result="onProductionResult" />
            </template>

            <div v-if="pendingResult" class="flex justify-center">
                <UiButton class="w-full sm:w-auto" variant="primary" :disabled="busy || continuing" @click="continueAfterResult">Continue</UiButton>
            </div>
        </div>

        <div
            v-if="detailsModal.loading || detailsModal.error || detailsModal.lexeme"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-3 sm:items-center sm:p-6"
            role="dialog"
            aria-modal="true"
            aria-labelledby="word-details-title"
            @click.self="closeLexemeDetails"
        >
            <div class="max-h-[85dvh] w-full max-w-xl overflow-y-auto rounded-2xl border border-border bg-surface p-5 text-left shadow-2xl sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.16em] text-primary">Word details</div>
                        <h2 id="word-details-title" class="mt-1 text-xl font-semibold text-fg">{{ detailsModal.lexeme?.lemma ?? word.lexeme_display }}</h2>
                    </div>
                    <UiButton variant="ghost" size="sm" @click="closeLexemeDetails">Close</UiButton>
                </div>

                <div v-if="detailsModal.loading" class="mt-6 space-y-3" aria-busy="true">
                    <div class="h-4 w-32 animate-pulse rounded bg-muted"></div>
                    <div class="h-3 w-2/3 animate-pulse rounded bg-muted/70"></div>
                </div>
                <div v-else-if="detailsModal.error" class="mt-6 rounded-spa border border-amber-500/20 bg-amber-500/10 p-3 text-sm text-amber-800" role="alert">
                    {{ detailsModal.error }}
                </div>
                <div v-else-if="detailsModal.lexeme" class="mt-5 space-y-5">
                    <div class="flex flex-wrap items-center gap-2">
                        <SpeakButton :text="detailsModal.lexeme.lemma" :language="detailsModal.lexeme.language" />
                        <UiBadge tone="neutral">{{ detailsModal.lexeme.language }}</UiBadge>
                        <UiBadge v-if="detailsModal.lexeme.part_of_speech" tone="neutral">{{ detailsModal.lexeme.part_of_speech }}</UiBadge>
                        <UiBadge v-if="detailsModal.lexeme.level" tone="primary">{{ detailsModal.lexeme.level }}</UiBadge>
                    </div>
                    <div class="text-lg font-medium text-fg-secondary">
                        {{ detailsModal.lexeme.translations.map((translation) => translation.translation).join(', ') || 'No translation available' }}
                    </div>
                    <div v-if="detailsModal.lexeme.associations.length" class="flex flex-wrap gap-1.5">
                        <UiBadge v-for="association in detailsModal.lexeme.associations" :key="`${association.type}-${association.lemma}`" tone="neutral">
                            {{ association.lemma }}
                        </UiBadge>
                    </div>
                    <div v-if="detailsModal.lexeme.examples.length" class="space-y-2">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-muted-foreground">Examples</div>
                        <WordExamples :examples="detailsModal.lexeme.examples" :collapsible="false" :language="detailsModal.lexeme.language" :truncate="false" />
                    </div>
                </div>
            </div>
        </div>
    </UiCard>
</template>
