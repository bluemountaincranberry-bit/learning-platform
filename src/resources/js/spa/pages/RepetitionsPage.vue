<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { BookOpen, CheckSquare, ChevronDown, ChevronLeft, ClipboardCheck, Eye, Headphones, Keyboard, Languages, ListChecks, Mic2, PenLine, Puzzle, Settings, SlidersHorizontal, Sparkles, Volume2 } from 'lucide-vue-next';
import PageState from '../components/ui/PageState.vue';
import { useAuthStore } from '../domains/user';
import { useTrainingSession, useAnswerStylePreference, useTrainerSettings, type FocusedPracticeMode } from '../domains/learning';
import { learningFlowApi, type LearningFlowResponse } from '../domains/learning';
import { isSpeechSupported, listVoices } from '../shared/lib/speech';
import UiButton from '../shared/ui/UiButton.vue';
import UiCard from '../shared/ui/UiCard.vue';
import UiEmptyState from '../shared/ui/UiEmptyState.vue';
import UiSectionHeader from '../shared/ui/UiSectionHeader.vue';
import UiSwitch from '../shared/ui/UiSwitch.vue';
import SelectField from '../shared/ui/SelectField.vue';
import WordCard from '../widgets/trainer/WordCard.vue';
import DictationCard from '../widgets/trainer/DictationCard.vue';
import ShadowingCard from '../widgets/trainer/ShadowingCard.vue';
import { speak } from '../shared/lib/speech';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const { answerStyle } = useAnswerStylePreference();
const { settings } = useTrainerSettings();

const {
    phase,
    error,
    busy,
    currentCard,
    currentIndex,
    sessionTotal,
    scopeLabel,
    catalogContents,
    reviewedCount,
    learnedCount,
    addedToReviewCount,
    reinforceCorrect,
    reinforceTotal,
    ensureCatalogLoaded,
    startSession,
    submitReviewGrade,
    submitLearnMarkLearned,
    submitLearnStartLearning,
    submitReinforceResult,
    prepareClozeExamples,
    restart,
} = useTrainingSession();

const cardNumber = computed(() => currentIndex.value + 1);

const answerStyleOptions = [
    { value: 'reveal', label: 'Reveal & self-grade' },
    { value: 'type', label: 'Type it' },
    { value: 'tap-letters', label: 'Tap the letters' },
    { value: 'choose-word', label: 'Choose the word (Cloze)' },
];

const listeningRevealOptions = [
    { value: 'tap', label: 'Tap to reveal' },
    { value: 'auto', label: 'Auto-reveal after a few seconds' },
];

const autoRevealSecondsOptions = [
    { value: '2', label: '2s' },
    { value: '4', label: '4s' },
    { value: '6', label: '6s' },
    { value: '8', label: '8s' },
];

const speechRateOptions = [
    { value: '0.75', label: 'Slow' },
    { value: '1', label: 'Normal' },
    { value: '1.25', label: 'Fast' },
];

// Voices load asynchronously in most browsers (empty on first call, populated
// once 'voiceschanged' fires) — kept as local reactive state refreshed on
// that event rather than read once, so the picker below isn't empty forever.
const availableVoices = ref<SpeechSynthesisVoice[]>(listVoices());
function refreshVoices() {
    availableVoices.value = listVoices();
}
onMounted(() => {
    if (isSpeechSupported()) window.speechSynthesis.addEventListener('voiceschanged', refreshVoices);
});
onUnmounted(() => {
    if (isSpeechSupported()) window.speechSynthesis.removeEventListener('voiceschanged', refreshVoices);
});

const voiceOptions = computed(() => [
    { value: '', label: 'Browser default' },
    ...availableVoices.value.map((v) => ({ value: v.voiceURI, label: `${v.name} (${v.lang})` })),
]);

const speechRateInput = computed({
    get: () => String(settings.value.speechRate),
    set: (value: string) => {
        settings.value.speechRate = Number(value);
    },
});

const speechVoiceInput = computed({
    get: () => settings.value.speechVoiceURI ?? '',
    set: (value: string) => {
        settings.value.speechVoiceURI = value || null;
    },
});

// A single adaptive session replaces the old mode-then-content setup screen
// (Task: full trainer redesign). This stays collapsed by default — power
// users can still jump to a specific content or a different answer style
// without leaving the page, but nobody has to pick anything to start.
const showCustomize = ref(false);
const customizeContentId = ref('');
const showSettings = ref(false);
const sessionStarted = ref(false);
const learningFlow = ref<LearningFlowResponse | null>(null);
const learningFlowModalOpen = ref(false);
const selectedFlowId = ref('');
const savingLearningFlow = ref(false);
const learningFlowMessage = ref('');
const showFlowDetails = ref(false);
const exerciseMode = ref<'dictation' | 'shadowing' | null>(null);
const showMorePractice = ref(false);
const preparingCloze = ref(false);
const clozePrepareMessage = ref('');

async function prepareSessionClozeExamples() {
    if (preparingCloze.value) return;
    preparingCloze.value = true;
    clozePrepareMessage.value = '';
    try {
        const result = await prepareClozeExamples();
        clozePrepareMessage.value = result.prepared > 0 ? `${result.prepared} examples prepared` : 'Examples are already prepared';
    } catch {
        clozePrepareMessage.value = 'Could not prepare examples';
    } finally {
        preparingCloze.value = false;
    }
}
const selectedPracticeMode = computed<FocusedPracticeMode>(() => {
    const value = route.query.mode as string | undefined;
    return value === 'cloze' || value === 'listening' ? value : 'adaptive';
});
const selectedAnswerStyle = computed(() => {
    const value = route.query.answer_style as string | undefined;
    return value === 'reveal' || value === 'type' || value === 'tap-letters' || value === 'choose-word' ? value : null;
});
const modeLabel = computed(() => selectedAnswerStyle.value
    ? ({ reveal: 'Recall practice', type: 'Type practice', 'tap-letters': 'Tap letters practice', 'choose-word': 'Cloze practice' }[selectedAnswerStyle.value])
    : ({ adaptive: 'Adaptive practice', cloze: 'Cloze practice', listening: 'Listening practice' }[selectedPracticeMode.value]));
