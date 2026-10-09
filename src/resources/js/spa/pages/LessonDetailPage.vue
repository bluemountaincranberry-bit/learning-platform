<script setup lang="ts">
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Paperclip, Sparkles, Save, Plus, Undo2, BookPlus, Pencil, Dumbbell } from 'lucide-vue-next';
import { RouterLink } from 'vue-router';
import PageState from '../components/ui/PageState.vue';
import { useAuthStore } from '../domains/user';
import {
    lessonApi,
    type LessonCorrection,
    type LessonCorrectionInput,
    type LessonDetail,
    type LessonGrammarCandidate,
    type LessonItemCollection,
    type LessonLexemeCandidate,
    type LessonMessage,
} from '../domains/learning';
import ChatMessage from '../shared/ui/ChatMessage.vue';
import WordRow from '../shared/ui/WordRow.vue';
import GrammarCard from '../shared/ui/GrammarCard.vue';
import UiBadge from '../shared/ui/UiBadge.vue';
import UiButton from '../shared/ui/UiButton.vue';
import UiCard from '../shared/ui/UiCard.vue';
import UiInput from '../shared/ui/UiInput.vue';
import UiSectionHeader from '../shared/ui/UiSectionHeader.vue';
import UiEmptyState from '../shared/ui/UiEmptyState.vue';
import UiTabs from '../shared/ui/UiTabs.vue';
import UiSegmentedControl from '../shared/ui/UiSegmentedControl.vue';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const lessonId = computed(() => Number(route.params.id));

const lesson = ref<LessonDetail | null>(null);
const messages = ref<LessonMessage[]>([]);
const loading = ref(true);
const error = ref('');
const sending = ref(false);
const isWaiting = ref(false);
const chatError = ref('');
const failedMessage = ref<{ text: string; file: File | null } | null>(null);
const analyzing = ref(false);
const saving = ref(false);
const archiving = ref(false);
const tagsDraft = ref('');
const itemError = ref('');
const itemSaving = ref(false);
const undoItem = ref<{ collection: LessonItemCollection; id: number } | null>(null);
const undoMessage = ref('');
const editingLexemeId = ref<number | null>(null);
const editingGrammarId = ref<number | null>(null);
const editingCorrectionId = ref<number | null>(null);
const showLexemeForm = ref(false);
const showGrammarForm = ref(false);
const showCorrectionForm = ref(false);
type LessonLexemeDraft = { text: string; type: string; translation: string; level: string; example: string; example_translation: string };
type LessonGrammarDraft = { title: string; summary: string; body: string; example: string; example_translation: string };
const lexemeDraft = ref<LessonLexemeDraft>({ text: '', type: 'word', translation: '', level: '', example: '', example_translation: '' });
const grammarDraft = ref<LessonGrammarDraft>({ title: '', summary: '', body: '', example: '', example_translation: '' });
const correctionDraft = ref<LessonCorrectionInput>({ original_text: '', corrected_text: '', explanation: '' });

const inputText = ref('');
const attachment = ref<File | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const messagesEnd = ref<HTMLElement | null>(null);
const notesTextarea = ref<HTMLTextAreaElement | null>(null);

const activeTab = ref<'notes' | 'words' | 'grammar' | 'corrections' | 'chat'>('words');
const lessonWordFilter = ref<'all' | 'not-in-practice' | 'in-practice'>('all');

const lessonWordStatusSegments = computed(() => {
    const words = lesson.value?.lexemes ?? [];
    return [
        { value: 'all', label: 'All', count: words.length },
        { value: 'not-in-practice', label: 'Not in practice', count: words.filter((word) => !word.in_review).length },
        { value: 'in-practice', label: 'In practice', count: words.filter((word) => word.in_review).length },
    ];
});

const visibleLessonWords = computed(() => {
    const words = lesson.value?.lexemes ?? [];
    if (lessonWordFilter.value === 'not-in-practice') return words.filter((word) => !word.in_review);
    if (lessonWordFilter.value === 'in-practice') return words.filter((word) => word.in_review);
    return words;
});

const practiceWordIds = computed(() => {
    const ids = (lesson.value?.lexemes ?? []).flatMap((word) =>
        word.in_review && word.matched_lexeme_id !== null ? [word.matched_lexeme_id] : [],
    );
    return [...new Set(ids)];
});

const tabs = [
    { key: 'notes', label: 'Notes' },
    { key: 'words', label: 'Words' },
    { key: 'grammar', label: 'Grammar' },
    { key: 'corrections', label: 'Corrections' },
    { key: 'chat', label: 'Chat' },
];

const canSend = computed(() => (inputText.value.trim() !== '' || attachment.value !== null) && !sending.value);

function setLessonWordFilter(value: string) {
    if (value === 'all' || value === 'not-in-practice' || value === 'in-practice') lessonWordFilter.value = value;
}

