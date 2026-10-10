<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { BookOpen, CheckSquare, ChevronDown, ChevronLeft, ClipboardCheck, Eye, Headphones, Keyboard, Languages, ListChecks, Mic2, PenLine, Puzzle, Settings, SlidersHorizontal, Sparkles, Volume2 } from 'lucide-vue-next';
import PageState from '../components/ui/PageState.vue';
import { useAuthStore } from '../domains/user';
import { useTrainingSession, useAnswerStylePreference, useTrainerSettings, lessonApi, type FocusedPracticeMode, type LessonSummary } from '../domains/learning';
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
const sourceChanged = ref(false);
const lessonSources = ref<LessonSummary[]>([]);
const sourceSearch = ref('');
const sourceLoadError = ref('');
const selectedSourceKeys = ref<string[]>([]);
const sourceDraftKeys = ref<string[]>([]);
const sourceDraftAll = ref(true);
const showSettings = ref(false);
const showModeSheet = ref(false);
const sessionStarted = ref(false);
type LaunchMode = 'adaptive' | 'recall' | 'type' | 'tap-letters' | 'choose-word' | 'cloze' | 'listening';
interface LastPractice {
    mode: FocusedPracticeMode;
    answerStyle: 'reveal' | 'type' | 'tap-letters' | 'choose-word' | null;
    contentId: string;
    lexemeIds: string;
    sourceKeys?: string[];
    lessonLexemeIds?: string;
}
const lastPractice = ref<LastPractice | null>(null);
const draftLaunchMode = ref<LaunchMode>('adaptive');
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
const startingPractice = ref(false);

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
const lastPracticeStorageKey = computed(() => `practice.last-session.${authStore.user?.id ?? 'anonymous'}`);
const sourceKey = (kind: 'content' | 'lesson', id: string | number) => `${kind}:${id}`;
const routeLessonId = computed(() => String(route.query.lesson_id ?? ''));
const routeLessonLexemeIds = computed(() => String(route.query.lesson_lexeme_ids ?? ''));
const routeSourceKeys = computed(() => String(route.query.source_ids ?? '').split(',').filter((key) => /^(content|lesson):\d+$/.test(key)));
const availableSources = computed(() => [
    ...catalogContents.value.map((content) => ({
        key: sourceKey('content', content.id), kind: 'Content', title: content.title,
        detail: `${content.total_lexemes ?? 0} words · ${content.type}`,
        count: content.total_lexemes ?? 0,
    })),
    ...lessonSources.value.map((lesson) => ({
        key: sourceKey('lesson', lesson.id), kind: 'Lesson', title: lesson.title || 'Untitled lesson',
        detail: `${lesson.lexeme_count} words · ${lesson.lesson_date ?? 'No date'}`,
        count: lesson.lexeme_count,
    })),
]);
const filteredSources = computed(() => {
    const query = sourceSearch.value.trim().toLocaleLowerCase();
    return query ? availableSources.value.filter((source) => `${source.title} ${source.kind} ${source.detail}`.toLocaleLowerCase().includes(query)) : availableSources.value;
});
function defaultSourceKeys(): string[] {
    if (routeSourceKeys.value.length > 0) return routeSourceKeys.value;
    if (routeLessonId.value) return [sourceKey('lesson', routeLessonId.value)];
    if (routeContentId.value) return [sourceKey('content', routeContentId.value)];
    if (lastPractice.value?.sourceKeys) return lastPractice.value.sourceKeys;
    if (lastPractice.value?.contentId) return [sourceKey('content', lastPractice.value.contentId)];
    return [];
}
const contentLabel = computed(() => {
    const keys = sourceChanged.value ? selectedSourceKeys.value : defaultSourceKeys();
    if (keys.length > 0) {
        const titles = keys.map((key) => availableSources.value.find((source) => source.key === key)?.title).filter((title): title is string => Boolean(title));
        if (titles.length === 0) return 'Selected sources';
        return titles.length < 3 ? titles.join(' + ') : `${titles.length} sources selected`;
    }
    if (sourceChanged.value) return 'All learning words';
    const routeLexemeIds = route.query.lexeme_ids as string | undefined;
    const selectedIds = routeLexemeIds || (!routeContentId.value ? lastPractice.value?.lexemeIds : '');
    if (selectedIds) return `${selectedIds.split(',').filter(Boolean).length} selected words`;
    const contentId = routeContentId.value || lastPractice.value?.contentId || '';
    if (!contentId) return 'All learning words';
    return catalogContents.value.find((content) => String(content.id) === contentId)?.title ?? 'Selected content';
});
const lastPracticeModeLabel = computed(() => {
    if (route.query.answer_style || route.query.mode) {
        if (selectedAnswerStyle.value) return ({ reveal: 'Recall', type: 'Type it', 'tap-letters': 'Tap letters', 'choose-word': 'Choose the word' } as const)[selectedAnswerStyle.value];
        return ({ adaptive: 'Balanced practice', cloze: 'Cloze', listening: 'Listening' } as const)[selectedPracticeMode.value];
    }
    if (!lastPractice.value) return activeFlow.value?.name ?? 'Balanced practice';
    if (lastPractice.value.answerStyle) {
        return ({ reveal: 'Recall', type: 'Type it', 'tap-letters': 'Tap letters', 'choose-word': 'Choose the word' } as const)[lastPractice.value.answerStyle];
    }
    return ({ adaptive: 'Balanced practice', cloze: 'Cloze', listening: 'Listening' } as const)[lastPractice.value.mode];
});
const launchButtonLabel = computed(() => 'Start practice');
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
            const saved = lastPractice.value;
            if (saved?.sourceKeys) {
                const scope = saved.sourceKeys.length > 0
                    ? await resolveSourceScope(saved.sourceKeys, saved.lessonLexemeIds)
                    : null;
                await startSession(scope ?? undefined, undefined, saved.mode);
            } else {
                await startSession(routeContentId.value || undefined, routeLexemeIds.value, selectedPracticeMode.value);
            }
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