const focusedMode = computed(() => selectedPracticeMode.value !== 'adaptive' || selectedAnswerStyle.value !== null);
const showRecallSettings = computed(() => !focusedMode.value || selectedAnswerStyle.value !== null || selectedPracticeMode.value === 'cloze');
const showListeningSettings = computed(() => !focusedMode.value || selectedPracticeMode.value === 'listening');
const exerciseTarget = computed(() => currentCard.value?.word.example ?? currentCard.value?.word.lexeme_display ?? '');

const activeFlow = computed(() => learningFlow.value?.profile ?? null);
const activeFlowConfig = computed(() => (learningFlow.value?.config ?? {}) as Record<string, unknown>);
const activeFlowDescription = computed(() => {
    const descriptions: Record<string, string> = {
        balanced: 'Alternates new words, review, context and listening in a balanced mix.',
        'listening-first': 'Puts more weight on listening comprehension and dictation.',
        'speaking-first': 'Moves words into active speech and production sooner.',
        'fast-vocabulary': 'Short sessions and fast growth of useful vocabulary.',
        'deep-mastery': 'Slower but deeper: more context, production and reinforcement.',
    };
    return descriptions[activeFlow.value?.slug ?? ''] ?? 'A personal practice order for your goal.';
});

function flowDescription(slug: string): string {
    const descriptions: Record<string, string> = {
        balanced: 'Balanced progress every day.',
        'listening-first': 'More understanding of real speech and dictation.',
        'speaking-first': 'More active speech and production.',
        'fast-vocabulary': 'Build useful vocabulary faster.',
        'deep-mastery': 'Deep reinforcement through context.',
    };
    return descriptions[slug] ?? 'Adaptive practice tuned to your goal.';
}

const selectedFlowProfile = computed(() => learningFlow.value?.available_profiles.find((profile) => String(profile.id) === selectedFlowId.value) ?? activeFlow.value);
const selectedFlowDetails = computed(() => {
    const details: Record<string, { goal: string; sequence: string; algorithm: string; bestFor: string; tradeoff: string }> = {
        balanced: {
            goal: 'Steady progress without leaning on one skill.',
            sequence: 'Meet the word → recognition → recall → production → listening → speaking.',
            algorithm: 'The system finds the weakest skill for each word and offers exercises for it more often. New words get a gentle introduction, and familiar ones gradually move to active use.',
            bestFor: 'Good if you want to grow a bit of everything without thinking about settings.',
            tradeoff: 'Progress in any single skill is slower than in a specialized flow.',
        },
        'listening-first': {
            goal: 'Understand real spoken language better.',
            sequence: 'Introduction → listening → recognition → recall → dictation → production.',
            algorithm: 'The algorithm raises the weight of listening and picks listening, listen-recognize and dictation more often. Listening mistakes get priority in the next reviews.',
            bestFor: 'Good for movies, YouTube, conversation, and when you know the words but speech still sounds too fast.',
            tradeoff: 'For the same time you get fewer new words and less written practice.',
        },
        'speaking-first': {
            goal: 'Start building your own sentences sooner.',
            sequence: 'Introduction → recognition → recall → production → speaking → listening.',
            algorithm: 'The system raises the weight of production and speaking. Words with examples move to cloze, sentence practice and speaking earlier.',
            bestFor: 'Good for conversation, travel, interviews and using the language actively.',
            tradeoff: 'Sessions are harder: accuracy may dip for a while as the active skill forms.',
        },
        'fast-vocabulary': {
            goal: 'Grow your vocabulary quickly.',
            sequence: 'Short recognition and recall cycles with as few hard exercises as possible.',
            algorithm: 'Sessions are shorter, there are more new words per day, and recognition and recall weigh more. The system moves each word through the early stages faster.',
            bestFor: 'Good before a trip or an exam, or when vocabulary coverage matters more than drilling each word deeply.',
            tradeoff: 'New words may need more reviews later before you feel truly confident.',
        },
        'deep-mastery': {
            goal: 'Lock words in and be able to use them in context.',
            sequence: 'Introduction → recognition → recall → production → listening → speaking, with a higher bar for success.',
            algorithm: 'The system brings words back more often in context, production and sentence practice. The target success rate is higher, so one correct answer does not make a word done.',
            bestFor: 'Good for long-term study, a solid B1+, and words that should become part of your active speech.',
            tradeoff: 'Fewer words per day, and each session takes longer.',
        },
    };
    return details[selectedFlowProfile.value?.slug ?? ''] ?? details.balanced;
});

function openLearningFlow() {
    selectedFlowId.value = learningFlow.value?.preferences?.learning_flow_profile_id
        ? String(learningFlow.value.preferences.learning_flow_profile_id)
        : activeFlow.value?.id
            ? String(activeFlow.value.id)
            : '';
    learningFlowMessage.value = '';
    showFlowDetails.value = false;
    learningFlowModalOpen.value = true;
}

async function saveLearningFlow() {
    if (!learningFlow.value || savingLearningFlow.value) return;
    savingLearningFlow.value = true;
    learningFlowMessage.value = '';
    try {
        await learningFlowApi.update({
            learning_flow_profile_id: selectedFlowId.value ? Number(selectedFlowId.value) : null,
        });
        // The update endpoint returns the resolved flow, while the GET
        // response also contains the published profile choices needed by the
        // selector. Refresh the complete view model after saving.
        learningFlow.value = await learningFlowApi.get();
        learningFlowModalOpen.value = false;
        if (sessionStarted.value) {
            sessionStarted.value = true;
            await startSession(routeContentId.value || undefined, routeLexemeIds.value, selectedPracticeMode.value);
        }
    } catch {
        learningFlowMessage.value = 'Failed to save the learning flow.';
    } finally {
        savingLearningFlow.value = false;
    }
}

function replayExerciseTarget() {
    if (exerciseTarget.value) speak(exerciseTarget.value, currentCard.value?.word.language);
}

function finishExercise() {
    exerciseMode.value = null;
    showMorePractice.value = false;
}

