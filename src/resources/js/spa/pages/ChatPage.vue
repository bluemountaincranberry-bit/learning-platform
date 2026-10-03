<script setup lang="ts">
import { ref, computed, nextTick, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import UiButton from '../shared/ui/UiButton.vue';
import UiCard from '../shared/ui/UiCard.vue';
import UiInput from '../shared/ui/UiInput.vue';
import UiSectionHeader from '../shared/ui/UiSectionHeader.vue';
import { tutorApi } from '../domains/ai';
import type { QuizQuestion, TutorToolEvent } from '../domains/ai';
import QuizCard from '../shared/ui/QuizCard.vue';
import UiEmptyState from '../shared/ui/UiEmptyState.vue';
import { useAuthStore } from '../domains/user';

interface ChatMessage {
    role: 'user' | 'assistant';
    content: string;
    // Task 6.3: set once the turn's `done` event carried GenerateQuizTool's
    // draft questions — rendered as an interactive QuizCard instead of the
    // model's plain-text summary of the same quiz.
    quiz?: QuizQuestion[];
}

const VALID_CONTEXT_TYPES = ['content', 'grammar', 'lexeme'] as const;
type ContextType = (typeof VALID_CONTEXT_TYPES)[number];

interface EntryContext {
    type: ContextType;
    id: string;
    title: string;
}

const route = useRoute();
const authStore = useAuthStore();

const conversationId = ref<number | null>(null);
const messages = ref<ChatMessage[]>([]);
const inputText = ref('');
const sending = ref(false);
const error = ref('');
const aiDisabled = ref(false);
const messagesEnd = ref<HTMLElement | null>(null);

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

onMounted(() => {
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

async function send(textOverride?: string) {
    const text = (textOverride ?? inputText.value)?.trim();
    if (!text || sending.value) return;

    error.value = '';
    let assistantMessage: ChatMessage | null = null;
    // Captured up front and consumed at most once: contextToInject is
    // cleared as soon as this message is sent, regardless of outcome, so a
    // failed send doesn't leave the context to be silently retried on the
    // next unrelated message.
    const context = contextToInject.value;
    contextToInject.value = null;
    try {
        const cid = await ensureConversation();
        messages.value.push({ role: 'user', content: text });
        inputText.value = '';
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
        assistantMessage = { role: 'assistant', content: '' };
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
            handleToolEvent
        );

        if (result.quiz.length > 0) {
            assistantMessage.quiz = result.quiz;
        }

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
        if (assistantMessage && messages.value[messages.value.length - 1] === assistantMessage) {
            messages.value.pop();
        }
        if (messages.value.length > 0 && messages.value[messages.value.length - 1].role === 'user') {
            messages.value.pop();
        }
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
    messages.value = [];
    error.value = '';
    aiDisabled.value = false;
    // A deliberate new chat leaves the entry context behind too — it was
    // tied to how the user *arrived* here, not to "conversation" as a
    // concept, so it doesn't make sense to carry it into a fresh one.
    discussingLabel.value = null;
    contextToInject.value = null;
}
</script>

<template>
    <div class="space-y-6">
        <UiCard>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="space-y-2">
                    <div class="text-xs uppercase tracking-[0.18em] text-muted-foreground">AI surface</div>
                    <h2 class="text-2xl font-semibold text-fg">Tutor chat</h2>
                    <p class="max-w-2xl text-sm leading-6 text-muted-foreground">
                        Ask about your own words, mistakes, grammar or review schedule — answers are grounded in your
                        real progress, not guesses.
                    </p>
                </div>
                <UiButton variant="secondary" :disabled="sending" @click="startNewChat">New chat</UiButton>
            </div>
        </UiCard>

        <UiCard v-if="accessDenied">
            <UiEmptyState
                title="Not available for your account"
                description="The tutor chat is a student-facing feature — it isn't available for admin, editor, or moderator accounts."
            />
        </UiCard>

        <div v-else-if="aiDisabled" class="rounded-spa-lg border border-warning-border bg-warning-bg p-4 text-warning-fg">
            {{ error }}
        </div>

        <template v-else>
            <UiCard class="space-y-4">
                <UiSectionHeader title="Quick prompts" subtitle="Shortcuts for the most common learning questions" />
                <div class="flex flex-wrap gap-2">
                    <UiButton v-for="prompt in quickPrompts" :key="prompt" variant="ghost" @click="send(prompt)">
                        {{ prompt }}
                    </UiButton>
                </div>
            </UiCard>

            <UiCard class="overflow-hidden p-0">
                <div class="flex h-[min(68vh,760px)] flex-col">
                    <div class="flex-1 overflow-y-auto p-4 space-y-4 bg-black/10">
                        <template v-if="messages.length === 0 && !sending">
                            <div class="rounded-spa-lg border border-dashed border-border-strong p-5 text-sm text-muted-foreground">
                                <template v-if="discussingLabel">
                                    Send a message and it'll go straight into the conversation about "{{ discussingLabel }}".
                                </template>
                                <template v-else>
                                    Send a prompt, or open chat from content/grammar/a word to bring its context along.
                                </template>
                            </div>
                        </template>
                        <div v-for="(msg, i) in messages" :key="i" class="space-y-2">
                            <div class="flex" :class="msg.role === 'user' ? 'justify-end' : 'justify-start'">
                                <div
                                    class="max-w-[85%] rounded-spa-lg border px-4 py-3 text-sm leading-6"
                                    :class="
                                        msg.role === 'user'
                                            ? 'border-primary bg-primary text-slate-950'
                                            : 'border-border bg-surface text-fg-secondary'
                                    "
                                >
                                    <template v-if="msg.role === 'assistant' && msg.content === '' && sending">
                                        <span class="inline-flex items-center gap-2 text-muted-foreground">
                                            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-primary"></span>
                                            {{ toolStatus ? `${toolStatus}...` : 'Thinking...' }}
                                        </span>
                                    </template>
                                    <template v-else>{{ msg.content }}</template>
                                </div>
                            </div>
                            <QuizCard v-if="msg.quiz && msg.quiz.length > 0" :questions="msg.quiz" />
                        </div>
                        <div ref="messagesEnd" />
                    </div>

                    <div v-if="error" class="border-t border-border px-4 py-2 text-sm text-warning" role="alert">
                        {{ error }}
                    </div>

                    <div
                        v-if="discussingLabel"
                        class="flex items-center gap-2 border-t border-border bg-primary/10 px-4 py-2 text-sm text-fg"
                    >
                        <span class="text-xs uppercase tracking-[0.14em] text-primary">Discussing</span>
                        <span class="truncate font-medium">{{ discussingLabel }}</span>
                    </div>

                    <div class="border-t border-border bg-black/20 p-3">
                        <div class="flex gap-2">
                            <UiInput
                                v-model="inputText"
                                type="text"
                                placeholder="Type a message..."
                                :disabled="sending || aiDisabled"
                                @keydown.enter.prevent="send()"
                            />
                            <UiButton variant="primary" :disabled="!canSend" @click="send()">Send</UiButton>
                        </div>
                    </div>
                </div>
            </UiCard>
        </template>
    </div>
</template>
