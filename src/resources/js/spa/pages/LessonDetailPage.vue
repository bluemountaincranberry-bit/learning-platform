<script setup lang="ts">
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ArrowLeft, Eraser, MoreHorizontal, Paperclip, Settings2, Sparkles, Save, Plus, Undo2, Dumbbell, Trash2, Minus, Check } from 'lucide-vue-next';
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
import { useLessonWordActions } from '../composables/useLessonWordActions';
import ChatMessage from '../shared/ui/ChatMessage.vue';
import WordListItem from '../shared/ui/WordListItem.vue';
import GrammarCard from '../shared/ui/GrammarCard.vue';
import UiBadge from '../shared/ui/UiBadge.vue';
import UiButton from '../shared/ui/UiButton.vue';
import UiCard from '../shared/ui/UiCard.vue';
import UiDialog from '../shared/ui/UiDialog.vue';
import UiInput from '../shared/ui/UiInput.vue';
import ChatComposerInput from '../shared/ui/ChatComposerInput.vue';
import UiSectionHeader from '../shared/ui/UiSectionHeader.vue';
import UiEmptyState from '../shared/ui/UiEmptyState.vue';
import UiTabs from '../shared/ui/UiTabs.vue';
import UiSegmentedControl from '../shared/ui/UiSegmentedControl.vue';
import VoiceDictationControl from '../shared/ui/VoiceDictationControl.vue';
import type { SpeechLanguage, SpeechProvider } from '../domains/learning';
import type { LexemeWithLearned } from '../types';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const lessonId = computed(() => Number(route.params.id));
const isLessonChatRoute = computed(() => route.name === 'lesson.chat');
let previousBodyOverflow = '';
let lessonChatLocksBody = false;

const lesson = ref<LessonDetail | null>(null);
const {
    lexemes: lessonLexemes,
    itemSaving,
    itemError,
    bulkPending: lessonBulkPending,
    bulkMessage: lessonBulkMessage,
    lessonWordById,
    runAction: runLessonWordAction,
    bulkAction: bulkLessonWordAction,
} = useLessonWordActions(lesson, lessonId);
const messages = ref<LessonMessage[]>([]);
const loading = ref(true);
const error = ref('');
const sending = ref(false);
const isWaiting = ref(false);
const chatError = ref('');
const failedMessage = ref<{ text: string; file: File | null; audio: Blob | null; provider: SpeechProvider; language: SpeechLanguage; keepForever: boolean } | null>(null);
const analyzing = ref(false);
const saving = ref(false);
const headerEditing = ref(false);
const actionMenu = ref<HTMLDetailsElement | null>(null);
const archiving = ref(false);
const tagsDraft = ref('');
const undoItem = ref<{ collection: LessonItemCollection; id: number } | null>(null);
const undoMessage = ref('');
const confirmingLessonLexemeIds = ref<number[]>([]);
const lessonActionNotice = ref('');
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
const voiceAudio = ref<Blob | null>(null);
const voiceProvider = ref<SpeechProvider>('local_whisper');
const voiceLanguage = ref<SpeechLanguage>('en');
const keepVoiceForever = ref(false);
const voiceControl = ref<InstanceType<typeof VoiceDictationControl> | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const messagesEnd = ref<HTMLElement | null>(null);
const notesTextarea = ref<HTMLTextAreaElement | null>(null);

type LessonTab = 'notes' | 'words' | 'grammar' | 'corrections' | 'chat';
const lessonTabs: LessonTab[] = ['notes', 'words', 'grammar', 'corrections'];
const activeTab = ref<LessonTab>(isLessonChatRoute.value ? 'chat' : lessonTabs.includes(route.query.tab as LessonTab) ? route.query.tab as LessonTab : 'words');
const lessonWordFilter = ref<'all' | 'not-in-practice' | 'in-practice'>('all');
const selectedLessonWordIds = ref<Set<number>>(new Set());
const confirmingLessonKnown = ref(false);

const lessonWordStatusSegments = computed(() => {
    const words = lesson.value?.lexemes ?? [];
    return [
        { value: 'all', label: 'All', count: words.length },
        { value: 'not-in-practice', label: 'Not in practice', count: words.filter((word) => !word.in_review).length },
        { value: 'in-practice', label: 'In practice', count: words.filter((word) => word.in_review).length },
    ];
});