function applyCustomize() {
    selectedSourceKeys.value = sourceDraftAll.value ? [] : [...sourceDraftKeys.value];
    showCustomize.value = false;
    sourceChanged.value = true;
}

function openSourcePicker() {
    sourceDraftKeys.value = [...(sourceChanged.value ? selectedSourceKeys.value : defaultSourceKeys())];
    sourceDraftAll.value = sourceDraftKeys.value.length === 0;
    sourceSearch.value = '';
    sourceLoadError.value = '';
    showCustomize.value = true;
}

function cancelSourcePicker() {
    showCustomize.value = false;
    sourceDraftKeys.value = [...(sourceChanged.value ? selectedSourceKeys.value : defaultSourceKeys())];
    sourceDraftAll.value = sourceDraftKeys.value.length === 0;
}

function toggleSourceDraft(key: string) {
    sourceDraftAll.value = false;
    const selected = new Set(sourceDraftKeys.value);
    if (selected.has(key)) selected.delete(key);
    else selected.add(key);
    sourceDraftKeys.value = [...selected];
}

function selectAllSources() {
    sourceDraftAll.value = true;
    sourceDraftKeys.value = [];
}

function currentPracticeSource(): Pick<LastPractice, 'contentId' | 'lexemeIds' | 'sourceKeys' | 'lessonLexemeIds'> {
    if (sourceChanged.value || routeLessonId.value || routeSourceKeys.value.length > 0 || lastPractice.value?.sourceKeys) {
        return {
            contentId: '',
            lexemeIds: '',
            sourceKeys: sourceChanged.value ? [...selectedSourceKeys.value] : defaultSourceKeys(),
            lessonLexemeIds: sourceChanged.value ? '' : routeLessonLexemeIds.value || lastPractice.value?.lessonLexemeIds,
        };
    }
    const routeLexemeIds = route.query.lexeme_ids as string | undefined;
    const saved = lastPractice.value;
    if (routeLexemeIds && !routeContentId.value) return { contentId: '', lexemeIds: routeLexemeIds };
    const contentId = routeContentId.value || saved?.contentId || '';
    const lexemeIds = routeLexemeIds || (contentId && contentId === saved?.contentId ? saved.lexemeIds : '');
    return { contentId, lexemeIds };
}

function startFocusedMode(mode: FocusedPracticeMode) {
    launchPractice({ mode, answerStyle: null, ...currentPracticeSource() });
}

function startAnswerStyleMode(style: 'reveal' | 'type' | 'tap-letters' | 'choose-word') {
    const mode = style === 'choose-word' ? 'cloze' : 'adaptive';
    launchPractice({ mode, answerStyle: style, ...currentPracticeSource() });
}