const routeContentId = computed(() => (route.query.content_id as string | undefined) ?? '');
const routeLexemeIds = computed(() => {
    const value = route.query.lexeme_ids as string | undefined;
    if (!value) return undefined;
    const ids = value
        .split(',')
        .map((id) => Number(id))
        .filter((id) => Number.isInteger(id) && id > 0);
    return ids.length > 0 ? ids : undefined;
});

const autoRevealSecondsInput = computed({
    get: () => String(settings.value.autoRevealSeconds),
    set: (value: string) => {
        settings.value.autoRevealSeconds = Number(value);
    },
});

const progressDots = computed(() =>
    Array.from({ length: sessionTotal.value }, (_, i) => (i < currentIndex.value ? 'done' : i === currentIndex.value ? 'current' : 'upcoming')),
);

const contentSwitchOptions = computed(() => [
    { value: '', label: 'Practice today (all content)' },
    ...catalogContents.value.map((c) => ({ value: String(c.id), label: c.title })),
]);

function applyCustomize() {
    showCustomize.value = false;
    sessionStarted.value = true;
    router.replace({
        name: 'repetitions',
        query: customizeContentId.value ? { content_id: customizeContentId.value, return_to: route.query.return_to } : { return_to: route.query.return_to },
    });
    void startSession(customizeContentId.value || undefined);
}

function startAdaptiveSession() {
    sessionStarted.value = true;
    void startSession(routeContentId.value || undefined, routeLexemeIds.value, selectedPracticeMode.value);
}

function startFocusedMode(mode: FocusedPracticeMode) {
    sessionStarted.value = true;
    router.replace({ name: 'repetitions', query: { ...route.query, mode } });
    void startSession(routeContentId.value || undefined, routeLexemeIds.value, mode);
}

function startAnswerStyleMode(style: 'reveal' | 'type' | 'tap-letters' | 'choose-word') {
    answerStyle.value = style;
    sessionStarted.value = true;
    router.replace({ name: 'repetitions', query: { ...route.query, answer_style: style, mode: style === 'choose-word' ? 'cloze' : 'adaptive' } });
    void startSession(routeContentId.value || undefined, routeLexemeIds.value, style === 'choose-word' ? 'cloze' : 'adaptive');
}

function openMode(mode: 'context' | 'speaking' | 'exam' | 'dictation' | 'shadowing') {
    if (mode === 'context') {
        router.push({ name: 'context-practice', query: { ...route.query, return_to: 'repetitions' } });
    } else if (mode === 'speaking') {
        router.push({ name: 'speaking-practice', query: { ...route.query, return_to: 'repetitions' } });
    } else if (mode === 'dictation' || mode === 'shadowing') {
        router.push({ name: 'transcript-exercise', query: { content_id: routeContentId.value, mode, return_to: 'repetitions' } });
    } else {
        router.push({ name: 'catalog.exam', params: { id: routeContentId.value }, query: route.query });
    }
}

function openSentenceMode(mode: 'read' | 'write-flexible' | 'write-exact' | 'reorder') {
    router.push({
        name: 'speaking-practice',
        query: { ...route.query, sentence_mode: mode, return_to: 'repetitions' },
    });
}

function backFromPractice() {
    // Keep the learner inside the word-practice flow: while a session is
    // active, the previous screen is the mode picker on this page.
    if (sessionStarted.value) {
        sessionStarted.value = false;
        exerciseMode.value = null;
        showSettings.value = false;
        showCustomize.value = false;
        return;
    }

    const returnTo = String(route.query.return_to ?? '');
    const destinations = {
        dashboard: { name: 'dashboard' },
        'my-progress': { name: 'my-progress' },
        'my-words': { name: 'my-words' },
        lessons: { name: 'lessons' },
        study: { name: 'catalog.study', params: { id: routeContentId.value } },
    } as const;

    if (returnTo in destinations) {
        router.push(destinations[returnTo as keyof typeof destinations]);
        return;
    }
    if (routeContentId.value) {
        router.push({ name: 'catalog.details', params: { id: routeContentId.value } });
        return;
    }
    router.push({ name: 'dashboard' });
}

onMounted(async () => {
    if (!authStore.isAuthenticated) {
        router.push({ name: 'login', query: { redirect: '/repetitions' } });
        return;
    }
    customizeContentId.value = routeContentId.value;
    await Promise.all([
        ensureCatalogLoaded(),
        learningFlowApi.get().then((response) => {
            learningFlow.value = response;
        }).catch(() => {
            learningFlowMessage.value = 'Failed to load the learning flow.';
        }),
    ]);
});
</script>

