<script setup lang="ts">
import { ref, reactive, computed, nextTick, onMounted, onUnmounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Eraser, Maximize2, Minimize2, Plus, Settings2, Sparkles } from 'lucide-vue-next';
import ChatMessageView from '../shared/ui/ChatMessage.vue';
import UiButton from '../shared/ui/UiButton.vue';
import UiCard from '../shared/ui/UiCard.vue';
import ChatComposerInput from '../shared/ui/ChatComposerInput.vue';
import { tutorApi } from '../domains/ai';
import type { QuizQuestion, TutorToolEvent } from '../domains/ai';
import QuizCard from '../shared/ui/QuizCard.vue';
import UiEmptyState from '../shared/ui/UiEmptyState.vue';
import VoiceDictationControl from '../shared/ui/VoiceDictationControl.vue';
import type { SpeechLanguage, SpeechProvider } from '../domains/learning';
import { useAuthStore } from '../domains/user';

interface ChatMessage {
    role: 'user' | 'assistant';
    content: string;
    // Task 6.3: set once the turn's `done` event carried GenerateQuizTool's
    // draft questions — rendered as an interactive QuizCard instead of the
    // model's plain-text summary of the same quiz.
    quiz?: QuizQuestion[];
    audioUrl?: string;
}

const VALID_CONTEXT_TYPES = ['content', 'grammar', 'lexeme'] as const;
type ContextType = (typeof VALID_CONTEXT_TYPES)[number];

interface EntryContext {
    type: ContextType;
    id: string;
    title: string;
}

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const conversationId = ref<number | null>(null);
const messages = ref<ChatMessage[]>([]);
const inputText = ref('');
const voiceAudio = ref<Blob | null>(null);
const voiceProvider = ref<SpeechProvider>('local_whisper');
const voiceLanguage = ref<SpeechLanguage>('en');
const keepVoiceForever = ref(false);
const voiceControl = ref<InstanceType<typeof VoiceDictationControl> | null>(null);
const sending = ref(false);
const error = ref('');
const aiDisabled = ref(false);
const messagesEnd = ref<HTMLElement | null>(null);
const fullScreen = ref(true);
let previousBodyOverflow = '';

// Task 6.5: known upfront from authStore.roles for a staff account — shown
// instead of the whole chat UI so there's no flash-then-error. accessDenied
// also gets set defensively if a request still comes back 403 (e.g. roles
// changed server-side since the SPA last fetched /api/auth/me), so this
// isn't purely a client-side gate.
const accessDenied = ref(!authStore.canAccessTutorAgent);

// Task 6.2: transient "what is the agent doing right now" status, driven by
// SSE tool_start/tool_end/handoff events — cleared as soon as either the
// matching tool_end/handoff-side arrives or actual reply text starts
// streaming in, whichever happens first.
const toolStatus = ref<string | null>(null);

function handleToolEvent(event: TutorToolEvent): void {
    if (event.kind === 'tool_start' || event.kind === 'handoff') {
        toolStatus.value = event.label;
    } else {
        toolStatus.value = null;
    }
}

// Task 6.1 (design 6.1): the query-param entry context is read once on
// mount. `discussingLabel` is the "несбиваемый" chip — it stays for the
// whole page session once set, there is no way to dismiss it, matching the
// design's "unremovable" wording. `contextToInject` is separate and gets
// cleared after the very first message: only that one message actually
// carries the context, both as a text prefix and as the observational
// context_type/context_ref_id/context_label fields on the request.
const discussingLabel = ref<string | null>(null);
const contextToInject = ref<EntryContext | null>(null);
const failedTurn = ref<{ text: string; context: EntryContext | null; audio: Blob | null; provider: SpeechProvider; language: SpeechLanguage; keepForever: boolean } | null>(null);

onMounted(() => {
    window.addEventListener('keydown', handleGlobalKeydown);
    previousBodyOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    const type = route.query.context_type;
    const id = route.query.context_id;
    const title = route.query.context_title;

    if (
        typeof type === 'string' &&
        typeof id === 'string' &&
        typeof title === 'string' &&
        (VALID_CONTEXT_TYPES as readonly string[]).includes(type) &&
        id !== '' &&
        title !== ''
    ) {
        const context: EntryContext = { type: type as ContextType, id, title };
        contextToInject.value = context;
        discussingLabel.value = title;
    }
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleGlobalKeydown);
    if (fullScreen.value) document.body.style.overflow = previousBodyOverflow;
});