async function resolveSourceScope(sourceKeys: string[], lessonLexemeIds = ''): Promise<{ contentIds: number[]; canonicalLexemeIds: number[]; lessonWords: { lexemeId: number; text: string; language: string; translation: string | null; level: string | null; example: string | null; exampleTranslation: string | null }[]; label: string }> {
    const contentIds = sourceKeys.flatMap((key) => key.startsWith('content:') ? [Number(key.slice('content:'.length))] : []);
    const lessonIds = sourceKeys.flatMap((key) => key.startsWith('lesson:') ? [Number(key.slice('lesson:'.length))] : []);
    const requestedLexemeIds = new Set(lessonLexemeIds.split(',').map(Number).filter((id) => Number.isInteger(id) && id > 0));
    const lessonDetails = await Promise.all(lessonIds.map((id) => lessonApi.get(id)));
    const lessonWords = lessonDetails.flatMap((lesson) => lesson.lexemes
        .filter((word) => word.in_review && word.matched_lexeme_id !== null)
        .filter((word) => lesson.id !== Number(routeLessonId.value) || requestedLexemeIds.size === 0 || requestedLexemeIds.has(word.matched_lexeme_id!))
        .map((word) => ({
            lexemeId: word.matched_lexeme_id!,
            text: word.text,
            language: word.language,
            translation: word.translation,
            level: word.level,
            example: word.example,
            exampleTranslation: word.example_translation,
        })));
    const canonicalLexemeIds = [...new Set(lessonWords.map((word) => word.lexemeId))];
    const names = sourceKeys.map((key) => availableSources.value.find((source) => source.key === key)?.title).filter((title): title is string => Boolean(title));
    return { contentIds, canonicalLexemeIds, lessonWords, label: names.length === 0 ? 'Selected sources' : names.length < 3 ? names.join(' + ') : `${names.length} sources` };
}

async function launchPractice(options: LastPractice) {
    if (startingPractice.value) return;
    startingPractice.value = true;
    try {
    const normalized = { ...options, contentId: options.contentId || '', lexemeIds: options.lexemeIds || '' };
    let sourceScope: Awaited<ReturnType<typeof resolveSourceScope>> | null = null;
    if (normalized.sourceKeys) {
        sourceScope = normalized.sourceKeys.length > 0
            ? await resolveSourceScope(normalized.sourceKeys, normalized.lessonLexemeIds)
            : { contentIds: [], canonicalLexemeIds: [], lessonWords: [], label: 'All learning words' };
        normalized.contentId = '';
        normalized.lexemeIds = '';
    }
    lastPractice.value = normalized;
    try {
        localStorage.setItem(lastPracticeStorageKey.value, JSON.stringify(normalized));
    } catch {
        // Practice can still start when browser storage is unavailable.
    }
    answerStyle.value = normalized.answerStyle ?? answerStyle.value;
    sessionStarted.value = true;
    sourceChanged.value = false;
    if (normalized.sourceKeys) selectedSourceKeys.value = [...normalized.sourceKeys];
    showModeSheet.value = false;
    const query = { ...route.query, mode: normalized.mode } as Record<string, string | (string | null)[] | null | undefined>;
    if (normalized.sourceKeys) {
        delete query.content_id;
        delete query.lexeme_ids;
        delete query.lesson_id;
        delete query.lesson_lexeme_ids;
        if (normalized.sourceKeys.length > 0) query.source_ids = normalized.sourceKeys.join(',');
        else delete query.source_ids;
        if (normalized.sourceKeys.length === 1 && normalized.sourceKeys[0]?.startsWith('lesson:')) {
            query.lesson_id = normalized.sourceKeys[0].slice('lesson:'.length);
            if (normalized.lessonLexemeIds) query.lesson_lexeme_ids = normalized.lessonLexemeIds;
        } else if (normalized.sourceKeys.length === 1 && normalized.sourceKeys[0]?.startsWith('content:')) {
            query.content_id = normalized.sourceKeys[0].slice('content:'.length);
        }
    } else {
        if (normalized.contentId) query.content_id = normalized.contentId;
        else delete query.content_id;
        if (normalized.lexemeIds) query.lexeme_ids = normalized.lexemeIds;
        else delete query.lexeme_ids;
    }
    if (normalized.answerStyle) query.answer_style = normalized.answerStyle;
    else delete query.answer_style;
    await router.replace({ name: 'repetitions', query });
    if (sourceScope && normalized.sourceKeys?.length === 0) {
        await startSession(undefined, undefined, normalized.mode);
    } else if (sourceScope) await startSession({ ...sourceScope }, undefined, normalized.mode);
    else await startSession(normalized.contentId || undefined, normalized.lexemeIds ? normalized.lexemeIds.split(',').map(Number).filter((id) => Number.isInteger(id) && id > 0) : undefined, normalized.mode);
    } catch {
        sessionStarted.value = false;
        sourceLoadError.value = 'Could not load the selected sources. Try again.';
    } finally {
        startingPractice.value = false;
    }
}