const confirmingLessonLexemes = computed(() => lesson.value?.lexemes.filter((word) => confirmingLessonLexemeIds.value.includes(word.id)) ?? []);

const visibleLessonWords = computed(() => {
    const words = lesson.value?.lexemes ?? [];
    if (lessonWordFilter.value === 'not-in-practice') return words.filter((word) => !word.in_review);
    if (lessonWordFilter.value === 'in-practice') return words.filter((word) => word.in_review);
    return words;
});

const visibleLessonLexemes = computed(() => {
    const visibleIds = new Set(visibleLessonWords.value.map((word) => word.id));
    return lessonLexemes.value.filter((word) => visibleIds.has(word.id));
});

const allVisibleLessonWordsSelected = computed(() => visibleLessonWords.value.length > 0
    && visibleLessonWords.value.every((word) => selectedLessonWordIds.value.has(word.id)));

const selectedLessonWords = computed(() => visibleLessonWords.value.filter((word) => selectedLessonWordIds.value.has(word.id)));
const selectedNotInPracticeIds = computed(() => selectedLessonWords.value.filter((word) => !word.in_review).map((word) => word.id));
const selectedInPracticeIds = computed(() => selectedLessonWords.value.filter((word) => word.in_review).map((word) => word.id));
const selectedUnknownIds = computed(() => selectedLessonWords.value.filter((word) => !word.learned).map((word) => word.id));

const practiceWordIds = computed(() => {
    const ids = (lesson.value?.lexemes ?? []).flatMap((word) =>
        word.in_review && word.matched_lexeme_id !== null ? [word.matched_lexeme_id] : [],
    );
    return [...new Set(ids)];
});

const tabs = [
    { key: 'chat', label: 'Chat' },
    { key: 'notes', label: 'Notes' },
    { key: 'words', label: 'Words' },
    { key: 'grammar', label: 'Grammar' },
    { key: 'corrections', label: 'Corrections' },
];

async function selectLessonTab(value: string) {
    if (value === 'chat') {
        await router.push({ name: 'lesson.chat', params: { id: lessonId.value } });
        return;
    }
    if (!lessonTabs.includes(value as LessonTab)) return;
    const tab = value as LessonTab;
    activeTab.value = tab;
    if (isLessonChatRoute.value) {
        await router.push({ name: 'lesson.details', params: { id: lessonId.value }, query: tab === 'words' ? {} : { tab } });
        return;
    }
    await router.replace({ name: 'lesson.details', params: { id: lessonId.value }, query: tab === 'words' ? {} : { tab } });
}

const canSend = computed(() => (inputText.value.trim() !== '' || attachment.value !== null || voiceAudio.value !== null) && !sending.value);

function onVoiceReady(text: string, audio: Blob, provider: SpeechProvider, language: SpeechLanguage, keepForever: boolean) {
    inputText.value = inputText.value.trim() ? `${inputText.value.trim()} ${text}` : text;
    voiceAudio.value = audio;
    voiceProvider.value = provider;
    voiceLanguage.value = language;
    keepVoiceForever.value = keepForever;
}

async function toggleVoicePin(message: LessonMessage) {
    if (!message.voice_audio_url) return;
    const result = await lessonApi.pinVoiceRecording(message.id, !message.voice_audio_pinned);
    message.voice_audio_pinned = result.pinned;
    message.voice_audio_expires_at = result.expires_at;
}

