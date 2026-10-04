<script setup lang="ts">
import axios from 'axios';
import { onMounted, computed, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { DropdownMenuContent, DropdownMenuItem, DropdownMenuPortal, DropdownMenuRoot, DropdownMenuTrigger } from 'reka-ui';
import { Check, Plus, Settings, Sparkles } from 'lucide-vue-next';
import PageState from '../components/ui/PageState.vue';
import { useAuthStore } from '../domains/user';
import { useContent, useLexemes, contentApi } from '../domains/content';
import { grammarApi } from '../domains/content';
import type { GrammarRule } from '../types';
import { extractYoutubeVideoId } from '../shared/youtube';
import AskAiButton from '../shared/ui/AskAiButton.vue';
import UiBadge from '../shared/ui/UiBadge.vue';
import UiButton from '../shared/ui/UiButton.vue';
import ExplainDialog from '../shared/ui/ExplainDialog.vue';
import UiCard from '../shared/ui/UiCard.vue';
import UiContentProgress from '../shared/ui/UiContentProgress.vue';
import UiEmptyState from '../shared/ui/UiEmptyState.vue';
import UiSectionHeader from '../shared/ui/UiSectionHeader.vue';
import UiTabs from '../shared/ui/UiTabs.vue';
import WordListItem from '../shared/ui/WordListItem.vue';
import WordListToolbar from '../shared/ui/WordListToolbar.vue';
import YoutubeEmbed from '../shared/ui/YoutubeEmbed.vue';
import TranscriptViewer from '../shared/ui/TranscriptViewer.vue';
import ReadinessStepper from '../widgets/content/ReadinessStepper.vue';
import type { LexemeWithLearned, TranscriptSegment } from '../types';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const { loading, error, content, loadContent } = useContent();
const contentId = computed(() => String(route.params.id));
const isOwner = computed(() => content.value?.created_by === authStore.user?.id);

const {
    loading: lexemesLoading,
    error: lexemesError,
    lexemes,
    markingId,
    startingReviewId,
    explainingId,
    explainError,
    fetchingExamplesId,
    aiUnavailable,
    bulkActionPending,
    bulkMarkLearned,
    bulkStartLearning,
    loadLexemes,
    markLearned,
    unmarkLearned,
    startLearning,
    stopLearning,
    skip,
    unskip,
    explainLexeme,
    fetchMoreExamples,
} = useLexemes();
const explanationModal = ref<{
    lexemeText: string;
    translation: string | null;
    language: string | null;
    explanation: string;
    loading: boolean;
    error: string;
} | null>(null);
const explainTarget = ref<LexemeWithLearned | null>(null);

// Task 7.8: level filter, status filter, "select all", and the bulk-action
// bar now live in WordListToolbar.vue (shared with StudyPage.vue). This page
// just keeps the toolbar's filtered output and the selection set.
const filteredLexemes = ref<LexemeWithLearned[]>([]);
const selectedIds = ref<Set<number>>(new Set());

function toggleSelect(lexeme: LexemeWithLearned) {
    const next = new Set(selectedIds.value);
    if (next.has(lexeme.id)) next.delete(lexeme.id);
    else next.add(lexeme.id);
    selectedIds.value = next;
}

function practiceSelected(ids: number[]) {
    if (ids.length === 0) return;
    router.push({ name: 'repetitions', query: { content_id: String(contentId.value), lexeme_ids: ids.join(',') } });
}

function practiceContext(ids: number[]) {
    if (ids.length === 0) return;
    router.push({ name: 'context-practice', query: { content_id: String(contentId.value), lexeme_ids: ids.join(',') } });
}

async function explainWord(lexeme: (typeof lexemes.value)[number]) {
    explainTarget.value = lexeme;
    explanationModal.value = {
        lexemeText: lexeme.text,
        translation: lexeme.translation ?? null,
        language: content.value?.language ?? null,
        explanation: '',
        loading: true,
        error: '',
    };
    const result = await explainLexeme(lexeme);
    if (explanationModal.value === null) return;
    if (result) {
        explanationModal.value = { ...explanationModal.value, explanation: result.explanation, loading: false };
    } else {
        explanationModal.value = { ...explanationModal.value, loading: false, error: explainError.value || 'Failed to get explanation.' };
    }
}

function retryExplanation() {
    if (explainTarget.value) void explainWord(explainTarget.value);
}

const grammarRules = ref<GrammarRule[]>([]);
const grammarLoading = ref(true);

const showFullText = ref(false);
const copyLabel = ref('Copy');

// Task 7.7: Words/Grammar/Full text as tabs instead of stacked cards.
// UiTabs keeps all panels mounted (v-show), so the Words tab's search text
// isn't lost when flipping to Grammar and back. Words is first/default so
// it's the first thing visible after the compact header — source link,
// "ready to watch" progress, and the other next-step actions moved into
// "About" instead of sitting above the word list.
const detailTabs = [
    { key: 'words', label: 'Words' },
    { key: 'grammar', label: 'Grammar' },
    { key: 'fulltext', label: 'Full text' },
    { key: 'about', label: 'About' },
];
const activeDetailTab = ref('words');

const youtubeVideoId = computed(() =>
    content.value?.type === 'youtube' ? extractYoutubeVideoId(content.value.source_url) : null,
);
const transcriptSegments = ref<TranscriptSegment[]>([]);
const transcriptLoading = ref(false);
const transcriptError = ref('');
const transcriptTranslations = ref<Record<number, string>>({});
const transcriptTranslationsLoading = ref(false);
const transcriptTranslationsStatus = ref<'idle' | 'pending' | 'ready' | 'error'>('idle');
const transcriptTranslationPolls = ref(0);
const activeTranscriptSegmentId = ref<number | null>(null);
const youtubeRef = ref<InstanceType<typeof YoutubeEmbed> | null>(null);
// Task 10.7: carries the full LexemeWithLearned row (not just id/text/
// translation) so the card below can render the exact same WordListItem
// used on the Words tab — same badges, same mark-learned/start-review/skip
// buttons — instead of a separate, thinner ad-hoc display.
const transcriptWord = ref<{ lexeme: LexemeWithLearned | null; segment: TranscriptSegment; loading?: boolean; error?: string } | null>(null);

// Every transcript word or selected phrase is clickable now (TranscriptViewer.vue),
// not just the ones the AI candidate pipeline already matched. A click/selection
// on an untracked span (lexemeId === null) looks it up on demand via the
// AI-backed endpoint (task 10.6 — full enrichment pipeline, not a bare
// translation), which also creates the ContentLexeme so the word list picks
// it up too; reloading that list and reading the row back out of it is what
// gives this card the exact same fields the Words tab already computes
// (learned/skipped/confidence/etc.) instead of duplicating that computation.
async function onTranscriptWordClick(payload: { lexemeId: number | null; text: string; startOffset: number; endOffset: number; segment: TranscriptSegment }): Promise<void> {
    if (payload.lexemeId !== null) {
        transcriptWord.value = {
            lexeme: lexemes.value.find((item) => item.id === payload.lexemeId) ?? null,
            segment: payload.segment,
        };
        return;
    }

    if (!authStore.isAuthenticated) {
        transcriptWord.value = { lexeme: null, segment: payload.segment, error: 'Log in to look up new words.' };
        return;
    }

    transcriptWord.value = { lexeme: null, segment: payload.segment, loading: true };
    try {
        const result = await contentApi.createTranscriptLexeme(contentId.value as string, {
            transcript_segment_id: payload.segment.id,
            text: payload.text,
            start_offset: payload.startOffset,
            end_offset: payload.endOffset,
        });

        const segment = transcriptSegments.value.find((item) => item.id === payload.segment.id);
        segment?.lexemes.push({
            content_lexeme_id: result.content_lexeme_id,
            text: result.text,
            start_offset: payload.startOffset,
            end_offset: payload.endOffset,
            surface_text: payload.text,
            match_type: 'manual',
        });

        await loadLexemes(contentId.value);
        transcriptWord.value = {
            lexeme: lexemes.value.find((item) => item.id === result.content_lexeme_id) ?? null,
            segment: payload.segment,
        };
    } catch (error) {
        // 429 (daily AI limit) and 503 (AI disabled) carry a meaningful server
        // message; anything else is a generic failure worth retrying.
        const status = axios.isAxiosError(error) ? error.response?.status : undefined;
        const serverMessage = axios.isAxiosError(error) ? error.response?.data?.message : undefined;
        const message = (status === 429 || status === 503) && typeof serverMessage === 'string'
            ? serverMessage
            : 'Lookup failed — try again.';
        transcriptWord.value = { lexeme: null, segment: payload.segment, error: message };
    }
}

async function loadTranscript(): Promise<void> {
    transcriptLoading.value = true;
    transcriptError.value = '';
    try {
        const response = await contentApi.getTranscript(contentId.value as string);
        transcriptSegments.value = response.segments ?? [];
    } catch {
        transcriptError.value = 'Transcript timing is not available for this content yet.';
    } finally {
        transcriptLoading.value = false;
    }
}

function onVideoTimeUpdate(timeMs: number): void {
    const active = [...transcriptSegments.value].reverse().find((segment) => {
        const end = segment.end_ms ?? segment.start_ms + 10000;
        return timeMs >= segment.start_ms && timeMs < end;
    });
    activeTranscriptSegmentId.value = active?.id ?? null;
}

function replaySegment(segment: TranscriptSegment): void {
    activeTranscriptSegmentId.value = segment.id;
    youtubeRef.value?.replaySegment(segment.start_ms, segment.end_ms);
}

async function loadTranscriptTranslations(): Promise<void> {
    if (transcriptTranslationsLoading.value || transcriptTranslationsStatus.value === 'ready' || transcriptTranslationPolls.value >= 15 || !authStore.isAuthenticated) return;
    transcriptTranslationsLoading.value = true;
    try {
        const response = await contentApi.getTranscriptTranslations(contentId.value as string);
        transcriptTranslations.value = response.translations ?? {};
        if (response.status === 'pending') {
            transcriptTranslationsStatus.value = 'pending';
            transcriptTranslationPolls.value += 1;
            window.setTimeout(() => {
                transcriptTranslationsLoading.value = false;
                loadTranscriptTranslations();
            }, 2000);
            return;
        }
        transcriptTranslationsStatus.value = 'ready';
    } catch {
        transcriptTranslations.value = {};
        transcriptTranslationsStatus.value = 'error';
    } finally {
        transcriptTranslationsLoading.value = false;
    }
}

// Task 6.1: AskAiButton's context — id/title only exist once content has
// loaded, so this is only rendered once `content` is truthy (see template).
const aiContext = computed(() => ({
    type: 'content' as const,
    id: content.value?.id ?? '',
    title: content.value?.title ?? '',
}));

// Lets the submitter re-run AI extraction on demand (e.g. content added
// before the extraction prompt got more generous, or their profile's level
// changed) — the job is async, so this only confirms it started; the new
// words show up once loadLexemes() is re-run, same as onAiSuggestionsAccepted.
const reanalyzing = ref(false);
const reanalyzeMessage = ref('');

async function reanalyze(): Promise<void> {
    if (!content.value) return;
    reanalyzing.value = true;
    reanalyzeMessage.value = '';
    try {
        await contentApi.reanalyze(content.value.id);
        reanalyzeMessage.value = 'Analysis started — new words will appear here shortly.';
    } catch (e: unknown) {
        const err = e as { response?: { status?: number; data?: { message?: string } } };
        reanalyzeMessage.value = err.response?.data?.message ?? 'Failed to start analysis.';
    } finally {
        reanalyzing.value = false;
    }
}

const fullTextWordCount = computed(() => {
    const text = content.value?.source_text?.trim();
    return text ? text.split(/\s+/).length : 0;
});

async function copyFullText() {
    const text = content.value?.source_text;
    if (!text) return;
    try {
        await navigator.clipboard.writeText(text);
        copyLabel.value = 'Copied!';
    } catch {
        copyLabel.value = 'Copy failed';
    } finally {
        setTimeout(() => (copyLabel.value = 'Copy'), 1500);
    }
}

async function loadGrammarRules(): Promise<void> {
    grammarLoading.value = true;
    try {
        const data = await grammarApi.getForContent(contentId.value as string);
        grammarRules.value = data.rules ?? [];
    } catch {
        grammarRules.value = [];
    } finally {
        grammarLoading.value = false;
    }
}

async function quickAddToMyList(rule: GrammarRule): Promise<void> {
    await grammarApi.startLearning(rule.id);
    rule.in_my_list = true;
}

// Calculated confidence (from practice) takes priority over the learner's
// own manual rating when both exist — see GrammarConfidenceService's
// docblock for why exam/SRS signal is weighted over self-report.
function confidenceLabel(rule: GrammarRule): string | null {
    const calculated = rule.confidence_calculated;
    const manual = rule.confidence_manual;
    if (calculated == null && manual == null) return null;
    if (calculated != null) return `Confidence: ${Math.round(calculated)}%${manual != null ? ` (you rated ${Math.round(manual)}%)` : ''}`;
    return `Your rating: ${Math.round(manual as number)}%`;
}

function sourceLinkLabel(): string {
    switch (content.value?.type) {
        case 'youtube':
            return 'Open source video';
        case 'song':
            return 'Open source audio';
        case 'book':
            return 'Open source text';
        case 'movie':
            return 'Movie info';
        default:
            return 'Open source';
    }
}

onMounted(() => {
    loadContent(contentId.value);
    loadGrammarRules();
    if (authStore.isAuthenticated) {
        loadLexemes(contentId.value);
    }
    loadTranscript();
});
</script>

<template>
    <PageState :loading="loading" :error="error">
        <template #retry>
            <UiButton variant="secondary" size="sm" @click="loadContent(contentId)">Try again</UiButton>
        </template>
        <div class="space-y-6">
            <UiCard class="space-y-3">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 space-y-1.5">
                        <h2 class="truncate text-xl font-semibold text-fg">{{ content?.title }}</h2>
                        <div class="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
                            <span class="capitalize">{{ content?.type }}</span>
                            <span aria-hidden="true">·</span>
                            <span class="uppercase">{{ content?.language }}</span>
                            <UiBadge v-if="content?.level" tone="neutral">{{ content?.level }}</UiBadge>
                            <UiBadge tone="success">{{ content?.status }}</UiBadge>
                        </div>
                    </div>

                    <DropdownMenuRoot v-if="isOwner && content">
                        <DropdownMenuTrigger
                            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            aria-label="Content settings"
                        >
                            <Settings :size="18" />
                        </DropdownMenuTrigger>
                        <DropdownMenuPortal>
                            <DropdownMenuContent
                                class="z-50 min-w-[240px] overflow-hidden rounded-md border border-border bg-popover p-1 text-popover-foreground shadow-[0_18px_50px_rgba(15,23,42,0.12)]"
                                align="end"
                                :side-offset="6"
                            >
                                <DropdownMenuItem
                                    class="flex cursor-default select-none items-center gap-2 rounded-md px-3 py-2 text-sm text-foreground outline-none transition-colors data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-40"
                                    :disabled="reanalyzing"
                                    title="Not enough AI words? Re-run AI extraction on this content's current text."
                                    @select="reanalyze"
                                >
                                    <Sparkles :size="14" />
                                    {{ reanalyzing ? 'Starting...' : 'Re-analyze with AI' }}
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenuPortal>
                    </DropdownMenuRoot>
                </div>

                <UiContentProgress v-if="authStore.isAuthenticated" :learned-count="content?.learned_count" :in-learning-count="content?.in_learning_count" :total-lexemes="content?.total_lexemes" />
                <p v-else class="text-xs text-muted-foreground">Log in to track your progress on this content.</p>

                <p v-if="reanalyzeMessage" class="text-xs" :class="reanalyzeMessage.startsWith('Analysis started') ? 'text-success-fg' : 'text-warning'">
                    {{ reanalyzeMessage }}
                </p>

                <!-- VIK-38: labels say what happens; one primary action. -->
                <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                    <UiButton variant="primary" size="touch" @click="router.push({ name: 'repetitions', query: { content_id: content?.id } })">Practice these words</UiButton>
                    <AskAiButton v-if="content" :context="aiContext" label="Explain with AI" variant="secondary" size="touch" />
                </div>
            </UiCard>

            <UiCard v-if="youtubeVideoId || content?.source_url" class="space-y-3">
                <div v-if="youtubeVideoId" class="grid gap-4 xl:grid-cols-[minmax(0,1.25fr)_minmax(320px,0.75fr)]">
                    <YoutubeEmbed
                        ref="youtubeRef"
                        :video-id="youtubeVideoId"
                        :title="content?.title"
                        @time-update="onVideoTimeUpdate"
                    />
                    <div>
                        <TranscriptViewer
                            v-if="transcriptSegments.length > 0"
                            :segments="transcriptSegments"
                            :active-segment-id="activeTranscriptSegmentId"
                            :native-translations="transcriptTranslations"
                            @seek="replaySegment"
                            @mode-change="($event === 'native' || $event === 'both') && loadTranscriptTranslations()"
                            @word-click="onTranscriptWordClick($event)"
                        />
                        <p v-else-if="transcriptLoading" class="text-sm text-muted-foreground">Loading timed transcript...</p>
                        <p v-else class="text-sm text-muted-foreground">{{ transcriptError || 'Timed transcript is not available yet.' }}</p>
                    </div>
                </div>
                <a
                    v-else-if="content?.source_url"
                    :href="content.source_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-sm text-primary underline"
                >
                    {{ sourceLinkLabel() }}
                </a>
            </UiCard>

            <UiCard v-if="transcriptWord" class="space-y-3 border-primary/30 bg-primary/5">
                <div class="flex items-start justify-between gap-3">
                    <p v-if="transcriptWord.loading" class="text-sm text-muted-foreground">Looking up...</p>
                    <p v-else-if="transcriptWord.error" class="text-sm text-warning">{{ transcriptWord.error }}</p>
                    <!-- Task 10.7: the exact same card as the Words tab — same badges, same mark-learned/start-review/skip buttons — instead of a separate, thinner display. -->
                    <WordListItem
                        v-else-if="transcriptWord.lexeme"
                        default-expanded
                        :lexeme="transcriptWord.lexeme"
                        :language="content?.language"
                        :marking="markingId === transcriptWord.lexeme.id"
                        :starting-review="startingReviewId === transcriptWord.lexeme.id"
                        :explaining="explainingId === transcriptWord.lexeme.id"
                        :fetching-examples="fetchingExamplesId === transcriptWord.lexeme.id"
                        :ai-unavailable="aiUnavailable"
                        class="flex-1"
                        @mark-learned="markLearned"
                        @unmark-learned="unmarkLearned"
                        @start-review="startLearning"
                        @stop-review="stopLearning"
                        @explain="explainWord"
                        @skip="skip"
                        @unskip="unskip"
                        @more-examples="fetchMoreExamples"
                    />
                    <UiButton variant="ghost" size="sm" @click="transcriptWord = null">Close</UiButton>
                </div>
                <p class="text-xs text-muted-foreground">{{ transcriptWord.segment.text }}</p>
                <div v-if="transcriptWord.lexeme" class="flex flex-wrap gap-2">
                    <UiButton variant="primary" size="sm" @click="router.push({ name: 'catalog.study', params: { id: content?.id } })">Open words</UiButton>
                    <UiButton variant="secondary" size="sm" @click="router.push({ name: 'repetitions', query: { content_id: content?.id } })">Practice content</UiButton>
                    <UiButton variant="primary" size="sm" @click="router.push({ name: 'transcript-exercise', query: { content_id: content?.id, content_lexeme_id: transcriptWord.lexeme.id, segment_id: transcriptWord.segment.id, mode: 'dictation' } })">Dictation</UiButton>
                    <UiButton variant="secondary" size="sm" @click="router.push({ name: 'transcript-exercise', query: { content_id: content?.id, content_lexeme_id: transcriptWord.lexeme.id, segment_id: transcriptWord.segment.id, mode: 'shadowing' } })">Shadowing</UiButton>
                    <UiButton variant="ghost" size="sm" @click="replaySegment(transcriptWord.segment)">Replay phrase</UiButton>
                </div>
            </UiCard>

            <UiTabs :tabs="detailTabs" v-model="activeDetailTab">
                <template #words>
                    <!-- VIK-38: edge-to-edge on phones (bleeds through main's px-4), card from sm up. -->
                    <section class="-mx-4 sm:mx-0 sm:rounded-xl sm:border sm:border-border sm:bg-card sm:p-5">
                        <UiEmptyState
                            v-if="!authStore.isAuthenticated"
                            class="mx-4 sm:mx-0"
                            title="Log in to see the words"
                            description="Word lists and progress are tied to your account."
                        >
                            <UiButton variant="secondary" @click="router.push({ name: 'login', query: { redirect: route.fullPath } })">Log in</UiButton>
                        </UiEmptyState>
                        <template v-else>
                            <p v-if="lexemesError" class="mx-4 mb-2 text-sm text-warning sm:mx-0" role="alert">{{ lexemesError }}</p>
                            <WordListToolbar
                                v-if="lexemes.length > 0"
                                :lexemes="lexemes"
                                v-model:selected-ids="selectedIds"
                                :bulk-mark-learned="bulkMarkLearned"
                                :bulk-start-learning="bulkStartLearning"
                                :bulk-action-pending="bulkActionPending"
                                @update:filtered="filteredLexemes = $event"
                                @practice-selected="practiceSelected"
                                @practice-context="practiceContext"
                            >
                                <div v-if="filteredLexemes.length > 0" class="mt-2 divide-y divide-border border-y border-border sm:overflow-hidden sm:rounded-lg sm:border">
                                    <WordListItem
                                        v-for="lexeme in filteredLexemes"
                                        :key="lexeme.id"
                                        :lexeme="lexeme"
                                        :language="content?.language"
                                        :marking="markingId === lexeme.id"
                                        :starting-review="startingReviewId === lexeme.id"
                                        :explaining="explainingId === lexeme.id"
                                        :fetching-examples="fetchingExamplesId === lexeme.id"
                                        :ai-unavailable="aiUnavailable"
                                        selectable
                                        :selected="selectedIds.has(lexeme.id)"
                                        @mark-learned="markLearned"
                                        @unmark-learned="unmarkLearned"
                                        @start-review="startLearning"
                                        @stop-review="stopLearning"
                                        @explain="explainWord"
                                        @toggle-select="toggleSelect"
                                        @skip="skip"
                                        @unskip="unskip"
                                        @more-examples="fetchMoreExamples"
                                    />
                                </div>
                                <UiEmptyState
                                    v-else
                                    class="mx-4 mt-3 sm:mx-0"
                                    title="No words match"
                                    description="Try another status or filter."
                                />
                            </WordListToolbar>
                            <UiEmptyState
                                v-else-if="!lexemesLoading"
                                class="mx-4 sm:mx-0"
                                title="No words extracted yet"
                                description="Nothing has been extracted for this content yet."
                            />
                        </template>
                    </section>
                </template>

                <template #grammar>
                    <UiCard v-if="!grammarLoading" class="space-y-4">
                        <UiSectionHeader title="Grammar" subtitle="Constructions found in this content">
                            <template v-if="grammarRules.length > 0 && authStore.isAuthenticated" #actions>
                                <UiButton variant="secondary" size="sm" @click="router.push({ name: 'catalog.grammarWarmup', params: { id: content?.id } })">
                                    Warm up on these topics
                                </UiButton>
                            </template>
                        </UiSectionHeader>
                        <div v-if="grammarRules.length > 0" class="grid gap-3 md:grid-cols-2">
                            <div
                                v-for="rule in grammarRules"
                                :key="rule.id"
                                class="rounded-spa border border-border bg-surface-alt/45 p-4 transition-all hover:-translate-y-0.5 hover:border-primary hover:shadow-sm"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <RouterLink
                                        :to="{ name: 'grammar.details', params: { id: rule.id } }"
                                        class="font-medium text-fg hover:text-primary hover:underline"
                                    >
                                        {{ rule.title }}
                                    </RouterLink>
                                    <div class="flex shrink-0 items-center gap-1">
                                        <UiBadge v-if="rule.level" tone="primary">{{ rule.level }}</UiBadge>
                                        <UiBadge v-if="rule.in_my_list" tone="success" title="In your grammar list">
                                            <Check :size="12" />
                                        </UiBadge>
                                        <UiButton
                                            v-else-if="authStore.isAuthenticated"
                                            variant="ghost"
                                            size="sm"
                                            title="Add to my grammar"
                                            @click="quickAddToMyList(rule)"
                                        >
                                            <Plus :size="14" />
                                        </UiButton>
                                    </div>
                                </div>
                                <p v-if="rule.summary" class="mt-1 text-sm text-muted-foreground line-clamp-2">{{ rule.summary }}</p>
                                <p v-if="authStore.isAuthenticated && confidenceLabel(rule)" class="mt-2 text-xs text-muted-foreground">
                                    {{ confidenceLabel(rule) }}
                                </p>
                            </div>
                        </div>
                        <UiEmptyState
                            v-else
                            title="No grammar linked to this content"
                            description="Nothing has been extracted or tagged for this specific content yet — browse the full grammar catalog instead."
                        >
                            <UiButton variant="secondary" @click="router.push({ name: 'grammar' })">Browse grammar catalog</UiButton>
                        </UiEmptyState>
                    </UiCard>
                </template>

                <template #fulltext>
                    <UiCard v-if="content?.source_text" class="space-y-4">
                        <UiSectionHeader title="Full text" subtitle="The source transcript or body text behind this content">
                            <template #actions>
                                <span class="text-xs text-muted-foreground">{{ fullTextWordCount }} words</span>
                                <UiButton v-if="showFullText" variant="ghost" size="sm" @click="copyFullText">{{ copyLabel }}</UiButton>
                                <UiButton variant="secondary" size="sm" @click="showFullText = !showFullText">
                                    {{ showFullText ? 'Hide full text' : 'Show full text' }}
                                </UiButton>
                            </template>
                        </UiSectionHeader>
                        <div
                            v-if="showFullText"
                            class="max-h-96 overflow-y-auto rounded-spa border border-border bg-black/10 p-4 text-sm leading-6 text-fg-secondary whitespace-pre-wrap"
                        >{{ content.source_text }}</div>
                    </UiCard>
                </template>

                <template #about>
                    <div class="space-y-4">
                        <ReadinessStepper v-if="authStore.isAuthenticated && content" :content-id="content.id" />

                        <UiCard class="space-y-3">
                            <UiSectionHeader title="Next actions" subtitle="What to do from here" />
                            <div class="grid gap-2 sm:grid-cols-2">
                                <UiButton variant="primary" @click="router.push({ name: 'repetitions', query: { content_id: content?.id } })">Practice these words</UiButton>
                                <UiButton variant="secondary" @click="router.push({ name: 'catalog.study', params: { id: content?.id } })">Browse words</UiButton>
                                <UiButton
                                    v-if="authStore.isAuthenticated"
                                    variant="secondary"
                                    @click="router.push({ name: 'speaking-practice', query: { content_id: content?.id } })"
                                >
                                    Reinforce (speaking practice)
                                </UiButton>
                                <AskAiButton v-if="content" :context="aiContext" label="Discuss with AI" variant="ghost" />
                            </div>
                        </UiCard>
                    </div>
                </template>
            </UiTabs>

            <ExplainDialog
                v-if="explanationModal"
                :open="true"
                :lexeme-text="explanationModal.lexemeText"
                :translation="explanationModal.translation"
                :language="explanationModal.language"
                :explanation="explanationModal.explanation"
                :loading="explanationModal.loading"
                :error="explanationModal.error"
                @close="explanationModal = null"
                @retry="retryExplanation"
            />
        </div>
    </PageState>
</template>