function continuePractice() {
    if (sourceChanged.value || routeLessonId.value || routeSourceKeys.value.length > 0 || lastPractice.value?.sourceKeys) {
        const source = currentPracticeSource();
        void launchPractice({
            mode: route.query.mode ? selectedPracticeMode.value : lastPractice.value?.mode ?? 'adaptive',
            answerStyle: route.query.answer_style ? selectedAnswerStyle.value : lastPractice.value?.answerStyle ?? null,
            contentId: '',
            lexemeIds: '',
            sourceKeys: source.sourceKeys,
            lessonLexemeIds: source.lessonLexemeIds,
        });
        return;
    }
    if (sourceChanged.value || routeContentId.value || routeLexemeIds.value) {
        const source = currentPracticeSource();
        launchPractice({
            mode: route.query.mode ? selectedPracticeMode.value : lastPractice.value?.mode ?? 'adaptive',
            answerStyle: route.query.answer_style ? selectedAnswerStyle.value : lastPractice.value?.answerStyle ?? null,
            ...source,
        });
        return;
    }
    launchPractice(lastPractice.value ?? { mode: 'adaptive', answerStyle: null, contentId: '', lexemeIds: '' });
}

function selectLaunchMode(mode: LaunchMode) {
    draftLaunchMode.value = mode;
}

function startDraftPractice() {
    if (draftLaunchMode.value === 'adaptive') {
        launchPractice({ mode: 'adaptive', answerStyle: null, ...currentPracticeSource() });
    } else if (draftLaunchMode.value === 'cloze' || draftLaunchMode.value === 'listening') {
        launchPractice({ mode: draftLaunchMode.value, answerStyle: null, ...currentPracticeSource() });
    } else {
        const style = draftLaunchMode.value === 'recall' ? 'reveal' : draftLaunchMode.value;
        launchPractice({ mode: style === 'choose-word' ? 'cloze' : 'adaptive', answerStyle: style, ...currentPracticeSource() });
    }
}

function openModeSheet() {
    const priorStyle = lastPractice.value?.answerStyle ?? selectedAnswerStyle.value;
    draftLaunchMode.value = priorStyle === 'reveal'
        ? 'recall'
        : priorStyle ?? lastPractice.value?.mode ?? selectedPracticeMode.value;
    showModeSheet.value = true;
}

function openMode(mode: 'context' | 'speaking' | 'exam' | 'dictation' | 'shadowing') {
    const source = currentPracticeSource();
    if (mode === 'context') {
        router.push({ name: 'context-practice', query: { ...route.query, ...(source.contentId ? { content_id: source.contentId } : {}), ...(source.lexemeIds ? { lexeme_ids: source.lexemeIds } : {}), return_to: 'repetitions' } });
    } else if (mode === 'speaking') {
        router.push({ name: 'speaking-practice', query: { ...route.query, ...(source.contentId ? { content_id: source.contentId } : {}), ...(source.lexemeIds ? { lexeme_ids: source.lexemeIds } : {}), return_to: 'repetitions' } });
    } else if (mode === 'dictation' || mode === 'shadowing') {
        if (source.contentId) router.push({ name: 'transcript-exercise', query: { content_id: source.contentId, mode, return_to: 'repetitions' } });
    } else if (source.contentId) {
        router.push({ name: 'catalog.exam', params: { id: source.contentId }, query: route.query });
    }
}