watch(fullScreen, (isFullScreen) => {
    if (isFullScreen) {
        previousBodyOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = previousBodyOverflow;
    }
});

function handleGlobalKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape' && fullScreen.value) fullScreen.value = false;
}

function contextTypeLabel(type: ContextType): string {
    switch (type) {
        case 'content':
            return 'this content';
        case 'grammar':
            return 'this grammar rule';
        case 'lexeme':
            return 'this word';
    }
}

const canSend = computed(() => {
    const t = inputText.value?.trim();
    return !!t && !sending.value && !aiDisabled.value;
});

const quickPrompts = [
    'Explain my weak words',
    'Quiz me on this content',
    'Give me 5 examples',
    'Plan today\'s study',
];

function onVoiceReady(text: string, audio: Blob, provider: SpeechProvider, language: SpeechLanguage, keepForever: boolean) {
    inputText.value = inputText.value.trim() ? `${inputText.value.trim()} ${text}` : text;
    voiceAudio.value = audio;
    voiceProvider.value = provider;
    voiceLanguage.value = language;
    keepVoiceForever.value = keepForever;
}

function onVoiceRetentionChanged(keepForever: boolean) {
    keepVoiceForever.value = keepForever;
    if (failedTurn.value?.audio) failedTurn.value = { ...failedTurn.value, keepForever };
}

async function ensureConversation(): Promise<number> {
    if (conversationId.value != null) return conversationId.value;
    try {
        const data = await tutorApi.createConversation();
        conversationId.value = data.conversation_id;
        return data.conversation_id;
    } catch (e: unknown) {
        const err = e as { response?: { status?: number; data?: { message?: string } } };
        if (err.response?.status === 403) {
            accessDenied.value = true;
        } else if (err.response?.status === 503) {
            aiDisabled.value = true;
            error.value = err.response?.data?.message ?? 'Chat is currently disabled.';
        } else {
            error.value = err.response?.data?.message ?? 'Failed to start conversation.';
        }
        throw e;
    }
}

async function send(textOverride?: string, retry = false) {
    if (!retry && ['/practice', '/practice translate'].includes(inputText.value.trim().toLowerCase())) {
        await router.push({ name: 'speaking-practice', query: { return_to: 'chat' } });
        inputText.value = '';
        return;
    }
    if (!retry && ['/mistakes', '/report'].includes(inputText.value.trim().toLowerCase())) {
        await router.push({ name: 'speaking-mistakes' });
        inputText.value = '';
        return;
    }
    const text = (textOverride ?? inputText.value)?.trim();
    if (!text || sending.value) return;

    sending.value = true;
    error.value = '';
    let assistantMessage: ChatMessage | null = null;
    const previousFailure = retry ? failedTurn.value : null;
    const context = previousFailure?.context ?? contextToInject.value;
    failedTurn.value = null;
    try {
        const cid = await ensureConversation();
        const audio = previousFailure?.audio ?? voiceAudio.value;
        const selectedProvider = previousFailure?.provider ?? voiceProvider.value;
        const selectedLanguage = previousFailure?.language ?? voiceLanguage.value;
        const keepForever = previousFailure?.keepForever ?? keepVoiceForever.value;
        messages.value.push({ role: 'user', content: text, ...(audio ? { audioUrl: URL.createObjectURL(audio) } : {}) });
        inputText.value = '';
        voiceAudio.value = null;
        sending.value = true;

        // Task 6.1: the chat bubble shows exactly what the user typed; the
        // context prefix is only added to the text actually sent to the
        // model, so the UI stays clean while the model still gets the
        // one-time hint about what page the student came from.
        const outgoingText = context
            ? `[The student is asking about ${contextTypeLabel(context.type)}: "${context.title}"]\n\n${text}`
            : text;

        // Placeholder streamed into as SSE deltas arrive (task 3.6) — starts
        // empty, rendered as the "thinking" state until the first chunk lands.
        assistantMessage = reactive<ChatMessage>({ role: 'assistant', content: '' });
        messages.value.push(assistantMessage);

        toolStatus.value = null;

        const result = await tutorApi.streamMessage(
            cid,
            outgoingText,
            (chunk) => {
                // Reply text has started — whatever "doing X" status was
                // showing is no longer accurate.
                toolStatus.value = null;
                assistantMessage!.content += chunk;
                scrollToBottom();
            },
            context
                ? {
                      context_type: context.type,
                      context_ref_id: Number(context.id),
                      context_label: context.title,
                  }
                : undefined,
            handleToolEvent,
            audio ? { audio, provider: selectedProvider, language: selectedLanguage, keepForever } : undefined,
        );

        if (result.quiz.length > 0) {
            assistantMessage.quiz = result.quiz;
        }

        contextToInject.value = null;
        voiceControl.value?.clearRecording();
        await scrollToBottom();
    } catch (e: unknown) {
        const err = e as { response?: { status?: number; data?: { message?: string } } };
        if (err.response?.status === 403) {
            accessDenied.value = true;
        } else if (err.response?.status === 503) {
            aiDisabled.value = true;
            error.value = err.response?.data?.message ?? 'Chat is currently disabled.';
        } else {
            error.value = err.response?.data?.message ?? 'Failed to send message.';
        }
        failedTurn.value = {
            text,
            context,
            audio: previousFailure?.audio ?? voiceAudio.value,
            provider: previousFailure?.provider ?? voiceProvider.value,
            language: previousFailure?.language ?? voiceLanguage.value,
            keepForever: previousFailure?.keepForever ?? keepVoiceForever.value,
        };
        if (assistantMessage) messages.value = messages.value.filter((message) => message !== assistantMessage);
        if (messages.value.at(-1)?.role === 'user' && messages.value.at(-1)?.content === text) messages.value.pop();
        inputText.value = text;
        if (failedTurn.value.audio) voiceAudio.value = failedTurn.value.audio;
    } finally {
        sending.value = false;
        toolStatus.value = null;
    }
}