async function loadLesson() {
    lesson.value = await lessonApi.get(lessonId.value);
    lesson.value.corrections ??= [];
    lesson.value.lesson_date = lesson.value.lesson_date?.slice(0, 10) ?? null;
    lesson.value.language = lesson.value.language || 'en';
    tagsDraft.value = (lesson.value.tags ?? []).join(', ');
}

function addLexeme() {
    editingLexemeId.value = null;
    showLexemeForm.value = true;
    lexemeDraft.value = { text: '', type: 'word', translation: '', level: '', example: '', example_translation: '' };
    itemError.value = '';
}

function editLexeme(item: LessonLexemeCandidate) {
    editingLexemeId.value = item.id;
    showLexemeForm.value = true;
    lexemeDraft.value = {
        text: item.text, type: item.type, translation: item.translation ?? '', level: item.level ?? '',
        example: item.example ?? '', example_translation: item.example_translation ?? '',
    };
    itemError.value = '';
}

function cancelLexemeEdit() {
    editingLexemeId.value = null;
    showLexemeForm.value = false;
    lexemeDraft.value = { text: '', type: 'word', translation: '', level: '', example: '', example_translation: '' };
}

async function saveLexeme() {
    if (!lesson.value) return;
    itemSaving.value = true;
    itemError.value = '';
    try {
        const item = editingLexemeId.value === null
            ? await lessonApi.createLexeme(lessonId.value, lexemeDraft.value)
            : await lessonApi.updateLexeme(lessonId.value, editingLexemeId.value, lexemeDraft.value);
        const index = lesson.value.lexemes.findIndex((candidate) => candidate.id === item.id);
        if (index === -1) lesson.value.lexemes.unshift(item);
        else lesson.value.lexemes[index] = item;
        cancelLexemeEdit();
    } catch {
        itemError.value = 'Failed to save this word. Try again.';
    } finally {
        itemSaving.value = false;
    }
}

function addGrammar() {
    editingGrammarId.value = null;
    showGrammarForm.value = true;
    grammarDraft.value = { title: '', summary: '', body: '', example: '', example_translation: '' };
    itemError.value = '';
}

function editGrammar(item: LessonGrammarCandidate) {
    editingGrammarId.value = item.id;
    showGrammarForm.value = true;
    grammarDraft.value = { title: item.title, summary: item.summary ?? '', body: item.body ?? '', example: item.example ?? '', example_translation: item.example_translation ?? '' };
    itemError.value = '';
}

function cancelGrammarEdit() {
    editingGrammarId.value = null;
    showGrammarForm.value = false;
    grammarDraft.value = { title: '', summary: '', body: '', example: '', example_translation: '' };
}

async function saveGrammar() {
    if (!lesson.value) return;
    itemSaving.value = true;
    itemError.value = '';
    try {
        const item = editingGrammarId.value === null
            ? await lessonApi.createGrammar(lessonId.value, grammarDraft.value)
            : await lessonApi.updateGrammar(lessonId.value, editingGrammarId.value, grammarDraft.value);
        const index = lesson.value.grammar.findIndex((candidate) => candidate.id === item.id);
        if (index === -1) lesson.value.grammar.unshift(item);
        else lesson.value.grammar[index] = item;
        cancelGrammarEdit();
    } catch {
        itemError.value = 'Failed to save this grammar point. Try again.';
    } finally {
        itemSaving.value = false;
    }
}

async function addGrammarToMyGrammar(item: LessonGrammarCandidate) {
    if (!lesson.value) return;
    itemSaving.value = true;
    itemError.value = '';
    try {
        const result = await lessonApi.addGrammarToMyGrammar(lessonId.value, item.id);
        const index = lesson.value.grammar.findIndex((candidate) => candidate.id === item.id);
        if (index !== -1) {
                lesson.value.grammar[index] = {
                    ...lesson.value.grammar[index],
                    matched_grammar_rule_id: result.matched_grammar_rule_id,
                    personal_grammar_rule_id: result.personal_grammar_rule_id,
                    in_my_grammar: true,
                    status: result.status,
            };
        }
    } catch {
        itemError.value = 'Failed to add this rule to My grammar. Try again.';
    } finally {
        itemSaving.value = false;
    }
}

async function addLexemeToMyWords(item: LessonLexemeCandidate) {
    if (!lesson.value) return;
    itemSaving.value = true;
    itemError.value = '';
    try {
        const result = await lessonApi.addLexemeToMyWords(lessonId.value, item.id);
        const index = lesson.value.lexemes.findIndex((candidate) => candidate.id === item.id);
        if (index !== -1) lesson.value.lexemes[index] = {
            ...lesson.value.lexemes[index],
            matched_lexeme_id: result.lexeme_id,
            in_my_words: result.in_my_words,
            in_review: result.in_review,
            status: result.status,
        };
    } catch {
        itemError.value = 'Failed to add this word to My words. Try again.';
    } finally {
        itemSaving.value = false;
    }
}