function openSentenceMode(mode: 'read' | 'write-flexible' | 'write-exact' | 'reorder') {
    const source = currentPracticeSource();
    router.push({
        name: 'speaking-practice',
        query: { ...route.query, ...(source.contentId ? { content_id: source.contentId } : {}), ...(source.lexemeIds ? { lexeme_ids: source.lexemeIds } : {}), sentence_mode: mode, return_to: 'repetitions' },
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
    try {
        const saved = localStorage.getItem(lastPracticeStorageKey.value);
        if (saved) {
            const parsed = JSON.parse(saved) as LastPractice;
            const validAnswerStyle = parsed.answerStyle === null || ['reveal', 'type', 'tap-letters', 'choose-word'].includes(parsed.answerStyle);
            if (['adaptive', 'cloze', 'listening'].includes(parsed.mode) && validAnswerStyle && typeof parsed.contentId === 'string' && typeof parsed.lexemeIds === 'string') {
                lastPractice.value = parsed;
            }
        }
    } catch {
        lastPractice.value = null;
    }
    selectedSourceKeys.value = defaultSourceKeys();
    await Promise.all([
        ensureCatalogLoaded(),
        (async () => {
            const firstPage = await lessonApi.list(1, 'all');
            const pages = firstPage.meta.last_page ?? 1;
            const remaining = await Promise.all(Array.from({ length: Math.max(0, pages - 1) }, (_, index) => lessonApi.list(index + 2, 'all')));
            lessonSources.value = [...(firstPage.data ?? []), ...remaining.flatMap((page) => page.data ?? [])];
        })(),
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
                    <div class="truncate text-sm font-semibold text-fg">{{ sessionStarted ? scopeLabel : contentLabel }}</div>
                    <div class="text-xs text-muted-foreground">{{ sessionStarted ? modeLabel : lastPractice ? 'Ready to continue' : 'Start with a balanced review' }}</div>
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
                    <UiButton v-if="!sessionStarted" variant="ghost" size="icon" aria-label="Change sources" title="Change sources" @click="openSourcePicker">
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
            <section v-if="!sessionStarted" class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-4 px-4 py-4 sm:py-8">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.16em] text-primary">Your study session</div>
                    <h1 class="mt-1 text-2xl font-semibold tracking-tight text-fg">Practice</h1>
                    <div class="mt-2 flex flex-wrap gap-3 text-sm">
                        <button type="button" class="font-medium text-primary hover:underline" @click="router.push({ name: 'speaking-practice' })">Practise my mistakes</button>
                        <button type="button" class="font-medium text-fg-secondary hover:text-primary hover:underline" @click="router.push({ name: 'speaking-mistakes' })">View mistake report</button>
                    </div>
                </div>

                <UiButton class="w-full" variant="secondary" @click="openSentenceMode('write-flexible')">Translate a sentence from my words</UiButton>

                <UiCard class="space-y-4 border-primary/25 bg-primary/5 p-4 sm:p-5">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 text-xs font-medium text-fg-secondary">
                            <BookOpen :size="15" class="shrink-0 text-primary" />
                            <span class="truncate">{{ contentLabel }}</span>
                        </div>
                        <div class="mt-3 flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <h2 class="truncate text-lg font-semibold text-fg">{{ lastPracticeModeLabel }}</h2>
                                <p class="mt-0.5 truncate text-sm text-muted-foreground">{{ activeFlow?.name ?? 'Your saved practice settings' }}<span v-if="activeFlowConfig.session_minutes"> · ~{{ activeFlowConfig.session_minutes }} min</span></p>
                            </div>
                            <UiButton variant="secondary" size="sm" class="shrink-0" @click="openModeSheet">Change</UiButton>
                        </div>
                    </div>
                    <button type="button" class="flex min-h-14 w-full items-center justify-center gap-2 rounded-spa-lg bg-primary px-5 py-3 text-base font-semibold text-primary-foreground shadow-sm transition hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:cursor-wait disabled:opacity-70" :disabled="startingPractice" @click="continuePractice">
                        <Sparkles :size="18" />
                        {{ startingPractice ? 'Preparing practice…' : launchButtonLabel }}
                    </button>
                    <div class="flex min-h-11 flex-wrap items-center justify-between gap-x-4 gap-y-1 border-t border-primary/15 pt-3 text-sm">
                        <button type="button" class="font-medium text-primary hover:underline" @click="openLearningFlow">Edit learning flow</button>
                        <button type="button" class="font-medium text-fg-secondary hover:text-primary" @click="showCustomize ? cancelSourcePicker() : openSourcePicker()">Change source</button>
                    </div>
                </UiCard>
                <p v-if="sourceLoadError" class="text-sm text-warning" role="alert">{{ sourceLoadError }}</p>

                <details class="rounded-spa-lg border border-border bg-surface">
                    <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-3 px-4 text-sm font-semibold text-fg [&::-webkit-details-marker]:hidden">
                        <span class="flex items-center gap-2"><SlidersHorizontal :size="16" class="text-primary" /> More practice</span>
                        <ChevronDown :size="16" class="text-muted-foreground" />
                    </summary>
                    <div class="grid grid-cols-1 gap-2 border-t border-border p-3 sm:grid-cols-2">
                        <button type="button" class="flex min-h-12 items-center gap-3 rounded-lg border border-border px-3 py-2 text-left text-sm hover:border-primary/50" @click="startAnswerStyleMode('reveal')"><Eye :size="17" class="text-primary" /><span><strong class="block font-medium">Recall</strong><span class="text-xs text-muted-foreground">Reveal and self-grade</span></span></button>
                        <button type="button" class="flex min-h-12 items-center gap-3 rounded-lg border border-border px-3 py-2 text-left text-sm hover:border-primary/50" @click="startAnswerStyleMode('type')"><Keyboard :size="17" class="text-primary" /><span><strong class="block font-medium">Type it</strong><span class="text-xs text-muted-foreground">Write the answer yourself</span></span></button>
                        <button type="button" class="flex min-h-12 items-center gap-3 rounded-lg border border-border px-3 py-2 text-left text-sm hover:border-primary/50" @click="startAnswerStyleMode('tap-letters')"><ListChecks :size="17" class="text-primary" /><span><strong class="block font-medium">Tap letters</strong><span class="text-xs text-muted-foreground">Build the word step by step</span></span></button>
                        <button type="button" class="flex min-h-12 items-center gap-3 rounded-lg border border-border px-3 py-2 text-left text-sm hover:border-primary/50" @click="startAnswerStyleMode('choose-word')"><Puzzle :size="17" class="text-primary" /><span><strong class="block font-medium">Choose the word</strong><span class="text-xs text-muted-foreground">Complete a sentence</span></span></button>
                        <button type="button" class="flex min-h-12 items-center gap-3 rounded-lg border border-border px-3 py-2 text-left text-sm hover:border-primary/50" @click="startFocusedMode('cloze')"><Puzzle :size="17" class="text-primary" /><span><strong class="block font-medium">Cloze</strong><span class="text-xs text-muted-foreground">Choose the missing word</span></span></button>
                        <button type="button" class="flex min-h-12 items-center gap-3 rounded-lg border border-border px-3 py-2 text-left text-sm hover:border-primary/50" @click="startFocusedMode('listening')"><Headphones :size="17" class="text-primary" /><span><strong class="block font-medium">Listening</strong><span class="text-xs text-muted-foreground">Hear and recognize words</span></span></button>
                        <button type="button" class="flex min-h-12 items-center gap-3 rounded-lg border border-border px-3 py-2 text-left text-sm hover:border-primary/50 disabled:opacity-40" :disabled="!routeContentId" @click="openMode('dictation')"><PenLine :size="17" class="text-primary" /><span><strong class="block font-medium">Dictation</strong><span class="text-xs text-muted-foreground">Listen, then type a phrase</span></span></button>
                        <button type="button" class="flex min-h-12 items-center gap-3 rounded-lg border border-border px-3 py-2 text-left text-sm hover:border-primary/50 disabled:opacity-40" :disabled="!routeContentId" @click="openMode('shadowing')"><Mic2 :size="17" class="text-primary" /><span><strong class="block font-medium">Shadowing</strong><span class="text-xs text-muted-foreground">Listen, then repeat aloud</span></span></button>
                        <button type="button" class="flex min-h-12 items-center gap-3 rounded-lg border border-border px-3 py-2 text-left text-sm hover:border-primary/50" @click="openMode('context')"><Languages :size="17" class="text-primary" /><span><strong class="block font-medium">Context</strong><span class="text-xs text-muted-foreground">Practice in real sentences</span></span></button>
                        <button type="button" class="flex min-h-12 items-center gap-3 rounded-lg border border-border px-3 py-2 text-left text-sm hover:border-primary/50 disabled:opacity-40" :disabled="!routeContentId" @click="openMode('exam')"><ClipboardCheck :size="17" class="text-primary" /><span><strong class="block font-medium">Ready check</strong><span class="text-xs text-muted-foreground">Test words from this content</span></span></button>
                        <button type="button" class="flex min-h-12 items-center gap-3 rounded-lg border border-border px-3 py-2 text-left text-sm hover:border-primary/50" @click="openSentenceMode('read')"><BookOpen :size="17" class="text-primary" /><span><strong class="block font-medium">Read & reveal</strong><span class="text-xs text-muted-foreground">Read a sentence and meaning</span></span></button>
                        <button type="button" class="flex min-h-12 items-center gap-3 rounded-lg border border-border px-3 py-2 text-left text-sm hover:border-primary/50" @click="openSentenceMode('write-flexible')"><Keyboard :size="17" class="text-primary" /><span><strong class="block font-medium">Write a translation</strong><span class="text-xs text-muted-foreground">Use your own natural wording</span></span></button>
                        <button type="button" class="flex min-h-12 items-center gap-3 rounded-lg border border-border px-3 py-2 text-left text-sm hover:border-primary/50" @click="openSentenceMode('write-exact')"><CheckSquare :size="17" class="text-primary" /><span><strong class="block font-medium">Match the translation</strong><span class="text-xs text-muted-foreground">Write the model sentence</span></span></button>
                        <button type="button" class="flex min-h-12 items-center gap-3 rounded-lg border border-border px-3 py-2 text-left text-sm hover:border-primary/50" @click="openSentenceMode('reorder')"><ListChecks :size="17" class="text-primary" /><span><strong class="block font-medium">Build a sentence</strong><span class="text-xs text-muted-foreground">Put the words in order</span></span></button>
                    </div>
                </details>
            </section>

            <div v-if="showModeSheet && !sessionStarted" class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 sm:items-center sm:p-4" role="presentation" @click.self="showModeSheet = false">
                <section class="max-h-[88dvh] w-full max-w-xl overflow-hidden rounded-t-2xl border border-border bg-surface shadow-2xl sm:rounded-2xl" role="dialog" aria-modal="true" aria-labelledby="practice-mode-title">
                    <div class="max-h-[88dvh] overflow-y-auto px-4 pb-[max(1rem,env(safe-area-inset-bottom))] pt-3 sm:p-5">
                        <div class="mx-auto mb-3 h-1 w-10 rounded-full bg-border sm:hidden" aria-hidden="true"></div>
                        <div class="flex items-center justify-between gap-3">
                            <div><p class="text-xs text-muted-foreground">Practice settings</p><h2 id="practice-mode-title" class="text-lg font-semibold text-fg">Choose a mode</h2></div>
                            <UiButton variant="ghost" size="sm" @click="showModeSheet = false">Close</UiButton>
                        </div>
                        <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Recommended</p>
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            <button v-for="choice in [
                                { mode: 'adaptive' as LaunchMode, label: 'Balanced', detail: 'Reviews and new words', icon: Sparkles },
                                { mode: 'recall' as LaunchMode, label: 'Recall', detail: 'Reveal and self-grade', icon: Eye },
                                { mode: 'type' as LaunchMode, label: 'Type it', detail: 'Write the answer', icon: Keyboard },
                                { mode: 'tap-letters' as LaunchMode, label: 'Tap letters', detail: 'Build the word', icon: ListChecks },
                                { mode: 'choose-word' as LaunchMode, label: 'Choose the word', detail: 'Complete a sentence', icon: Puzzle },
                                { mode: 'cloze' as LaunchMode, label: 'Cloze', detail: 'Fill the missing word', icon: Puzzle },
                                { mode: 'listening' as LaunchMode, label: 'Listening', detail: 'Hear and recognize', icon: Headphones },
                            ]" :key="choice.mode" type="button" class="flex min-h-16 items-center gap-2 rounded-xl border px-3 py-2 text-left transition" :class="draftLaunchMode === choice.mode ? 'border-primary bg-primary/10 ring-1 ring-primary' : 'border-border bg-background hover:border-primary/50'" :aria-pressed="draftLaunchMode === choice.mode" @click="selectLaunchMode(choice.mode)">
                                <component :is="choice.icon" :size="17" class="shrink-0 text-primary" />
                                <span class="min-w-0"><span class="block truncate text-sm font-semibold text-fg">{{ choice.label }}</span><span class="block truncate text-xs text-muted-foreground">{{ choice.detail }}</span></span>
                            </button>
                        </div>
                        <details class="mt-3 rounded-xl border border-border px-3">
                            <summary class="flex min-h-11 cursor-pointer items-center justify-between text-sm font-medium text-fg">More practice <ChevronDown :size="16" class="text-muted-foreground" /></summary>
                            <div class="grid grid-cols-2 gap-2 border-t border-border py-3">
                                <button type="button" class="min-h-11 rounded-lg border border-border px-3 text-left text-sm hover:border-primary/50" @click="openMode('context')">Context</button>
                                <button type="button" class="min-h-11 rounded-lg border border-border px-3 text-left text-sm hover:border-primary/50" @click="openMode('speaking')">Speaking</button>
                                <button type="button" class="min-h-11 rounded-lg border border-border px-3 text-left text-sm hover:border-primary/50 disabled:opacity-40" :disabled="!routeContentId && !lastPractice?.contentId" @click="openMode('dictation')">Dictation</button>
                                <button type="button" class="min-h-11 rounded-lg border border-border px-3 text-left text-sm hover:border-primary/50 disabled:opacity-40" :disabled="!routeContentId && !lastPractice?.contentId" @click="openMode('shadowing')">Shadowing</button>
                                <button type="button" class="min-h-11 rounded-lg border border-border px-3 text-left text-sm hover:border-primary/50 disabled:opacity-40" :disabled="!routeContentId && !lastPractice?.contentId" @click="openMode('exam')">Ready check</button>
                                <button type="button" class="min-h-11 rounded-lg border border-border px-3 text-left text-sm hover:border-primary/50" @click="openSentenceMode('read')">Read a sentence</button>
                            </div>
                        </details>
                        <div class="sticky bottom-0 mt-4 bg-surface pt-2">
                            <UiButton variant="primary" size="lg" class="w-full" @click="startDraftPractice">Use this mode</UiButton>
                        </div>
                    </div>
                </section>
            </div>

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

        <div v-if="showCustomize" class="fixed inset-0 z-50 flex items-end justify-center bg-black/55 p-0 sm:items-center sm:p-4" role="presentation" @click.self="cancelSourcePicker">
            <section class="flex max-h-[90dvh] w-full max-w-xl flex-col overflow-hidden rounded-t-2xl border border-border bg-surface shadow-2xl sm:rounded-2xl" role="dialog" aria-modal="true" aria-labelledby="practice-sources-title">
                <div class="flex items-start justify-between gap-3 border-b border-border px-4 py-3 sm:px-5">
                    <div class="min-w-0">
                        <h2 id="practice-sources-title" class="text-lg font-semibold text-fg">Choose sources</h2>
                        <p class="mt-0.5 text-sm text-muted-foreground">Select one or more lessons or catalog items.</p>
                    </div>
                    <UiButton variant="ghost" size="sm" aria-label="Close source picker" @click="cancelSourcePicker">Close</UiButton>
                </div>

                <div class="space-y-3 overflow-y-auto px-3 py-3 sm:px-5">
                    <label class="flex min-h-12 cursor-pointer items-center gap-3 rounded-lg border px-3 py-2 text-sm" :class="sourceDraftAll ? 'border-primary bg-primary/5' : 'border-border bg-background'">
                        <input type="radio" name="practice-source-scope" class="h-5 w-5 shrink-0 accent-primary" :checked="sourceDraftAll" @change="selectAllSources" />
                        <span class="min-w-0 flex-1"><span class="block font-medium text-fg">All learning words</span><span class="block text-xs text-muted-foreground">Use your full practice queue</span></span>
                    </label>

                    <label class="block">
                        <span class="sr-only">Search sources</span>
                        <input v-model="sourceSearch" type="search" class="h-11 w-full rounded-md border border-border bg-background px-3 text-base text-fg placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" placeholder="Search lessons and content" />
                    </label>

                    <div class="hidden grid-cols-[auto_minmax(0,1fr)_minmax(9rem,auto)] gap-3 px-3 text-xs font-medium text-muted-foreground sm:grid" aria-hidden="true">
                        <span></span><span>Source</span><span>Type and words</span>
                    </div>
                    <div class="space-y-1" aria-label="Available practice sources">
                        <label v-for="source in filteredSources" :key="source.key" class="grid min-h-14 cursor-pointer grid-cols-[auto_minmax(0,1fr)] items-center gap-x-3 rounded-lg border border-transparent px-3 py-2 hover:border-border hover:bg-background sm:grid-cols-[auto_minmax(0,1fr)_minmax(9rem,auto)]">
                            <input type="checkbox" class="row-start-1 h-5 w-5 rounded border-border accent-primary" :checked="sourceDraftKeys.includes(source.key)" @change="toggleSourceDraft(source.key)" />
                            <span class="row-start-1 min-w-0"><span class="block truncate text-sm font-medium text-fg">{{ source.title }}</span><span class="block truncate text-xs text-muted-foreground sm:hidden">{{ source.kind }} · {{ source.count }} words</span></span>
                            <span class="col-start-2 row-start-2 text-xs text-muted-foreground sm:col-start-3 sm:row-start-1 sm:text-right"><span class="hidden font-medium text-fg-secondary sm:block">{{ source.kind }}</span><span>{{ source.detail }}</span></span>
                        </label>
                        <p v-if="filteredSources.length === 0" class="px-3 py-6 text-center text-sm text-muted-foreground">No matching sources.</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-border bg-surface px-4 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] sm:px-5">
                    <p class="text-sm text-muted-foreground">{{ sourceDraftAll ? 'All learning words' : sourceDraftKeys.length === 0 ? 'Choose at least one source' : `${sourceDraftKeys.length} source${sourceDraftKeys.length === 1 ? '' : 's'} selected` }}</p>
                    <div class="flex gap-2">
                        <UiButton variant="ghost" size="sm" @click="cancelSourcePicker">Cancel</UiButton>
                        <UiButton variant="primary" size="sm" :disabled="!sourceDraftAll && sourceDraftKeys.length === 0" @click="applyCustomize">Save sources</UiButton>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
