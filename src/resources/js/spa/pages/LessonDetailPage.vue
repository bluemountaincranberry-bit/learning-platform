<script setup lang="ts">
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Paperclip, Sparkles, Save, Loader2 } from 'lucide-vue-next';
import PageState from '../components/ui/PageState.vue';
import { useAuthStore } from '../domains/user';
import { lessonApi, type LessonDetail, type LessonMessage } from '../domains/learning';
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

const inputText = ref('');
const attachment = ref<File | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const messagesEnd = ref<HTMLElement | null>(null);
const notesTextarea = ref<HTMLTextAreaElement | null>(null);

const activeTab = ref<'notes' | 'words' | 'grammar' | 'chat'>('notes');

const tabs = [
    { key: 'notes', label: 'Notes' },
    { key: 'words', label: 'Words' },
    { key: 'grammar', label: 'Grammar' },
    { key: 'chat', label: 'Chat' },
] as const;

const canSend = computed(() => (inputText.value.trim() !== '' || attachment.value !== null) && !sending.value);

async function loadLesson() {
    lesson.value = await lessonApi.get(lessonId.value);
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

function autoResizeTextarea(el: HTMLTextAreaElement | null) {
    if (!el) return;
    el.style.height = 'auto';
    el.style.height = `${Math.min(el.scrollHeight, 400)}px`;
}

async function saveNotes() {
    if (!lesson.value) return;
    saving.value = true;
    error.value = '';
    try {
        await lessonApi.update(lessonId.value, {
            notes: lesson.value.notes,
            homework: lesson.value.homework,
        });
    } catch (e: unknown) {
        const err = e as { response?: { data?: { message?: string } } };
        error.value = err.response?.data?.message ?? 'Failed to save notes.';
    } finally {
        saving.value = false;
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
                        <div class="flex items-center gap-2">
                            <UiButton variant="secondary" :disabled="saving" @click="saveNotes">
                                <Save :size="16" class="mr-1" /> {{ saving ? 'Saving...' : 'Save' }}
                            </UiButton>
                            <UiButton variant="primary" :disabled="analyzing" @click="analyzeLesson">
                                <Sparkles :size="16" /> {{ analyzing ? 'Analyzing...' : 'Analyze lesson' }}
                            </UiButton>
                        </div>
                    </div>
                </UiCard>

                <div v-if="error" class="rounded-spa-lg border border-warning-border bg-warning-bg p-4 text-sm text-warning-fg" role="alert">
                    {{ error }}
                </div>

                <!-- Tabs -->
                <UiTabs v-model="activeTab" :tabs="tabs" class="mb-4" />

                <!-- Notes Tab -->
                <div v-if="activeTab === 'notes'" class="space-y-4">
                    <UiCard class="space-y-4">
                        <UiSectionHeader title="Lesson notes" subtitle="Markdown supported" />
                        <div class="space-y-3">
                            <label class="block">
                                <textarea
                                    ref="notesTextarea"
                                    v-model="lesson.notes"
                                    class="w-full min-h-[180px] max-h-[500px] resize-none rounded-spa border border-border bg-surface p-4 text-base font-mono text-fg placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                    placeholder="Write your lesson notes here…"
                                    @input="autoResizeTextarea(notesTextarea)"
                                    @keydown.enter.exact.prevent="saveNotes()"
                                ></textarea>
                                <p class="mt-2 text-xs text-muted-foreground">
                                    Auto-saves on blur. Press Ctrl+Enter to save.
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
                                    @input="autoResizeTextarea($event.target)"
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
                        <UiSectionHeader title="Words" :subtitle="`${lesson.lexemes.length} from this lesson`" />
                        <UiEmptyState v-if="lesson.lexemes.length === 0" title="Nothing yet" description="Tap “Analyze lesson” once you have written your notes." />
                        <div v-else class="space-y-2">
                            <WordRow
                                v-for="w in lesson.lexemes"
                                :key="w.id"
                                :text="w.text"
                                :language="w.language"
                                :translation="w.translation"
                                :level="w.level"
                                :lexeme-id="w.matched_lexeme_id"
                                :example="w.example"
                                :examples="w.example ? [{ example: w.example, translation: w.example_translation, is_primary: true }] : []"
                            >
                                <span class="text-xs text-muted-foreground">{{ w.status === 'matched' ? 'Already in your dictionary' : 'New' }}</span>
                            </WordRow>
                        </div>
                    </UiCard>
                </div>

                <!-- Grammar Tab -->
                <div v-if="activeTab === 'grammar'" class="space-y-4">
                    <UiCard class="space-y-3">
                        <UiSectionHeader title="Grammar" :subtitle="`${lesson.grammar.length} from this lesson`" />
                        <UiEmptyState v-if="lesson.grammar.length === 0" title="Nothing yet" description="Tap “Analyze lesson” once you have written your notes." />
                        <div v-else class="space-y-2">
                            <GrammarCard
                                v-for="g in lesson.grammar"
                                :key="g.id"
                                :title="g.title"
                                :rule-id="g.matched_grammar_rule_id"
                                :summary="g.summary"
                                :status="g.status === 'linked' ? 'Added to My grammar' : 'New'"
                            />
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