function addCorrection() {
    editingCorrectionId.value = null;
    showCorrectionForm.value = true;
    correctionDraft.value = { original_text: '', corrected_text: '', explanation: '' };
    itemError.value = '';
}

function editCorrection(item: LessonCorrection) {
    editingCorrectionId.value = item.id;
    showCorrectionForm.value = true;
    correctionDraft.value = { original_text: item.original_text, corrected_text: item.corrected_text, explanation: item.explanation ?? '' };
    itemError.value = '';
}

function cancelCorrectionEdit() {
    editingCorrectionId.value = null;
    showCorrectionForm.value = false;
    correctionDraft.value = { original_text: '', corrected_text: '', explanation: '' };
}

async function saveCorrection() {
    if (!lesson.value) return;
    itemSaving.value = true;
    itemError.value = '';
    try {
        const item = editingCorrectionId.value === null
            ? await lessonApi.createCorrection(lessonId.value, correctionDraft.value)
            : await lessonApi.updateCorrection(lessonId.value, editingCorrectionId.value, correctionDraft.value);
        const index = lesson.value.corrections.findIndex((candidate) => candidate.id === item.id);
        if (index === -1) lesson.value.corrections.unshift(item);
        else lesson.value.corrections[index] = item;
        cancelCorrectionEdit();
    } catch {
        itemError.value = 'Failed to save this correction. Try again.';
    } finally {
        itemSaving.value = false;
    }
}

async function deleteLessonItem(collection: LessonItemCollection, id: number) {
    if (!lesson.value) return;
    itemSaving.value = true;
    itemError.value = '';
    try {
        if (collection === 'lexemes') {
            await lessonApi.deleteLexeme(lessonId.value, id);
            lesson.value.lexemes = lesson.value.lexemes.filter((item) => item.id !== id);
        } else if (collection === 'grammar') {
            await lessonApi.deleteGrammar(lessonId.value, id);
            lesson.value.grammar = lesson.value.grammar.filter((item) => item.id !== id);
        } else {
            await lessonApi.deleteCorrection(lessonId.value, id);
            lesson.value.corrections = lesson.value.corrections.filter((item) => item.id !== id);
        }
        undoItem.value = { collection, id };
        undoMessage.value = 'Removed from this lesson.';
    } catch {
        itemError.value = 'Failed to remove this item. Try again.';
    } finally {
        itemSaving.value = false;
    }
}

async function restoreLessonItem() {
    if (!lesson.value || !undoItem.value) return;
    const deleted = undoItem.value;
    itemSaving.value = true;
    itemError.value = '';
    try {
        if (deleted.collection === 'lexemes') lesson.value.lexemes.unshift(await lessonApi.restoreLexeme(lessonId.value, deleted.id));
        else if (deleted.collection === 'grammar') lesson.value.grammar.unshift(await lessonApi.restoreGrammar(lessonId.value, deleted.id));
        else lesson.value.corrections.unshift(await lessonApi.restoreCorrection(lessonId.value, deleted.id));
        undoItem.value = null;
        undoMessage.value = '';
    } catch {
        itemError.value = 'Could not restore this item. Reload the lesson and try again.';
    } finally {
        itemSaving.value = false;
    }
}

async function loadMessages() {
    const data = await lessonApi.listMessages(lessonId.value);
    messages.value = data.messages;
    isWaiting.value = data.is_waiting;
    await scrollToBottom();
}

function schedulePoll() {
    stopPolling();
    if (!isWaiting.value) return;
    pollTimer = setTimeout(async () => {
        try {
            await loadMessages();
        } catch {
            chatError.value = 'Failed to refresh messages. Try again to check the reply.';
            return;
        }
        schedulePoll();
    }, 1500);
}

function stopPolling() {
    if (pollTimer) {
        clearTimeout(pollTimer);
        pollTimer = null;
    }
}

let pollTimer: ReturnType<typeof setTimeout> | null = null;

function onFileChange(e: Event) {
    const files = (e.target as HTMLInputElement).files;
    attachment.value = files && files.length > 0 ? files[0] : null;
}

async function refreshMessages() {
    if (sending.value) return;
    sending.value = true;
    chatError.value = '';
    try {
        await loadMessages();
        schedulePoll();
    } catch {
        chatError.value = 'Failed to refresh messages. Try again to check the reply.';
    } finally {
        sending.value = false;
    }
}

async function send(retry = false) {
    if (sending.value || (!retry && !canSend.value)) return;
    const text = retry ? failedMessage.value?.text ?? '' : inputText.value.trim();
    const file = retry ? failedMessage.value?.file ?? null : attachment.value;
    if (!text && !file) return;
    sending.value = true;
    chatError.value = '';
    failedMessage.value = null;
    let accepted = false;
    try {
        await lessonApi.sendMessage(lessonId.value, text, file);
        accepted = true;
        inputText.value = '';
        attachment.value = null;
        if (fileInput.value) fileInput.value.value = '';
        await loadMessages();
        schedulePoll();
    } catch (e: unknown) {
        if (accepted) {
            chatError.value = 'Message sent. Failed to refresh messages; try again to check the reply.';
        } else {
            failedMessage.value = { text, file };
            const err = e as { response?: { data?: { message?: string } } };
            chatError.value = err.response?.data?.message ?? 'Failed to send the message.';
        }
    } finally {
        sending.value = false;
    }
}