async function scrollToBottom() {
    await nextTick();
    messagesEnd.value?.scrollIntoView({ behavior: 'smooth' });
}

function startNewChat() {
    conversationId.value = null;
    failedTurn.value = null;
    messages.value = [];
    error.value = '';
    aiDisabled.value = false;
    // A deliberate new chat leaves the entry context behind too — it was
    // tied to how the user *arrived* here, not to "conversation" as a
    // concept, so it doesn't make sense to carry it into a fresh one.
    discussingLabel.value = null;
    contextToInject.value = null;
}

function clearDraft(): void {
    if (sending.value) return;
    if (failedTurn.value) error.value = '';
    inputText.value = '';
    voiceAudio.value = null;
    failedTurn.value = null;
    voiceControl.value?.clearRecording();
}

function toggleFullScreen(): void {
    fullScreen.value = !fullScreen.value;
}
</script>

<template>
    <div
        class="mx-auto flex w-full max-w-5xl flex-col gap-4"
        :class="fullScreen ? 'fixed inset-0 z-[100] h-[100dvh] max-w-none gap-0 overflow-hidden bg-background p-0' : ''"
        :role="fullScreen ? 'dialog' : undefined"
        :aria-modal="fullScreen ? 'true' : undefined"
        :aria-label="fullScreen ? 'AI tutor conversation' : undefined"
    >
        <div class="flex shrink-0 items-center justify-between gap-3 border-b border-border bg-background px-4 py-3 sm:px-5" :class="fullScreen ? 'pt-[max(env(safe-area-inset-top),0.75rem)]' : 'rounded-spa-lg border'">
            <div class="flex min-w-0 items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                    <Sparkles :size="19" aria-hidden="true" />
                </div>
                <div class="min-w-0">
                    <h1 class="font-semibold leading-5 text-fg">AI tutor</h1>
                    <p class="truncate text-xs text-muted-foreground">Your words, grammar and learning progress</p>
                </div>
            </div>
            <div class="flex shrink-0 items-center gap-1">
                <div id="tutor-chat-settings" class="relative flex items-center">
                    <UiButton variant="ghost" size="icon-touch" aria-label="Voice settings" title="Voice settings" aria-haspopup="dialog" :aria-expanded="voiceControl?.settingsOpen ?? false" @click="voiceControl?.toggleSettings()">
                        <Settings2 :size="18" aria-hidden="true" />
                    </UiButton>
                </div>
                <UiButton variant="ghost" :disabled="sending" aria-label="Start a new chat" title="New chat" @click="startNewChat">
                    <Plus :size="17" aria-hidden="true" /><span class="hidden sm:inline">New chat</span>
                </UiButton>
                <UiButton
                    variant="secondary"
                    size="icon-touch"
                    :aria-label="fullScreen ? 'Exit full screen' : 'Full screen'"
                    :title="fullScreen ? 'Exit full screen' : 'Full screen'"
                    @click="toggleFullScreen"
                >
                    <Minimize2 v-if="fullScreen" :size="17" aria-hidden="true" />
                    <Maximize2 v-else :size="17" aria-hidden="true" />
                </UiButton>
            </div>
        </div>

        <UiCard v-if="accessDenied" class="flex-1">
            <UiEmptyState
                title="Not available for your account"
                description="The tutor chat is a student-facing feature — it isn't available for admin, editor, or moderator accounts."
            />
        </UiCard>

        <div v-else-if="aiDisabled" class="rounded-spa-lg border border-warning-border bg-warning-bg p-4 text-warning-fg">
            {{ error }}
        </div>

        <template v-else>
            <section class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-spa-lg border border-border bg-surface" :class="fullScreen ? 'rounded-none border-x-0 border-b-0' : 'h-[min(72dvh,780px)] min-h-[440px]'" aria-label="Conversation">
                <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain bg-background/70 px-3 py-4 sm:px-6 sm:py-6">
                    <div class="mx-auto flex w-full max-w-3xl flex-col gap-5">
                        <div v-if="messages.length === 0 && !sending" class="py-6 sm:py-10">
                            <div class="mx-auto max-w-xl text-center">
                                <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-primary">
                                    <Sparkles :size="22" aria-hidden="true" />
                                </div>
                                <h2 class="text-lg font-semibold text-fg">What would you like to learn?</h2>
                                <p class="mt-2 text-sm leading-6 text-muted-foreground">
                                    <template v-if="discussingLabel">Ask anything about “{{ discussingLabel }}”.</template>
                                    <template v-else>Ask about a word, grammar point, mistake or your learning progress.</template>
                                </p>
                                <div class="mt-6 grid grid-cols-1 gap-2 text-left sm:grid-cols-2">
                                    <UiButton v-for="prompt in quickPrompts" :key="prompt" variant="secondary" class="!h-auto min-h-11 justify-start whitespace-normal px-3 py-2.5 text-left leading-5" @click="send(prompt)">
                                        {{ prompt }}
                                    </UiButton>
                                </div>
                            </div>
                        </div>
                        <div v-for="(msg, i) in messages" :key="i" class="space-y-2">
                            <ChatMessageView :role="msg.role" :content="msg.content" :loading="msg.role === 'assistant' && msg.content === '' && sending" :loading-label="toolStatus ? `${toolStatus}...` : null" />
                        <audio v-if="msg.audioUrl" :src="msg.audioUrl" controls class="ml-auto h-9 max-w-full" aria-label="Your saved voice message" />
                            <QuizCard v-if="msg.quiz && msg.quiz.length > 0" :questions="msg.quiz" />
                        </div>
                        <div ref="messagesEnd" />
                    </div>
                </div>

                <ChatMessageView v-if="error" role="assistant" :error="error" :retryable="Boolean(failedTurn) && !sending" :loading="sending" @retry="send(failedTurn?.text, true)" />

                <div v-if="discussingLabel" class="flex shrink-0 items-center gap-2 border-t border-border bg-primary/5 px-4 py-2.5 text-sm text-fg sm:px-6">
                    <span class="text-xs font-medium text-primary">Discussing</span>
                    <span class="truncate font-medium">{{ discussingLabel }}</span>
                </div>

                <form class="shrink-0 border-t border-border bg-surface px-3 pt-3 pb-[max(env(safe-area-inset-bottom),0.75rem)] sm:px-6 sm:py-4" @submit.prevent="send()">
                    <div class="mx-auto flex w-full max-w-3xl flex-col gap-2">
                        <ChatComposerInput
                            v-model="inputText"
                            class="w-full"
                            placeholder="Type a message..."
                            aria-label="Message the AI tutor"
                            :disabled="sending || aiDisabled"
                            @submit="send()"
                        />
                        <div class="flex shrink-0 items-center justify-between gap-2">
                            <UiButton variant="ghost" size="touch" :disabled="sending || (!inputText.trim() && !voiceAudio && !failedTurn)" type="button" @click="clearDraft()">
                                <Eraser :size="16" aria-hidden="true" />
                                Clear all
                            </UiButton>
                            <div class="flex items-center gap-2">
                            <VoiceDictationControl ref="voiceControl" settings-target="#tutor-chat-settings" @ready="onVoiceReady" @retention="onVoiceRetentionChanged" @cleared="voiceAudio = null" />
                            <UiButton variant="primary" size="touch" :disabled="!canSend" type="submit">Send</UiButton>
                            </div>
                        </div>
                    </div>
                    <p class="mx-auto mt-2 hidden max-w-3xl text-xs text-muted-foreground sm:block">Answers are grounded in your learning activity.</p>
                </form>
            </section>
        </template>
    </div>
</template>