<template>
    <div :class="sessionStarted ? 'h-[100dvh] overflow-hidden bg-background' : 'min-h-screen bg-background'">
        <header class="sticky top-0 z-20 border-b border-border bg-background/95 backdrop-blur">
            <div class="mx-auto flex max-w-2xl items-center justify-between gap-3 px-4 py-3">
                <UiButton variant="ghost" size="icon" aria-label="Back to previous learning screen" title="Back to previous learning screen" @click="backFromPractice">
                    <ChevronLeft :size="20" />
                </UiButton>
                <div class="min-w-0 text-center">
                    <div class="truncate text-sm font-semibold text-fg">{{ sessionStarted ? scopeLabel : routeContentId ? 'This content' : 'Practice today' }}</div>
                    <div class="text-xs text-muted-foreground">{{ sessionStarted ? modeLabel : 'Choose a practice mode' }}</div>
                </div>
                <div class="flex items-center gap-1">
                    <button
                        v-if="activeFlow"
                        type="button"
                        class="hidden max-w-40 truncate rounded-full border border-primary/25 bg-primary/10 px-2.5 py-1 text-[11px] font-semibold text-primary sm:block"
                        title="Change learning flow"
                        @click="openLearningFlow"
                    >
                        {{ activeFlow.name }}
                    </button>
                    <UiButton variant="ghost" size="icon" aria-label="Change scope" title="Change scope" @click="showCustomize = !showCustomize">
                        <SlidersHorizontal :size="17" />
                    </UiButton>
                    <UiButton variant="ghost" size="icon" aria-label="Trainer settings" title="Trainer settings" @click="showSettings = true">
                        <Settings :size="18" />
                    </UiButton>
                </div>
            </div>
        </header>

        <main
            class="mx-auto flex max-w-none flex-col px-0 py-0"
            :class="sessionStarted ? 'h-[calc(100dvh-129px)] min-h-0 overflow-hidden lg:h-[calc(100dvh-65px)]' : 'min-h-[calc(100dvh-65px)] pb-20 lg:pb-0'"
        >
            <section v-if="!sessionStarted" class="mx-auto flex w-full max-w-4xl flex-1 flex-col px-4 py-6 sm:py-10">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div class="space-y-2">
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-primary">Your study session</div>
                        <h1 class="text-3xl font-semibold tracking-tight text-fg sm:text-4xl">Practice words your way</h1>
                        <p class="max-w-xl text-sm leading-6 text-muted-foreground">Choose one focused exercise, or let the trainer balance your review automatically.</p>
                    </div>
                    <div class="flex w-fit items-center gap-2 rounded-full border border-border bg-surface px-3 py-2 text-xs font-medium text-fg-secondary">
                        <BookOpen :size="15" class="text-primary" />
                        {{ routeContentId ? 'Selected content' : 'All learning words' }}
                    </div>
                </div>

                <UiCard v-if="activeFlow" class="mt-6 border-primary/25 bg-primary/5 p-4 sm:p-5">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-[11px] font-semibold uppercase tracking-[0.18em] text-primary">Your learning flow</span>
                                <UiBadge tone="primary">{{ activeFlow.name }}</UiBadge>
                            </div>
                            <h2 class="mt-2 text-lg font-semibold text-fg">Today's learning style: {{ activeFlow.name }}</h2>
                            <p class="mt-1 max-w-2xl text-sm leading-5 text-muted-foreground">{{ activeFlowDescription }}</p>
                            <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-fg-secondary">
                                <span>{{ activeFlowConfig.session_minutes ?? 15 }} min per session</span>
                                <span>{{ activeFlowConfig.daily_new_words ?? 8 }} new words per day</span>
                                <span>Target: {{ Math.round(Number(activeFlowConfig.target_success_rate ?? 0.8) * 100) }}%</span>
                            </div>
                        </div>
                        <UiButton class="shrink-0" variant="secondary" size="sm" @click="openLearningFlow">Change learning flow</UiButton>
                    </div>
                </UiCard>

                <button
                    type="button"
                    class="mt-8 flex min-h-40 w-full flex-col items-start justify-between rounded-spa-lg border border-primary/40 bg-primary/10 p-5 text-left shadow-sm transition hover:border-primary hover:bg-primary/15 sm:p-6"
                    @click="startAdaptiveSession"
                >
                    <span class="flex w-full items-start justify-between gap-4">
                        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-primary text-white"><Sparkles :size="21" /></span>
                        <span class="rounded-full bg-primary/15 px-2.5 py-1 text-xs font-semibold text-primary">Best for today</span>
                    </span>
                    <span class="mt-6 space-y-1">
                        <span class="block text-lg font-semibold text-fg">Adaptive practice</span>
                        <span class="block text-sm leading-5 text-muted-foreground">Reviews, new words and reinforcement are balanced for {{ routeContentId ? 'this content' : 'your learning list' }}.</span>
                    </span>
                </button>

                <div class="mt-6 space-y-6">
                    <section class="space-y-3">
                        <div class="flex items-start gap-3"><span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"><Eye :size="17" /></span><div><h2 class="text-sm font-semibold text-fg">Recall & answer</h2><p class="mt-1 text-xs text-muted-foreground">Build memory by recalling or producing the word.</p></div></div>
                        <div class="grid grid-cols-2 gap-2 lg:grid-cols-4">
                    <button type="button" class="group flex min-h-32 flex-col items-start justify-between rounded-spa-lg border border-border bg-surface p-3 text-left transition hover:border-primary/50 hover:bg-primary/5 sm:p-4" @click="startAnswerStyleMode('reveal')">
                        <Eye :size="18" class="text-fg-secondary transition group-hover:text-primary" /><span class="space-y-1"><span class="block text-sm font-semibold text-fg">Recall</span><span class="block text-xs leading-5 text-muted-foreground">Reveal and self-grade</span></span>
                    </button>
                    <button type="button" class="group flex min-h-32 flex-col items-start justify-between rounded-spa-lg border border-border bg-surface p-3 text-left transition hover:border-primary/50 hover:bg-primary/5 sm:p-4" @click="startAnswerStyleMode('type')">
                        <Keyboard :size="18" class="text-fg-secondary transition group-hover:text-primary" /><span class="space-y-1"><span class="block text-sm font-semibold text-fg">Type it</span><span class="block text-xs leading-5 text-muted-foreground">Write the answer yourself</span></span>
                    </button>
                    <button type="button" class="group flex min-h-32 flex-col items-start justify-between rounded-spa-lg border border-border bg-surface p-3 text-left transition hover:border-primary/50 hover:bg-primary/5 sm:p-4" @click="startAnswerStyleMode('tap-letters')">
                        <ListChecks :size="18" class="text-fg-secondary transition group-hover:text-primary" /><span class="space-y-1"><span class="block text-sm font-semibold text-fg">Tap letters</span><span class="block text-xs leading-5 text-muted-foreground">Build it step by step</span></span>
                    </button>
                    <button type="button" class="group flex min-h-32 flex-col items-start justify-between rounded-spa-lg border border-border bg-surface p-3 text-left transition hover:border-primary/50 hover:bg-primary/5 sm:p-4" @click="startAnswerStyleMode('choose-word')">
                        <Puzzle :size="18" class="text-fg-secondary transition group-hover:text-primary" /><span class="space-y-1"><span class="block text-sm font-semibold text-fg">Choose the word</span><span class="block text-xs leading-5 text-muted-foreground">Complete a generated sentence</span></span>
                    </button>
                        </div>
                    </section>

                    <section class="space-y-3">
                        <div class="flex items-start gap-3"><span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"><Headphones :size="17" /></span><div><h2 class="text-sm font-semibold text-fg">Listening & speaking</h2><p class="mt-1 text-xs text-muted-foreground">Train your ear, pronunciation and fluency.</p></div></div>
                        <div class="grid grid-cols-2 gap-2 lg:grid-cols-4">
                    <button type="button" class="group flex min-h-32 flex-col items-start justify-between rounded-spa-lg border border-border bg-surface p-3 text-left transition hover:border-primary/50 hover:bg-primary/5 sm:p-4" @click="startFocusedMode('cloze')">
                        <Puzzle :size="18" class="text-fg-secondary transition group-hover:text-primary" /><span class="space-y-1"><span class="block text-sm font-semibold text-fg">Cloze</span><span class="block text-xs leading-5 text-muted-foreground">Choose the missing word</span></span>
                    </button>
                    <button type="button" class="group flex min-h-32 flex-col items-start justify-between rounded-spa-lg border border-border bg-surface p-3 text-left transition hover:border-primary/50 hover:bg-primary/5 sm:p-4" @click="startFocusedMode('listening')">
                        <Headphones :size="18" class="text-fg-secondary transition group-hover:text-primary" /><span class="space-y-1"><span class="block text-sm font-semibold text-fg">Listening</span><span class="block text-xs leading-5 text-muted-foreground">Hear and recognize words</span></span>
                    </button>
                    <button type="button" class="group flex min-h-32 flex-col items-start justify-between rounded-spa-lg border border-border bg-surface p-3 text-left transition hover:border-primary/50 hover:bg-primary/5 disabled:cursor-not-allowed disabled:opacity-40 sm:p-4" :disabled="!routeContentId" @click="openMode('dictation')">
                        <PenLine :size="18" class="text-fg-secondary transition group-hover:text-primary" /><span class="space-y-1"><span class="block text-sm font-semibold text-fg">Dictation</span><span class="block text-xs leading-5 text-muted-foreground">Listen, then type a phrase</span></span>
                    </button>
                    <button type="button" class="group flex min-h-32 flex-col items-start justify-between rounded-spa-lg border border-border bg-surface p-3 text-left transition hover:border-primary/50 hover:bg-primary/5 disabled:cursor-not-allowed disabled:opacity-40 sm:p-4" :disabled="!routeContentId" @click="openMode('shadowing')">
                        <Mic2 :size="18" class="text-fg-secondary transition group-hover:text-primary" /><span class="space-y-1"><span class="block text-sm font-semibold text-fg">Shadowing</span><span class="block text-xs leading-5 text-muted-foreground">Listen, then repeat aloud</span></span>
                    </button>
                        </div>
                    </section>

                    <section class="space-y-3">
                        <div class="flex items-start gap-3"><span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"><PenLine :size="17" /></span><div><h2 class="text-sm font-semibold text-fg">Sentence practice</h2><p class="mt-1 text-xs text-muted-foreground">Use your learning words in complete sentences.</p></div></div>
                        <div class="grid grid-cols-2 gap-2 lg:grid-cols-4">
                            <button type="button" class="group flex min-h-32 flex-col items-start justify-between rounded-spa-lg border border-border bg-surface p-3 text-left transition hover:border-primary/50 hover:bg-primary/5 sm:p-4" @click="openSentenceMode('read')">
                                <BookOpen :size="18" class="text-fg-secondary transition group-hover:text-primary" /><span class="space-y-1"><span class="block text-sm font-semibold text-fg">Read & reveal</span><span class="block text-xs leading-5 text-muted-foreground">Read a sentence and see its meaning.</span></span>
                            </button>
                            <button type="button" class="group flex min-h-32 flex-col items-start justify-between rounded-spa-lg border border-border bg-surface p-3 text-left transition hover:border-primary/50 hover:bg-primary/5 sm:p-4" @click="openSentenceMode('write-flexible')">
                                <Keyboard :size="18" class="text-fg-secondary transition group-hover:text-primary" /><span class="space-y-1"><span class="block text-sm font-semibold text-fg">Write · flexible</span><span class="block text-xs leading-5 text-muted-foreground">Write any natural translation.</span></span>
                            </button>
                            <button type="button" class="group flex min-h-32 flex-col items-start justify-between rounded-spa-lg border border-border bg-surface p-3 text-left transition hover:border-primary/50 hover:bg-primary/5 sm:p-4" @click="openSentenceMode('write-exact')">
                                <CheckSquare :size="18" class="text-fg-secondary transition group-hover:text-primary" /><span class="space-y-1"><span class="block text-sm font-semibold text-fg">Write · exact</span><span class="block text-xs leading-5 text-muted-foreground">Match the model sentence.</span></span>
                            </button>
                            <button type="button" class="group flex min-h-32 flex-col items-start justify-between rounded-spa-lg border border-border bg-surface p-3 text-left transition hover:border-primary/50 hover:bg-primary/5 sm:p-4" @click="openSentenceMode('reorder')">
                                <ListChecks :size="18" class="text-fg-secondary transition group-hover:text-primary" /><span class="space-y-1"><span class="block text-sm font-semibold text-fg">Build the sentence</span><span class="block text-xs leading-5 text-muted-foreground">Put the words in order.</span></span>
                            </button>
                        </div>
                    </section>

                    <section class="space-y-3">
                        <div class="flex items-start gap-3"><span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"><ClipboardCheck :size="17" /></span><div><h2 class="text-sm font-semibold text-fg">Context & assessment</h2><p class="mt-1 text-xs text-muted-foreground">Practice inside real examples or check your progress.</p></div></div>
                        <div class="grid grid-cols-2 gap-2">
                    <button
                        type="button"
                        class="group flex min-h-32 flex-col items-start justify-between rounded-spa-lg border border-border bg-surface p-3 text-left transition hover:border-primary/50 hover:bg-primary/5 sm:p-4"
                        @click="openMode('context')"
                    >
                        <Languages :size="18" class="text-fg-secondary transition group-hover:text-primary" />
                        <span class="space-y-1"><span class="block text-sm font-semibold text-fg">Context</span><span class="block text-xs leading-5 text-muted-foreground">Translate words inside real sentences.</span></span>
                    </button>
                    <button
                        type="button"
                        class="group flex min-h-32 flex-col items-start justify-between rounded-spa-lg border border-border bg-surface p-3 text-left transition hover:border-primary/50 hover:bg-primary/5 disabled:cursor-not-allowed disabled:opacity-40 sm:p-4"
                        :disabled="!routeContentId"
                        @click="openMode('exam')"
                    >
                        <ClipboardCheck :size="18" class="text-fg-secondary transition group-hover:text-primary" />
                        <span class="space-y-1"><span class="block text-sm font-semibold text-fg">Ready check</span><span class="block text-xs leading-5 text-muted-foreground">Test yourself before returning to the content.</span></span>
                    </button>
                        </div>
                    </section>
                </div>

                <div class="mt-8 flex justify-center">
                    <UiButton variant="ghost" size="sm" @click="showCustomize = !showCustomize">Change study scope</UiButton>
                </div>
                <UiCard v-if="showCustomize" class="mt-3 space-y-3">
                    <SelectField v-model="customizeContentId" label="Content" :options="contentSwitchOptions" placeholder="Practice today (all content)" />
                    <UiButton variant="primary" size="sm" @click="applyCustomize">Use this scope</UiButton>
                </UiCard>
            </section>

            <div
                v-if="learningFlowModalOpen && !sessionStarted"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
                role="dialog"
                aria-modal="true"
                aria-labelledby="learning-flow-title-before-session"
                @click.self="learningFlowModalOpen = false"
            >
                <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-spa-lg border border-border bg-surface p-5 shadow-2xl sm:p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-primary">Adaptive practice</div>
                            <h2 id="learning-flow-title-before-session" class="mt-1 text-xl font-semibold text-fg">Choose how you want to progress</h2>
                            <p class="mt-1 text-sm leading-5 text-muted-foreground">A flow changes the pace and mix of exercises. Try any option — your progress and SRS reviews are kept.</p>
                        </div>
                        <UiButton variant="ghost" size="sm" @click="learningFlowModalOpen = false">Close</UiButton>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        <button
                            v-for="profileOption in learningFlow?.available_profiles ?? []"
                            :key="profileOption.id"
                            type="button"
                            class="rounded-spa-lg border p-4 text-left transition"
                            :class="selectedFlowId === String(profileOption.id) ? 'border-primary bg-primary/10 shadow-sm' : 'border-border bg-background hover:border-primary/50 hover:bg-primary/5'"
                            @click="selectedFlowId = String(profileOption.id)"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <span class="text-sm font-semibold text-fg">{{ profileOption.name }}</span>
                                <span v-if="selectedFlowId === String(profileOption.id)" class="rounded-full bg-primary px-2 py-0.5 text-[10px] font-bold text-white">SELECTED</span>
                            </div>
                            <p class="mt-2 text-xs leading-5 text-muted-foreground">{{ flowDescription(profileOption.slug) }}</p>
                            <p v-if="profileOption.description" class="mt-2 text-xs leading-5 text-fg-secondary">{{ profileOption.description }}</p>
                        </button>
                    </div>

                    <button type="button" class="mt-4 text-sm font-medium text-primary hover:underline" @click="showFlowDetails = !showFlowDetails">
                        {{ showFlowDetails ? 'Hide details' : `How does ${selectedFlowProfile?.name ?? 'this flow'} work?` }}
                    </button>
                    <div v-if="showFlowDetails" class="mt-3 space-y-3 rounded-spa-lg border border-primary/20 bg-primary/5 p-4 text-sm">
                        <div><div class="font-semibold text-fg">Main goal</div><p class="mt-1 leading-5 text-muted-foreground">{{ selectedFlowDetails.goal }}</p></div>
                        <div><div class="font-semibold text-fg">How it works</div><p class="mt-1 leading-5 text-muted-foreground">{{ selectedFlowDetails.sequence }}</p></div>
                        <div><div class="font-semibold text-fg">How the adaptive algorithm decides</div><p class="mt-1 leading-5 text-muted-foreground">{{ selectedFlowDetails.algorithm }}</p></div>
                        <div class="grid gap-3 border-t border-primary/15 pt-3 sm:grid-cols-2">
                            <div><div class="font-semibold text-fg">Best for</div><p class="mt-1 leading-5 text-muted-foreground">{{ selectedFlowDetails.bestFor }}</p></div>
                            <div><div class="font-semibold text-fg">Trade-off</div><p class="mt-1 leading-5 text-muted-foreground">{{ selectedFlowDetails.tradeoff }}</p></div>
                        </div>
                    </div>

                    <p v-if="learningFlowMessage" class="mt-3 text-sm text-warning" role="alert">{{ learningFlowMessage }}</p>
                    <div class="mt-5 flex flex-wrap justify-end gap-2">
                        <UiButton variant="ghost" @click="learningFlowModalOpen = false">Cancel</UiButton>
                        <UiButton variant="primary" :disabled="savingLearningFlow" @click="saveLearningFlow">
                            {{ savingLearningFlow ? 'Saving…' : 'Use this flow' }}
                        </UiButton>
                    </div>
                </div>
            </div>

            <template v-else>
            <div v-if="showCustomize" class="mx-auto w-full max-w-2xl px-4 pt-4">
                <UiCard class="space-y-3">
                    <SelectField v-model="customizeContentId" label="Content" :options="contentSwitchOptions" placeholder="Practice today (all content)" />
                    <UiButton variant="primary" size="sm" @click="applyCustomize">Apply</UiButton>
                </UiCard>
            </div>

            <div
                v-if="learningFlowModalOpen"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
                role="dialog"
                aria-modal="true"
                aria-labelledby="learning-flow-title"
                @click.self="learningFlowModalOpen = false"
            >
                <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-spa-lg border border-border bg-surface p-5 shadow-2xl sm:p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-primary">Adaptive practice</div>
                            <h2 id="learning-flow-title" class="mt-1 text-xl font-semibold text-fg">Choose how you want to progress</h2>
                            <p class="mt-1 text-sm leading-5 text-muted-foreground">A flow changes the pace and mix of exercises. Try any option — your progress and SRS reviews are kept.</p>
                        </div>
                        <UiButton variant="ghost" size="sm" @click="learningFlowModalOpen = false">Close</UiButton>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        <button
                            v-for="profileOption in learningFlow?.available_profiles ?? []"
                            :key="profileOption.id"
                            type="button"
                            class="rounded-spa-lg border p-4 text-left transition"
                            :class="selectedFlowId === String(profileOption.id) ? 'border-primary bg-primary/10 shadow-sm' : 'border-border bg-background hover:border-primary/50 hover:bg-primary/5'"
                            @click="selectedFlowId = String(profileOption.id)"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <span class="text-sm font-semibold text-fg">{{ profileOption.name }}</span>
                                <span v-if="selectedFlowId === String(profileOption.id)" class="rounded-full bg-primary px-2 py-0.5 text-[10px] font-bold text-white">ACTIVE CHOICE</span>
                            </div>
                            <p class="mt-2 text-xs leading-5 text-muted-foreground">{{ flowDescription(profileOption.slug) }}</p>
                            <p v-if="profileOption.description" class="mt-2 text-xs leading-5 text-fg-secondary">{{ profileOption.description }}</p>
                        </button>
                    </div>

                    <button type="button" class="mt-4 text-sm font-medium text-primary hover:underline" @click="showFlowDetails = !showFlowDetails">
                        {{ showFlowDetails ? 'Hide details' : `How does ${selectedFlowProfile?.name ?? 'this flow'} work?` }}
                    </button>
                    <div v-if="showFlowDetails" class="mt-3 space-y-3 rounded-spa-lg border border-primary/20 bg-primary/5 p-4 text-sm">
                        <div><div class="font-semibold text-fg">Main goal</div><p class="mt-1 leading-5 text-muted-foreground">{{ selectedFlowDetails.goal }}</p></div>
                        <div><div class="font-semibold text-fg">How it works</div><p class="mt-1 leading-5 text-muted-foreground">{{ selectedFlowDetails.sequence }}</p></div>
                        <div><div class="font-semibold text-fg">How the adaptive algorithm decides</div><p class="mt-1 leading-5 text-muted-foreground">{{ selectedFlowDetails.algorithm }}</p></div>
                        <div class="grid gap-3 border-t border-primary/15 pt-3 sm:grid-cols-2">
                            <div><div class="font-semibold text-fg">Best for</div><p class="mt-1 leading-5 text-muted-foreground">{{ selectedFlowDetails.bestFor }}</p></div>
                            <div><div class="font-semibold text-fg">Trade-off</div><p class="mt-1 leading-5 text-muted-foreground">{{ selectedFlowDetails.tradeoff }}</p></div>
                        </div>
                    </div>

                    <div class="mt-5 rounded-spa-lg border border-border bg-background p-4">
                        <div class="text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">What changes</div>
                        <div class="mt-3 grid gap-2 text-sm text-fg-secondary sm:grid-cols-3">
                            <div><span class="block text-lg font-semibold text-fg">{{ activeFlowConfig.session_minutes ?? 15 }}m</span>session rhythm</div>
                            <div><span class="block text-lg font-semibold text-fg">{{ activeFlowConfig.daily_new_words ?? 8 }}</span>new words/day</div>
                            <div><span class="block text-lg font-semibold text-fg">{{ Math.round(Number(activeFlowConfig.target_success_rate ?? 0.8) * 100) }}%</span>target success</div>
                        </div>
                    </div>
                    <p v-if="learningFlowMessage" class="mt-3 text-sm text-warning" role="alert">{{ learningFlowMessage }}</p>
                    <div class="mt-5 flex flex-wrap justify-end gap-2">
                        <UiButton variant="ghost" @click="learningFlowModalOpen = false">Cancel</UiButton>
                        <UiButton variant="primary" :disabled="savingLearningFlow" @click="saveLearningFlow">
                            {{ savingLearningFlow ? 'Saving…' : sessionStarted ? 'Save & restart session' : 'Use this flow' }}
                        </UiButton>
                    </div>
                </div>
            </div>

            <div
                v-if="showSettings"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
                role="dialog"
                aria-modal="true"
                aria-labelledby="trainer-settings-title"
                @click.self="showSettings = false"
            >
                <div class="w-full max-w-md space-y-5 rounded-spa-lg border border-border bg-surface p-5 shadow-2xl">
                    <div class="flex items-start justify-between gap-4">
                        <h3 id="trainer-settings-title" class="text-lg font-semibold text-fg">Trainer settings</h3>
                        <UiButton variant="ghost" size="sm" @click="showSettings = false">Close</UiButton>
                    </div>

                    <SelectField v-if="showRecallSettings && !selectedAnswerStyle" v-model="answerStyle" label="Answer style" :options="answerStyleOptions" />

                    <div v-if="showRecallSettings" class="space-y-1 border-t border-border pt-4">
                        <UiSwitch
                            v-model="settings.translationVisible"
                            label="Show translation"
                            description="Off hides the translation panel for a harder recall/listening test"
                        />
                        <UiSwitch
                            v-model="settings.autoplayPronunciation"
                            label="Autoplay pronunciation"
                            description="Speak the word aloud automatically once it's revealed or answered"
                        />
                    </div>

                    <div v-if="showListeningSettings" class="space-y-3 border-t border-border pt-4">
                        <SelectField v-model="settings.listeningReveal" label="Listen cards: reveal translation" :options="listeningRevealOptions" />
                        <SelectField
                            v-if="settings.listeningReveal === 'auto'"
                            v-model="autoRevealSecondsInput"
                            label="Auto-reveal delay"
                            :options="autoRevealSecondsOptions"
                        />
                    </div>

                    <div v-if="isSpeechSupported() && (!focusedMode || selectedPracticeMode === 'listening')" class="space-y-3 border-t border-border pt-4">
                        <SelectField v-model="speechRateInput" label="Pronunciation speed" :options="speechRateOptions" />
                        <SelectField v-model="speechVoiceInput" label="Pronunciation voice" :options="voiceOptions" />
                    </div>
                </div>
            </div>

            <p v-if="error" class="mt-4 text-sm text-warning" role="alert">{{ error }}</p>

            <PageState v-if="phase === 'loading'" :loading="true" />

            <UiEmptyState
                v-else-if="phase === 'empty'"
                title="Nothing to practice right now"
                description="No due reviews, no new words, and nothing to reinforce for this scope."
            >
                <UiButton variant="secondary" size="sm" @click="router.push({ name: 'catalog' })">Browse catalog</UiButton>
            </UiEmptyState>

            <template v-else-if="phase === 'session' && currentCard">
                <div class="mx-auto mt-3 w-full max-w-2xl space-y-2 px-4">
                    <div class="flex items-center justify-between gap-2 text-xs font-medium text-muted-foreground">
                        <span>Progress</span>
                        <span class="flex items-center gap-2">
                            <UiButton
                                v-if="currentCard.activity === 'cloze'"
                                size="sm"
                                variant="ghost"
                                class="h-8 px-2 text-[11px] text-primary"
                                :disabled="preparingCloze"
                                @click="prepareSessionClozeExamples"
                            >
                                {{ preparingCloze ? 'Preparing…' : 'Prepare examples' }}
                            </UiButton>
                            <span>{{ cardNumber }} / {{ sessionTotal }}</span>
                        </span>
                    </div>
                    <p v-if="clozePrepareMessage" class="mt-1 text-right text-[11px] text-muted-foreground" role="status">{{ clozePrepareMessage }}</p>
                    <div class="flex items-center gap-1" aria-hidden="true">
                    <span
                        v-for="(dot, i) in progressDots"
                        :key="i"
                        class="h-1.5 flex-1 rounded-full transition-colors"
                        :class="{ 'bg-primary': dot === 'done', 'bg-primary/40': dot === 'current', 'bg-muted': dot === 'upcoming' }"
                    />
                    </div>
                </div>

                <div class="mt-4 flex min-h-0 w-full flex-1 items-stretch justify-center overflow-hidden">
                    <div v-if="exerciseMode" class="w-full max-w-2xl px-4">
                        <UiButton class="mb-3" size="sm" @click="finishExercise">Back to word card</UiButton>
                        <DictationCard
                            v-if="exerciseMode === 'dictation'"
                            :content-id="currentCard.contentId ?? 0"
                            :content-lexeme-id="currentCard.contentLexemeId"
                            :target-text="exerciseTarget"
                            :replay="replayExerciseTarget"
                            @submitted="finishExercise"
                        />
                        <ShadowingCard
                            v-else
                            :content-id="currentCard.contentId ?? 0"
                            :content-lexeme-id="currentCard.contentLexemeId"
                            :target-text="exerciseTarget"
                            @submitted="finishExercise"
                        />
                    </div>
                    <WordCard
                        v-else
                        class="h-full min-h-0 w-full max-w-none rounded-none border-x-0 border-b-0 shadow-none sm:rounded-none sm:px-10"
                        :card="currentCard"
                        :busy="busy"
                        @grade="submitReviewGrade"
                        @mark-learned="submitLearnMarkLearned"
                        @start-learning="submitLearnStartLearning"
                        @reinforce-result="submitReinforceResult"
                    />
                </div>
                <div v-if="!exerciseMode" class="mx-auto w-full max-w-2xl px-4 pb-3 lg:pb-4">
                    <button
                        type="button"
                        class="flex min-h-11 w-full items-center justify-between rounded-spa-lg border border-border bg-surface px-3 py-2.5 text-left transition hover:border-primary/50 hover:bg-primary/5"
                        :aria-expanded="showMorePractice"
                        @click="showMorePractice = !showMorePractice"
                    >
                        <span class="flex min-w-0 items-center gap-2">
                            <Volume2 :size="17" class="shrink-0 text-primary" aria-hidden="true" />
                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-fg">More practice</span>
                                <span class="block truncate text-xs text-muted-foreground">Practice with audio</span>
                            </span>
                        </span>
                        <ChevronDown :size="17" class="shrink-0 text-primary transition-transform" :class="showMorePractice ? 'rotate-180' : ''" aria-hidden="true" />
                    </button>

                    <div v-if="showMorePractice" class="mt-2 grid gap-2 sm:grid-cols-2">
                        <button
                            type="button"
                            class="flex min-h-16 items-center gap-3 rounded-spa-lg border border-border bg-surface p-3 text-left transition hover:border-primary/50 hover:bg-primary/5"
                            @click="exerciseMode = 'dictation'"
                        >
                            <Volume2 :size="19" class="shrink-0 text-primary" aria-hidden="true" />
                            <span><span class="block text-sm font-semibold text-fg">Dictation</span><span class="block text-xs text-muted-foreground">Listen and type</span></span>
                        </button>
                        <button
                            type="button"
                            class="flex min-h-16 items-center gap-3 rounded-spa-lg border border-border bg-surface p-3 text-left transition hover:border-primary/50 hover:bg-primary/5"
                            @click="exerciseMode = 'shadowing'"
                        >
                            <Mic2 :size="19" class="shrink-0 text-primary" aria-hidden="true" />
                            <span><span class="block text-sm font-semibold text-fg">Shadowing</span><span class="block text-xs text-muted-foreground">Listen and repeat</span></span>
                        </button>
                    </div>
                </div>
            </template>

            <template v-else-if="phase === 'summary'">
                <UiCard class="mt-6 space-y-4">
                    <UiSectionHeader title="Session complete" subtitle="Here's what happened" />

                    <ul class="space-y-1 text-sm text-muted-foreground">
                        <li v-if="reviewedCount > 0">{{ reviewedCount }} card{{ reviewedCount === 1 ? '' : 's' }} reviewed</li>
                        <li v-if="learnedCount > 0 || addedToReviewCount > 0">
                            {{ learnedCount }} word{{ learnedCount === 1 ? '' : 's' }} marked already known, {{ addedToReviewCount }} added to reviews
                        </li>
                        <li v-if="reinforceTotal > 0">{{ reinforceCorrect }} / {{ reinforceTotal }} correct on reinforcement</li>
                    </ul>

                    <div class="flex flex-wrap gap-2">
                        <UiButton variant="primary" @click="restart">Practice more</UiButton>
                        <UiButton variant="secondary" @click="router.push({ name: 'catalog' })">Catalog</UiButton>
                    </div>
                </UiCard>
            </template>
            </template>
        </main>
    </div>
</template>