async function retryChat() {
    if (failedMessage.value) await send(true);
    else await refreshMessages();
}

async function analyzeLesson() {
    analyzing.value = true;
    error.value = '';
    try {
        await lessonApi.analyze(lessonId.value);
        await pollAnalysis();
    } catch (e: unknown) {
        const err = e as { response?: { status?: number; data?: { message?: string } } };
        error.value = err.response?.data?.message ?? 'Failed to analyze the lesson.';
        analyzing.value = false;
    }
}

async function pollAnalysis() {
    await loadLesson();
    const status = lesson.value?.analysis_status;
    if (status === 'pending' || status === 'running') {
        setTimeout(pollAnalysis, 1500);
        return;
    }
    analyzing.value = false;
}

async function scrollToBottom() {
    await nextTick();
    messagesEnd.value?.scrollIntoView({ behavior: 'smooth' });
}

function autoResizeTextarea(el: { style: { height: string }; scrollHeight: number } | null) {
    if (!el) return;
    el.style.height = 'auto';
    el.style.height = `${Math.min(el.scrollHeight, 400)}px`;
}

async function saveLesson() {
    if (!lesson.value) return;
    saving.value = true;
    error.value = '';
    try {
        const tags = tagsDraft.value.split(',').map((tag) => tag.trim()).filter(Boolean);
        await lessonApi.update(lessonId.value, {
            title: lesson.value.title,
            lesson_date: lesson.value.lesson_date,
            teacher: lesson.value.teacher,
            topic: lesson.value.topic,
            language: lesson.value.language || 'en',
            tags,
            notes: lesson.value.notes,
            homework: lesson.value.homework,
        });
        lesson.value.tags = tags;
    } catch (e: unknown) {
        const err = e as { response?: { data?: { message?: string } } };
        error.value = err.response?.data?.message ?? 'Failed to save lesson.';
    } finally {
        saving.value = false;
    }
}

async function toggleArchive() {
    if (!lesson.value) return;
    archiving.value = true;
    error.value = '';
    try {
        if (lesson.value.status === 'archived') {
            await lessonApi.restore(lessonId.value);
            lesson.value.status = 'active';
        } else {
            await lessonApi.destroy(lessonId.value);
            lesson.value.status = 'archived';
        }
    } catch (e: unknown) {
        const err = e as { response?: { data?: { message?: string } } };
        error.value = err.response?.data?.message ?? 'Failed to update lesson status.';
    } finally {
        archiving.value = false;
    }
}

onMounted(async () => {
    if (!authStore.isAuthenticated) {
        router.push({ name: 'login', query: { redirect: `/lessons/${lessonId.value}` } });
        return;
    }
    loading.value = true;
    try {
        await Promise.all([loadLesson(), loadMessages()]);
        schedulePoll();
        nextTick(() => autoResizeTextarea(notesTextarea.value));
    } catch (e: unknown) {
        const err = e as { response?: { status?: number; data?: { message?: string } } };
        error.value = err.response?.data?.message ?? 'Failed to load the lesson.';
    } finally {
        loading.value = false;
    }
});

onUnmounted(() => {
    stopPolling();
});
</script>