function setLessonWordFilter(value: string) {
    if (value === 'all' || value === 'not-in-practice' || value === 'in-practice') {
        if (lessonWordFilter.value !== value) clearLessonWordSelection();
        lessonWordFilter.value = value;
    }
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

function toggleLessonWordSelection(word: LexemeWithLearned) {
    const next = new Set(selectedLessonWordIds.value);
    if (next.has(word.id)) next.delete(word.id);
    else next.add(word.id);
    selectedLessonWordIds.value = next;
    confirmingLessonKnown.value = false;
}

function toggleAllVisibleLessonWords() {
    const next = new Set(selectedLessonWordIds.value);
    if (allVisibleLessonWordsSelected.value) visibleLessonWords.value.forEach((word) => next.delete(word.id));
    else visibleLessonWords.value.forEach((word) => next.add(word.id));
    selectedLessonWordIds.value = next;
    confirmingLessonKnown.value = false;
}

function clearLessonWordSelection() {
    selectedLessonWordIds.value = new Set();
    confirmingLessonKnown.value = false;
}

async function bulkStartLessonWords(ids: number[]) {
    reconcileLessonBulkSelection(await bulkLessonWordAction(ids, 'start'));
}

async function bulkStopLessonWords(ids: number[]) {
    reconcileLessonBulkSelection(await bulkLessonWordAction(ids, 'stop'));
}

async function bulkMarkLessonWordsKnown(ids: number[]) {
    reconcileLessonBulkSelection(await bulkLessonWordAction(ids, 'known'));
}

function reconcileLessonBulkSelection(result: { results: { id: number; ok: boolean }[] }) {
    selectedLessonWordIds.value = new Set(result.results.filter((item) => !item.ok).map((item) => item.id));
    confirmingLessonKnown.value = false;
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

async function deleteLessonItem(collection: LessonItemCollection, id: number): Promise<boolean> {
    if (!lesson.value) return false;
    itemSaving.value = true;
    itemError.value = '';
    lessonActionNotice.value = '';
    try {
        if (collection === 'lexemes') {
            await lessonApi.permanentlyDeleteLexeme(lessonId.value, id);
            lesson.value.lexemes = lesson.value.lexemes.filter((item) => item.id !== id);
            const nextSelection = new Set(selectedLessonWordIds.value);
            nextSelection.delete(id);
            selectedLessonWordIds.value = nextSelection;
            undoItem.value = null;
            undoMessage.value = '';
            lessonActionNotice.value = 'Word permanently deleted from this lesson.';
        } else if (collection === 'grammar') {
            await lessonApi.deleteGrammar(lessonId.value, id);
            lesson.value.grammar = lesson.value.grammar.filter((item) => item.id !== id);
        } else {
            await lessonApi.deleteCorrection(lessonId.value, id);
            lesson.value.corrections = lesson.value.corrections.filter((item) => item.id !== id);
        }
        if (collection !== 'lexemes') {
            undoItem.value = { collection, id };
            undoMessage.value = 'Removed from this lesson.';
        }
        return true;
    } catch {
        itemError.value = 'Failed to remove this item. Try again.';
        return false;
    } finally {
        itemSaving.value = false;
    }
}

async function confirmPermanentLessonLexemeDelete() {
    if (!lesson.value || confirmingLessonLexemeIds.value.length === 0) return;
    itemSaving.value = true;
    itemError.value = '';
    const ids = [...confirmingLessonLexemeIds.value];
    try {
        const deletedIds = await lessonApi.permanentlyDeleteLexemes(lessonId.value, ids);
        const deleted = new Set(deletedIds);
        lesson.value.lexemes = lesson.value.lexemes.filter((word) => !deleted.has(word.id));
        selectedLessonWordIds.value = new Set([...selectedLessonWordIds.value].filter((id) => !deleted.has(id)));
        confirmingLessonLexemeIds.value = [];
        lessonActionNotice.value = `${deletedIds.length} ${deletedIds.length === 1 ? 'word' : 'words'} permanently deleted from this lesson.`;
        clearLessonWordSelection();
    } catch {
        itemError.value = 'Failed to permanently delete the selected words. Try again.';
    } finally {
        itemSaving.value = false;
    }
}

function requestPermanentLessonLexemeDelete(id: number) {
    itemError.value = '';
    confirmingLessonLexemeIds.value = [id];
}

function requestPermanentSelectedLessonLexemesDelete() {
    itemError.value = '';
    confirmingLessonLexemeIds.value = selectedLessonWords.value.map((word) => word.id);
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
    const previousFailure = retry ? failedMessage.value : null;
    const text = retry ? failedMessage.value?.text ?? '' : inputText.value.trim();
    const file = retry ? failedMessage.value?.file ?? null : attachment.value;
    const audio = previousFailure?.audio ?? voiceAudio.value;
    const selectedProvider = previousFailure?.provider ?? voiceProvider.value;
    const selectedLanguage = previousFailure?.language ?? voiceLanguage.value;
    const keepForever = previousFailure?.keepForever ?? keepVoiceForever.value;
    if (!text && !file && !audio) return;
    sending.value = true;
    chatError.value = '';
    failedMessage.value = null;
    let accepted = false;
    try {
        await lessonApi.sendMessage(lessonId.value, text, file, audio ? { audio, provider: selectedProvider, language: selectedLanguage, keepForever } : undefined);
        accepted = true;
        inputText.value = '';
        attachment.value = null;
        voiceAudio.value = null;
        voiceControl.value?.clearRecording();
        if (fileInput.value) fileInput.value.value = '';
        await loadMessages();
        schedulePoll();
    } catch (e: unknown) {
        if (accepted) {
            chatError.value = 'Message sent. Failed to refresh messages; try again to check the reply.';
        } else {
            failedMessage.value = { text, file, audio, provider: selectedProvider, language: selectedLanguage, keepForever };
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

function clearChatDraft(): void {
    if (sending.value) return;
    if (failedMessage.value) chatError.value = '';
    inputText.value = '';
    attachment.value = null;
    voiceAudio.value = null;
    failedMessage.value = null;
    if (fileInput.value) fileInput.value.value = '';
    voiceControl.value?.clearRecording();
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

async function saveLesson(closeDetails = false) {
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
        if (closeDetails) headerEditing.value = false;
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
    if (isLessonChatRoute.value) {
        previousBodyOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        lessonChatLocksBody = true;
    }
    if (!authStore.isAuthenticated) {
        router.push({ name: 'login', query: { redirect: route.fullPath } });
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
    if (lessonChatLocksBody) document.body.style.overflow = previousBodyOverflow;
});
</script>

<template>
    <div :class="isLessonChatRoute ? 'fixed inset-0 z-40 flex h-[100dvh] flex-col overflow-hidden bg-background' : 'space-y-6'">
        <PageState :class="isLessonChatRoute ? 'flex min-h-0 flex-1 flex-col' : ''" :loading="loading" :error="error && !lesson ? error : ''">
            <template v-if="lesson">
                <template v-if="isLessonChatRoute">
                    <header class="flex shrink-0 items-center gap-3 border-b border-border px-3 py-2.5 pt-[max(env(safe-area-inset-top),0.625rem)] sm:px-6 sm:py-3">
                        <RouterLink
                            :to="{ name: 'lesson.details', params: { id: lesson.id } }"
                            class="inline-flex min-h-11 shrink-0 items-center gap-1 rounded-md px-2 text-sm font-medium text-fg-secondary hover:bg-surface-alt hover:text-fg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <ArrowLeft :size="18" aria-hidden="true" />
                            <span>Lesson</span>
                        </RouterLink>
                        <div class="h-7 w-px bg-border" aria-hidden="true"></div>
                        <div class="min-w-0 flex-1">
                            <h1 class="text-base font-semibold leading-5 text-fg">Chat</h1>
                            <p class="truncate text-xs text-muted-foreground">{{ lesson.title || 'Lesson' }}</p>
                        </div>
                        <div id="lesson-chat-settings" class="relative flex shrink-0 items-center">
                            <UiButton variant="ghost" size="icon-touch" aria-label="Voice settings" title="Voice settings" aria-haspopup="dialog" :aria-expanded="voiceControl?.settingsOpen ?? false" @click="voiceControl?.toggleSettings()">
                                <Settings2 :size="18" aria-hidden="true" />
                            </UiButton>
                        </div>
                    </header>
                    <div v-if="chatError" class="mx-auto mt-3 w-full max-w-3xl px-3 sm:px-6" role="alert">
                        <ChatMessage role="assistant" :error="chatError" retryable :loading="sending" @retry="retryChat" />
                    </div>
                    <section class="flex min-h-0 flex-1 flex-col" aria-label="Lesson conversation">
                        <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 py-4 sm:px-6 sm:py-6">
                            <div class="mx-auto flex w-full max-w-3xl flex-col gap-4">
                                <div v-if="messages.length === 0" class="rounded-spa-lg border border-dashed border-border-strong p-4 text-sm leading-6 text-muted-foreground sm:p-5">
                                    Ask a question about this lesson, its notes, or the words and grammar you are learning.
                                </div>
                                <template v-for="msg in messages" :key="msg.id">
                                    <ChatMessage
                                        :role="msg.role"
                                        :content="msg.content"
                                        :attachments="msg.attachment_name ? [{ name: msg.attachment_name, status: 'Attached' }] : []"
                                    />
                                    <div v-if="msg.voice_audio_url" class="flex flex-wrap items-center justify-end gap-2">
                                        <audio :src="msg.voice_audio_url" controls class="h-9 max-w-full" aria-label="Voice message recording" />
                                        <UiButton variant="ghost" size="sm" @click="toggleVoicePin(msg)">
                                            {{ msg.voice_audio_pinned ? 'Saved forever' : 'Keep forever' }}
                                        </UiButton>
                                        <span v-if="!msg.voice_audio_pinned && msg.voice_audio_expires_at" class="text-xs text-muted-foreground">Expires {{ new Date(msg.voice_audio_expires_at).toLocaleDateString() }}</span>
                                    </div>
                                </template>
                                <ChatMessage v-if="isWaiting" role="assistant" loading loading-label="Typing..." />
                                <div ref="messagesEnd" />
                            </div>
                        </div>
                        <form class="shrink-0 border-t border-border bg-surface px-3 pt-3 pb-[max(env(safe-area-inset-bottom),0.75rem)] sm:px-6 sm:py-4" @submit.prevent="send()">
                            <div class="mx-auto w-full max-w-3xl space-y-2">
                                <div v-if="attachment" class="flex items-center gap-2 text-xs text-muted-foreground">
                                    <Paperclip :size="12" /> {{ attachment.name }}
                                </div>
                                <ChatComposerInput
                                    v-model="inputText"
                                    class="w-full"
                                    placeholder="Message about this lesson..."
                                    aria-label="Message about this lesson"
                                    :disabled="sending"
                                    @submit="send()"
                                />
                                <div class="flex shrink-0 items-center justify-between gap-2">
                                    <div class="flex items-center gap-1">
                                        <input ref="fileInput" type="file" accept="application/pdf" class="hidden" @change="onFileChange" />
                                        <UiButton type="button" variant="secondary" size="icon-touch" :disabled="sending" aria-label="Attach a PDF" @click="fileInput?.click()">
                                            <Paperclip :size="16" aria-hidden="true" />
                                        </UiButton>
                                        <UiButton type="button" variant="ghost" size="touch" :disabled="sending || (!inputText.trim() && !attachment && !voiceAudio && !failedMessage)" @click="clearChatDraft()">
                                            <Eraser :size="16" aria-hidden="true" />
                                            Clear all
                                        </UiButton>
                                    </div>
                                    <div class="ml-auto flex items-center gap-2">
                                        <VoiceDictationControl ref="voiceControl" settings-target="#lesson-chat-settings" @ready="onVoiceReady" @cleared="voiceAudio = null" />
                                        <UiButton type="submit" variant="primary" size="touch" :disabled="!canSend">Send</UiButton>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </section>
                </template>
                <template v-else>
                <!-- Header -->
                <UiCard class="!p-3 sm:!p-5">
                    <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="flex min-w-0 flex-1 items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h1 class="break-words text-xl font-semibold leading-tight tracking-tight text-fg sm:text-3xl">{{ lesson.title || 'Lesson' }}</h1>
                                <p v-if="lesson.topic" class="mt-1 line-clamp-1 break-words text-sm text-fg-secondary">{{ lesson.topic }}</p>
                                <div class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
                                    <span v-if="lesson.lesson_date">{{ new Date(`${lesson.lesson_date}T00:00:00`).toLocaleDateString() }}</span>
                                    <span v-if="lesson.lesson_date && lesson.teacher" aria-hidden="true">·</span>
                                    <span v-if="lesson.teacher">{{ lesson.teacher }}</span>
                                    <span v-if="lesson.language" class="uppercase">{{ lesson.language }}</span>
                                    <UiBadge v-if="lesson.status === 'archived'" tone="warning">Archived</UiBadge>
                                </div>
                            </div>
                            <details ref="actionMenu" class="relative shrink-0">
                                <summary class="flex h-11 w-11 cursor-pointer list-none items-center justify-center rounded-md border border-border text-fg-secondary hover:bg-surface-alt focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" aria-label="More lesson actions" title="More actions">
                                    <MoreHorizontal :size="19" aria-hidden="true" />
                                </summary>
                                <div class="absolute right-0 top-12 z-30 min-w-48 rounded-spa-lg border border-border bg-card p-1 shadow-lg">
                                    <button type="button" class="w-full rounded-md px-3 py-2.5 text-left text-sm text-fg hover:bg-surface-alt" @click="headerEditing = !headerEditing; actionMenu?.removeAttribute('open')">
                                        {{ headerEditing ? 'Close details' : 'Edit details' }}
                                    </button>
                                    <button type="button" class="w-full rounded-md px-3 py-2.5 text-left text-sm text-fg hover:bg-surface-alt disabled:opacity-50" :disabled="archiving" @click="toggleArchive(); actionMenu?.removeAttribute('open')">
                                        {{ archiving ? 'Saving...' : lesson.status === 'archived' ? 'Restore lesson' : 'Archive lesson' }}
                                    </button>
                                </div>
                            </details>
                        </div>
                        <div class="flex w-full flex-col gap-1 sm:w-auto sm:min-w-52">
                            <UiButton variant="primary" size="touch" class="w-full" :disabled="analyzing" @click="analyzeLesson">
                                <Sparkles :size="16" aria-hidden="true" /> {{ analyzing ? 'Analyzing...' : 'Analyze lesson' }}
                            </UiButton>
                            <p class="px-1 text-xs text-muted-foreground sm:text-right">Find words and grammar in your notes</p>
                        </div>
                    </div>
                    <div v-if="headerEditing" class="grid min-w-0 grid-cols-1 gap-4 border-t border-border pt-4 sm:grid-cols-2">
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
                        <div class="flex flex-wrap gap-2 sm:col-span-2">
                            <UiButton variant="primary" size="touch" :disabled="saving" @click="saveLesson(true)"><Save :size="16" /> {{ saving ? 'Saving...' : 'Save details' }}</UiButton>
                            <UiButton variant="secondary" size="touch" @click="headerEditing = false">Close details</UiButton>
                        </div>
                    </div>
                </UiCard>

                <div v-if="error" class="rounded-spa-lg border border-warning-border bg-warning-bg p-4 text-sm text-warning-fg" role="alert">
                    {{ error }}
                </div>

                <!-- Tabs -->
                <UiTabs :model-value="activeTab" :tabs="tabs" class="mb-4" @update:model-value="selectLessonTab" />

                <div v-if="undoMessage" class="flex min-w-0 flex-wrap items-center justify-between gap-3 rounded-spa-lg border border-border bg-surface p-3 text-sm" role="status">
                    <span>{{ undoMessage }}</span>
                    <UiButton variant="secondary" size="touch" :disabled="itemSaving" @click="restoreLessonItem">
                        <Undo2 :size="16" /> Undo
                    </UiButton>
                </div>
                <div v-if="itemError" class="rounded-spa-lg border border-warning-border bg-warning-bg p-3 text-sm text-warning-fg" role="alert">
                    {{ itemError }}
                </div>
                <div v-if="lessonActionNotice" class="rounded-spa-lg border border-border bg-surface p-3 text-sm text-fg-secondary" role="status">
                    {{ lessonActionNotice }}
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
                                    @blur="() => saveLesson()"
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
                <section v-if="activeTab === 'words'" class="-mx-4 min-w-0 sm:mx-0 sm:rounded-xl sm:border sm:border-border sm:bg-card sm:p-5">
                    <div class="mx-4 space-y-3 sm:mx-0">
                        <div class="flex min-w-0 flex-wrap items-center justify-between gap-3">
                            <UiSectionHeader title="Words" :subtitle="`${lesson.lexemes.length} from this lesson`" />
                            <div class="flex flex-wrap items-center gap-2">
                                <RouterLink
                                    v-if="practiceWordIds.length > 0"
                                    :to="{ name: 'repetitions', query: { lesson_id: String(lesson.id), return_to: 'lessons' } }"
                                    class="inline-flex min-h-11 items-center gap-2 rounded-md border border-border bg-card px-3 text-sm font-medium text-fg transition-colors hover:border-primary hover:text-primary"
                                >
                                    <Dumbbell :size="16" aria-hidden="true" /> Practice {{ practiceWordIds.length }}
                                </RouterLink>
                                <UiButton variant="primary" size="touch" @click="addLexeme"><Plus :size="16" /> Add word</UiButton>
                            </div>
                        </div>
                        <UiSegmentedControl :model-value="lessonWordFilter" :segments="lessonWordStatusSegments" :ariaLabel="'Lesson word status'" @update:model-value="setLessonWordFilter" />
                        <div class="flex min-h-11 items-center justify-between gap-3">
                            <label v-if="visibleLessonWords.length > 0" class="flex min-h-11 cursor-pointer items-center gap-2 text-sm text-muted-foreground">
                                <input type="checkbox" class="h-5 w-5 rounded border-border accent-primary" :checked="allVisibleLessonWordsSelected" aria-label="Select all words in this view" @change="toggleAllVisibleLessonWords" />
                                Select all {{ visibleLessonWords.length }}
                            </label>
                            <p v-if="lessonBulkMessage" class="text-sm text-muted-foreground" role="status">{{ lessonBulkMessage }}</p>
                        </div>
                        <form v-if="showLexemeForm" class="grid min-w-0 gap-3 rounded-spa-lg border border-border bg-surface-alt/45 p-3" @submit.prevent="saveLexeme">
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
                        <WordListItem
                            v-for="w in visibleLessonLexemes"
                            :key="w.id"
                            :lexeme="w"
                            :language="lesson.language"
                            :selectable="true"
                            :selected="selectedLessonWordIds.has(w.id)"
                            :marking="itemSaving"
                            :starting-review="itemSaving"
                            @toggle-select="toggleLessonWordSelection"
                            @start-review="(word) => { const candidate = lessonWordById(word.id); if (candidate) void runLessonWordAction(candidate, 'start'); }"
                            @stop-review="(word) => { const candidate = lessonWordById(word.id); if (candidate) void runLessonWordAction(candidate, 'stop'); }"
                            @mark-learned="(word) => { const candidate = lessonWordById(word.id); if (candidate) void runLessonWordAction(candidate, 'known'); }"
                            @unmark-learned="(word) => { const candidate = lessonWordById(word.id); if (candidate) void runLessonWordAction(candidate, 'unknown'); }"
                        >
                            <template #actions>
                                <div class="flex flex-wrap gap-2">
                                    <UiButton variant="secondary" size="touch" :disabled="itemSaving" @click="lessonWordById(w.id) && editLexeme(lessonWordById(w.id)!)">Edit</UiButton>
                                    <UiButton variant="danger" size="touch" :disabled="itemSaving" @click="requestPermanentLessonLexemeDelete(w.id)"><Trash2 :size="16" /> Delete permanently</UiButton>
                                </div>
                            </template>
                        </WordListItem>
                    </div>
                    <div v-if="selectedLessonWordIds.size > 0" class="sticky bottom-[var(--spa-mobile-nav-height)] z-20 mt-2 space-y-2 border-y border-border bg-card/95 p-3 shadow-[0_-8px_28px_rgba(15,23,42,0.08)] backdrop-blur sm:rounded-xl sm:border lg:bottom-4">
                        <div class="flex min-h-11 items-center gap-2 text-sm">
                            <label class="flex min-h-11 cursor-pointer items-center gap-2 text-muted-foreground">
                                <input type="checkbox" class="h-5 w-5 rounded border-border accent-primary" :checked="allVisibleLessonWordsSelected" aria-label="Select all words in this view" @change="toggleAllVisibleLessonWords" />
                                Select all {{ visibleLessonWords.length }}
                            </label>
                            <span class="ml-auto font-medium text-fg">{{ selectedLessonWordIds.size }} selected</span>
                            <UiButton variant="ghost" size="icon-touch" aria-label="Clear selection" @click="clearLessonWordSelection">×</UiButton>
                        </div>
                        <div v-if="confirmingLessonKnown" class="flex flex-wrap items-center gap-2 rounded-md bg-warning-bg p-2">
                            <span class="min-w-0 flex-1 text-sm text-warning">Mark {{ selectedUnknownIds.length }} {{ selectedUnknownIds.length === 1 ? 'word' : 'words' }} as known?</span>
                            <UiButton variant="success" size="touch" :disabled="lessonBulkPending || selectedUnknownIds.length === 0" @click="bulkMarkLessonWordsKnown(selectedUnknownIds)">Confirm</UiButton>
                            <UiButton variant="ghost" size="touch" :disabled="lessonBulkPending" @click="confirmingLessonKnown = false">Cancel</UiButton>
                        </div>
                        <div v-else class="grid grid-cols-4 gap-1.5">
                            <UiButton variant="primary" size="touch" class="min-w-0 gap-1 px-1 text-xs" :disabled="lessonBulkPending || selectedNotInPracticeIds.length === 0" aria-label="Add selected words to practice" title="Add selected words to practice" @click="bulkStartLessonWords(selectedNotInPracticeIds)"><Plus :size="14" class="shrink-0" /><span class="truncate">Add</span></UiButton>
                            <UiButton variant="secondary" size="touch" class="min-w-0 gap-1 px-1 text-xs" :disabled="lessonBulkPending || selectedInPracticeIds.length === 0" aria-label="Remove selected words from practice" title="Remove selected words from practice" @click="bulkStopLessonWords(selectedInPracticeIds)"><Minus :size="14" class="shrink-0" /><span class="truncate">Remove</span></UiButton>
                            <UiButton variant="secondary" size="touch" class="min-w-0 gap-1 px-1 text-xs" :disabled="lessonBulkPending || selectedUnknownIds.length === 0" aria-label="Mark selected words as known" title="Mark selected words as known" @click="confirmingLessonKnown = true"><Check :size="14" class="shrink-0 text-success-fg" /><span class="truncate">Known</span></UiButton>
                            <UiButton variant="danger" size="touch" class="min-w-0 gap-1 px-1 text-xs" :disabled="lessonBulkPending || itemSaving" aria-label="Permanently delete selected words from this lesson" title="Permanently delete selected words from this lesson" @click="requestPermanentSelectedLessonLexemesDelete"><Trash2 :size="14" class="shrink-0" /><span class="truncate">Delete</span></UiButton>
                        </div>
                        <p v-if="lessonBulkPending" class="flex items-center gap-2 text-xs text-muted-foreground" role="status"><span class="inline-block h-3 w-3 animate-spin rounded-full border-2 border-current border-r-transparent" /> Updating selected words…</p>
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

                </template>
            </template>
        </PageState>
        <UiDialog :open="confirmingLessonLexemeIds.length > 0" :title="`Delete ${confirmingLessonLexemeIds.length} ${confirmingLessonLexemeIds.length === 1 ? 'word' : 'words'} permanently?`" :busy="itemSaving" sheet @close="confirmingLessonLexemeIds = []">
            <p class="text-sm leading-6 text-muted-foreground">
                {{ confirmingLessonLexemeIds.length === 1 ? `“${confirmingLessonLexemes[0]?.text ?? 'This word'}” and its lesson details will be` : `${confirmingLessonLexemeIds.length} selected words and their lesson details will be` }} permanently deleted from this lesson. Entries in My words and practice history, if any, will remain.
            </p>
            <p v-if="itemError" class="mt-3 text-sm text-warning-fg" role="alert">{{ itemError }}</p>
            <template #footer>
                <UiButton variant="secondary" size="touch" :disabled="itemSaving" @click="confirmingLessonLexemeIds = []">Cancel</UiButton>
                <UiButton variant="danger" size="touch" :disabled="itemSaving" @click="confirmPermanentLessonLexemeDelete">{{ itemSaving ? 'Deleting…' : 'Delete permanently' }}</UiButton>
            </template>
        </UiDialog>
    </div>
</template>