<template>
    <div class="space-y-6">
        <PageState :loading="loading" :error="error && !lesson ? error : ''">
            <template v-if="lesson">
                <!-- Header -->
                <UiCard>
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <UiSectionHeader
                            :title="lesson.title || 'Lesson'"
                            :subtitle="lesson.teacher ? `Teacher: ${lesson.teacher}` : (lesson.lesson_date ? `Date: ${new Date(lesson.lesson_date).toLocaleDateString()}` : 'Notes from this lesson')"
                        />
                        <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto">
                            <UiButton variant="secondary" :disabled="archiving" @click="toggleArchive">
                                {{ archiving ? 'Saving...' : lesson.status === 'archived' ? 'Restore lesson' : 'Archive lesson' }}
                            </UiButton>
                            <UiButton variant="secondary" :disabled="saving" @click="saveLesson">
                                <Save :size="16" class="mr-1" /> {{ saving ? 'Saving...' : 'Save' }}
                            </UiButton>
                            <UiButton variant="primary" :disabled="analyzing" @click="analyzeLesson">
                                <Sparkles :size="16" /> {{ analyzing ? 'Analyzing...' : 'Analyze lesson' }}
                            </UiButton>
                        </div>
                        </div>
                    <div class="mt-5 grid min-w-0 grid-cols-1 gap-4 border-t border-border pt-4 sm:grid-cols-2">
                        <label class="block min-w-0 space-y-2">
                            <span class="text-sm font-medium text-fg-secondary">Title</span>
                            <UiInput id="lesson-title" :model-value="lesson.title ?? ''" placeholder="Lesson title" @update:model-value="lesson.title = $event" />
                        </label>
                        <label class="block min-w-0 space-y-2">
                            <span class="text-sm font-medium text-fg-secondary">Date</span>
                            <UiInput id="lesson-date" :model-value="lesson.lesson_date ?? ''" type="date" @update:model-value="lesson.lesson_date = $event || null" />
                        </label>
                        <label class="block min-w-0 space-y-2">
                            <span class="text-sm font-medium text-fg-secondary">Teacher or group</span>
                            <UiInput id="lesson-teacher" :model-value="lesson.teacher ?? ''" placeholder="Teacher or group" @update:model-value="lesson.teacher = $event" />
                        </label>
                        <label class="block min-w-0 space-y-2">
                            <span class="text-sm font-medium text-fg-secondary">Topic</span>
                            <UiInput id="lesson-topic" :model-value="lesson.topic ?? ''" placeholder="Topic" @update:model-value="lesson.topic = $event" />
                        </label>
                        <label class="block min-w-0 space-y-2">
                            <span class="text-sm font-medium text-fg-secondary">Language code</span>
                            <UiInput id="lesson-language" :model-value="lesson.language" maxlength="8" placeholder="en" @update:model-value="lesson.language = $event" />
                        </label>
                        <label class="block min-w-0 space-y-2 sm:col-span-2">
                            <span class="text-sm font-medium text-fg-secondary">Tags</span>
                            <UiInput id="lesson-tags" v-model="tagsDraft" placeholder="Comma-separated tags" />
                        </label>
                    </div>
                </UiCard>

                <div v-if="error" class="rounded-spa-lg border border-warning-border bg-warning-bg p-4 text-sm text-warning-fg" role="alert">
                    {{ error }}
                </div>

                <!-- Tabs -->
                <UiTabs v-model="activeTab" :tabs="tabs" class="mb-4" />

                <div v-if="undoMessage" class="flex min-w-0 flex-wrap items-center justify-between gap-3 rounded-spa-lg border border-border bg-surface p-3 text-sm" role="status">
                    <span>{{ undoMessage }}</span>
                    <UiButton variant="secondary" size="touch" :disabled="itemSaving" @click="restoreLessonItem">
                        <Undo2 :size="16" /> Undo
                    </UiButton>
                </div>
                <div v-if="itemError" class="rounded-spa-lg border border-warning-border bg-warning-bg p-3 text-sm text-warning-fg" role="alert">
                    {{ itemError }}
                </div>

                <!-- Notes Tab -->
                <div v-if="activeTab === 'notes'" class="space-y-4">
                    <UiCard class="space-y-4">
                        <UiSectionHeader title="Lesson notes" subtitle="Markdown supported" />
                        <div class="space-y-3">
                            <label class="block">
                                <textarea
                                    ref="notesTextarea"
                                    v-model="lesson.notes"
                                    :style="{ paddingBottom: 'calc(env(safe-area-inset-bottom) + 16px)' }"
                                    class="w-full min-h-[180px] max-h-[500px] resize-none rounded-spa border border-border bg-surface p-4 text-base font-mono text-fg placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                    placeholder="Write your lesson notes here…"
                                    @input="autoResizeTextarea(notesTextarea)"
                                    @blur="saveLesson"
                                    @keydown.ctrl.enter.exact.prevent="saveLesson()"
                                    @keydown.meta.enter.exact.prevent="saveLesson()"
                                ></textarea>
                                <p class="mt-2 text-xs text-muted-foreground">
                                    Saves automatically on blur. Press Ctrl+Enter to save now.
                                </p>
                            </label>
                        </div>
                        <div v-if="lesson.homework !== null && lesson.homework !== undefined" class="space-y-2 border-t border-border pt-4">
                            <label class="block">
                                <span class="text-sm font-medium text-fg-secondary">Homework</span>
                                <textarea
                                    v-model="lesson.homework"
                                    class="w-full min-h-[80px] max-h-[300px] resize-none rounded-spa border border-border bg-surface p-4 text-base font-mono text-fg placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring mt-1"
                                    placeholder="Homework for next lesson…"
                                    @input="autoResizeTextarea($event.target as HTMLTextAreaElement)"
                                ></textarea>
                            </label>
                        </div>
                        <div v-if="lesson.tags && lesson.tags.length > 0" class="flex flex-wrap gap-2">
                            <UiBadge v-for="tag in lesson.tags" :key="tag" tone="primary">{{ tag }}</UiBadge>
                        </div>
                    </UiCard>
                </div>

                <!-- Words Tab -->
                <div v-if="activeTab === 'words'" class="space-y-4">
                    <UiCard class="space-y-3">
                        <div class="flex min-w-0 flex-wrap items-center justify-between gap-3">
                            <UiSectionHeader title="Words" :subtitle="`${lesson.lexemes.length} from this lesson`" />
                            <UiButton variant="primary" size="touch" @click="addLexeme"><Plus :size="16" /> Add word</UiButton>
                        </div>
                        <form v-if="showLexemeForm" class="grid min-w-0 gap-3 rounded-spa-lg border border-border bg-black/5 p-3" @submit.prevent="saveLexeme">
                            <p class="text-sm font-medium">{{ editingLexemeId === null ? 'Add a word or phrase' : 'Edit word or phrase' }}</p>
                            <div class="grid min-w-0 gap-3 sm:grid-cols-2">
                                <label class="min-w-0 space-y-1 text-sm">Word or phrase
                                    <UiInput v-model="lexemeDraft.text" required maxlength="255" placeholder="e.g. look after" />
                                </label>
                                <label class="min-w-0 space-y-1 text-sm">Type
                                    <select v-model="lexemeDraft.type" class="h-11 w-full min-w-0 rounded-md border border-border bg-surface px-3 text-base text-fg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                                        <option value="word">Word</option><option value="phrase">Phrase</option><option value="phrasal_verb">Phrasal verb</option><option value="idiom">Idiom</option><option value="collocation">Collocation</option>
                                    </select>
                                </label>
                                <label class="min-w-0 space-y-1 text-sm">Translation
                                    <UiInput v-model="lexemeDraft.translation" maxlength="5000" placeholder="Translation" />
                                </label>
                                <label class="min-w-0 space-y-1 text-sm">Level
                                    <UiInput v-model="lexemeDraft.level" maxlength="4" placeholder="A2" />
                                </label>
                                <label class="min-w-0 space-y-1 text-sm sm:col-span-2">Example
                                    <UiInput v-model="lexemeDraft.example" maxlength="10000" placeholder="Example sentence" />
                                </label>
                                <label class="min-w-0 space-y-1 text-sm sm:col-span-2">Example translation
                                    <UiInput v-model="lexemeDraft.example_translation" maxlength="10000" placeholder="Translation of the example" />
                                </label>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <UiButton type="submit" variant="primary" size="touch" :disabled="itemSaving">{{ itemSaving ? 'Saving…' : 'Save word' }}</UiButton>
                                <UiButton type="button" variant="secondary" size="touch" @click="cancelLexemeEdit">Cancel</UiButton>
                            </div>
                        </form>
                    </div>
                    <UiEmptyState v-if="lesson.lexemes.length === 0" class="mx-4 mt-3 sm:mx-0" title="Nothing yet" description="Add a word here, or analyze this lesson to find words." />
                    <UiEmptyState v-else-if="visibleLessonWords.length === 0" class="mx-4 mt-3 sm:mx-0" title="No words in this view" description="Try another status or add a word to this lesson." />
                    <div v-else class="mt-3 divide-y divide-border border-y border-border sm:overflow-hidden sm:rounded-lg sm:border">
                        <WordRow
                            v-for="w in visibleLessonWords"
                            :key="w.id"
                            :text="w.text"
                            :language="w.language"
                            :translation="w.translation"
                            :level="w.level"
                            level-in-details
                            :lexeme-id="w.matched_lexeme_id"
                            :example="w.example"
                            :examples="w.example ? [{ example: w.example, translation: w.example_translation, is_primary: true }] : []"
                            :status-label="w.in_review ? 'In practice' : w.in_my_words ? 'Saved to My words' : (w.status === 'matched' ? 'Dictionary match' : 'New from lesson')"
                            :status-tone="w.in_review ? 'success' : 'neutral'"
                        >
                            <span class="text-xs text-muted-foreground">{{ w.type.replaceAll('_', ' ') }}</span>
                            <template #row-actions>
                                <RouterLink v-if="w.in_review && w.matched_lexeme_id" :to="{ name: 'repetitions', query: { lexeme_ids: String(w.matched_lexeme_id), return_to: 'lessons' } }" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-md text-primary hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" :aria-label="`Practice ${w.text}`" title="Practice word"><Dumbbell :size="18" /></RouterLink>
                                <UiButton v-else variant="secondary" size="icon-touch" class="shrink-0" :disabled="itemSaving" :aria-label="w.in_my_words ? `Add ${w.text} to practice` : `Add ${w.text} to My words and practice`" :title="w.in_my_words ? 'Add to practice' : 'Add to My words and practice'" @click="addLexemeToMyWords(w)"><BookPlus :size="18" /></UiButton>
                            </template>
                            <template #actions>
                                <div class="flex flex-wrap gap-2">
                                    <UiButton variant="secondary" size="touch" :disabled="itemSaving" @click="editLexeme(w)">Edit</UiButton>
                                    <UiButton variant="danger" size="touch" :disabled="itemSaving" @click="deleteLessonItem('lexemes', w.id)">Remove</UiButton>
                                </div>
                            </template>
                        </WordRow>
                    </div>
                </section>

                <!-- Grammar Tab -->
                <div v-if="activeTab === 'grammar'" class="space-y-4">
                    <UiCard class="space-y-3">
                        <div class="flex min-w-0 flex-wrap items-center justify-between gap-3">
                            <UiSectionHeader title="Grammar" :subtitle="`${lesson.grammar.length} from this lesson`" />
                            <UiButton variant="primary" size="touch" @click="addGrammar"><Plus :size="16" /> Add grammar</UiButton>
                        </div>
                        <form v-if="showGrammarForm" class="grid min-w-0 gap-3 rounded-spa-lg border border-border bg-black/5 p-3" @submit.prevent="saveGrammar">
                            <p class="text-sm font-medium">{{ editingGrammarId === null ? 'Add a grammar point' : 'Edit grammar point' }}</p>
                            <label class="min-w-0 space-y-1 text-sm">Title
                                <UiInput v-model="grammarDraft.title" required maxlength="255" placeholder="e.g. Past habits with used to" />
                            </label>
                            <label class="min-w-0 space-y-1 text-sm">Summary
                                <textarea v-model="grammarDraft.summary" maxlength="10000" rows="3" class="w-full min-w-0 rounded-md border border-border bg-surface p-3 text-base text-fg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" placeholder="When and how to use this pattern" />
                            </label>
                            <label class="min-w-0 space-y-1 text-sm">Example
                                <UiInput v-model="grammarDraft.example" maxlength="10000" placeholder="Example sentence" />
                            </label>
                            <label class="min-w-0 space-y-1 text-sm">Example translation
                                <UiInput v-model="grammarDraft.example_translation" maxlength="10000" placeholder="Translation of the example" />
                            </label>
                            <label class="min-w-0 space-y-1 text-sm">Details
                                <textarea v-model="grammarDraft.body" maxlength="20000" rows="4" class="w-full min-w-0 rounded-md border border-border bg-surface p-3 text-base text-fg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" placeholder="Optional rule details" />
                            </label>
                            <div class="flex flex-wrap gap-2">
                                <UiButton type="submit" variant="primary" size="touch" :disabled="itemSaving">{{ itemSaving ? 'Saving…' : 'Save grammar' }}</UiButton>
                                <UiButton type="button" variant="secondary" size="touch" @click="cancelGrammarEdit">Cancel</UiButton>
                            </div>
                        </form>
                        <UiEmptyState v-if="lesson.grammar.length === 0" title="Nothing yet" description="Add a grammar point here, or analyze this lesson to find grammar." />
                        <div v-else class="space-y-2">
                            <GrammarCard
                                v-for="g in lesson.grammar"
                                :key="g.id"
                                :title="g.title"
                                :rule-id="g.personal_grammar_rule_id ?? g.matched_grammar_rule_id"
                                :summary="g.summary"
                                :body="g.body"
                                :example="g.example"
                                :example-translation="g.example_translation"
                                :status="g.in_my_grammar ? 'In My grammar' : (g.personal_grammar_rule_id || g.matched_grammar_rule_id ? 'Catalog match' : 'Not added')"
                            >
                                <template #actions>
                                    <UiButton v-if="!g.in_my_grammar" variant="primary" size="touch" :disabled="itemSaving" @click="addGrammarToMyGrammar(g)">Add to My grammar</UiButton>
                                    <RouterLink v-if="g.in_my_grammar" :to="{ name: 'grammar.practice', params: { id: g.personal_grammar_rule_id ?? g.matched_grammar_rule_id }, query: { from: `/lessons/${lesson.id}` } }" class="inline-flex min-h-11 items-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90">Practice grammar</RouterLink>
                                    <UiButton variant="secondary" size="touch" :disabled="itemSaving" @click="editGrammar(g)">Edit</UiButton>
                                    <UiButton variant="danger" size="touch" :disabled="itemSaving" @click="deleteLessonItem('grammar', g.id)">Remove</UiButton>
                                </template>
                            </GrammarCard>
                        </div>
                    </UiCard>
                </div>

                <div v-if="activeTab === 'corrections'" class="space-y-4">
                    <UiCard class="space-y-3">
                        <div class="flex min-w-0 flex-wrap items-center justify-between gap-3">
                            <UiSectionHeader title="Corrections" :subtitle="`${lesson.corrections.length} from this lesson`" />
                            <UiButton variant="primary" size="touch" @click="addCorrection"><Plus :size="16" /> Add correction</UiButton>
                        </div>
                        <form v-if="showCorrectionForm" class="grid min-w-0 gap-3 rounded-spa-lg border border-border bg-black/5 p-3" @submit.prevent="saveCorrection">
                            <p class="text-sm font-medium">{{ editingCorrectionId === null ? 'Add a correction' : 'Edit correction' }}</p>
                            <div class="grid min-w-0 gap-3 sm:grid-cols-2">
                                <label class="min-w-0 space-y-1 text-sm">What you said
                                    <textarea v-model="correctionDraft.original_text" required maxlength="10000" rows="3" class="w-full min-w-0 rounded-md border border-border bg-surface p-3 text-base text-fg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" placeholder="Original wording" />
                                </label>
                                <label class="min-w-0 space-y-1 text-sm">Correct form
                                    <textarea v-model="correctionDraft.corrected_text" required maxlength="10000" rows="3" class="w-full min-w-0 rounded-md border border-border bg-surface p-3 text-base text-fg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" placeholder="Corrected wording" />
                                </label>
                            </div>
                            <label class="min-w-0 space-y-1 text-sm">Why
                                <textarea v-model="correctionDraft.explanation" maxlength="10000" rows="3" class="w-full min-w-0 rounded-md border border-border bg-surface p-3 text-base text-fg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" placeholder="Optional explanation" />
                            </label>
                            <div class="flex flex-wrap gap-2">
                                <UiButton type="submit" variant="primary" size="touch" :disabled="itemSaving">{{ itemSaving ? 'Saving…' : 'Save correction' }}</UiButton>
                                <UiButton type="button" variant="secondary" size="touch" @click="cancelCorrectionEdit">Cancel</UiButton>
                            </div>
                        </form>
                        <UiEmptyState v-if="lesson.corrections.length === 0" title="Nothing yet" description="Add a teacher correction to keep the original and corrected wording together." />
                        <div v-else class="space-y-2">
                            <article v-for="correction in lesson.corrections" :key="correction.id" class="min-w-0 space-y-3 rounded-spa-lg border border-border bg-surface p-3">
                                <div class="grid min-w-0 gap-3 sm:grid-cols-2">
                                    <div class="min-w-0"><span class="text-xs font-medium text-muted-foreground">What you said</span><p class="break-words text-base text-fg">{{ correction.original_text }}</p></div>
                                    <div class="min-w-0"><span class="text-xs font-medium text-muted-foreground">Correct form</span><p class="break-words text-base font-semibold text-fg">{{ correction.corrected_text }}</p></div>
                                </div>
                                <p v-if="correction.explanation" class="break-words text-sm text-muted-foreground"><span class="font-medium text-fg-secondary">Why: </span>{{ correction.explanation }}</p>
                                <div class="flex flex-wrap gap-2">
                                    <UiButton variant="secondary" size="touch" :disabled="itemSaving" @click="editCorrection(correction)">Edit</UiButton>
                                    <UiButton variant="danger" size="touch" :disabled="itemSaving" @click="deleteLessonItem('corrections', correction.id)">Remove</UiButton>
                                </div>
                            </article>
                        </div>
                    </UiCard>
                </div>

                <!-- Chat Tab (moved from main screen) -->
                <div v-if="activeTab === 'chat'" class="space-y-4">
                    <UiCard class="overflow-hidden p-0">
                        <div class="flex h-[min(60vh,640px)] flex-col">
                            <div class="flex-1 overflow-y-auto p-4 space-y-3 bg-black/10">
                                <template v-if="messages.length === 0">
                                    <div class="rounded-spa-lg border border-dashed border-border-strong p-5 text-sm text-muted-foreground">
                                        Write what you covered in the lesson or attach a file with notes, then tap
                                        “Analyze lesson” to pull out the words and grammar.
                                    </div>
                                </template>
                                <ChatMessage
                                    v-for="msg in messages"
                                    :key="msg.id"
                                    :role="msg.role"
                                    :content="msg.content"
                                    :attachments="msg.attachment_name ? [{ name: msg.attachment_name, status: 'Attached' }] : []"
                                />
                                <ChatMessage v-if="isWaiting" role="assistant" loading loading-label="Typing..." />
                                <ChatMessage v-if="chatError" role="assistant" :error="chatError" retryable :loading="sending" @retry="retryChat" />
                                <div ref="messagesEnd" />
                            </div>

                            <div class="border-t border-border bg-black/20 p-3 space-y-2">
                                <div v-if="attachment" class="flex items-center gap-2 text-xs text-muted-foreground">
                                    <Paperclip :size="12" /> {{ attachment.name }}
                                </div>
                                <div class="flex gap-2">
                                    <input ref="fileInput" type="file" accept="application/pdf" class="hidden" @change="onFileChange" />
                                    <UiButton variant="secondary" :disabled="sending" @click="fileInput?.click()">
                                        <Paperclip :size="16" />
                                    </UiButton>
                                    <UiInput
                                        v-model="inputText"
                                        type="text"
                                        placeholder="What did you learn today?"
                                        :disabled="sending"
                                        @keydown.enter.prevent="send()"
                                    />
                                    <UiButton variant="primary" :disabled="!canSend" @click="send()">Send</UiButton>
                                </div>
                            </div>
                        </div>
                    </UiCard>
                </div>
            </template>
        </PageState>
    </div>
</template>
