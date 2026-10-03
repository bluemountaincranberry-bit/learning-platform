import axios from 'axios';
import type { QuizQuestion } from '../../../shared/types/QuizQuestion';
export type { QuizQuestion } from '../../../shared/types/QuizQuestion';

/**
 * Student-facing TutorAgent (EPIC-3.5/3.6) — backed by
 * TutorConversationController / StudentTutorAgentService, not the older,
 * non-agentic ChatContextAiService (AiConversationController). The reply is
 * grounded in the student's own data via tools instead of a single
 * ungrounded completion, and streams in over SSE (task 3.6) rather than
 * arriving as one JSON blob. Task 6.7: the SPA's own client for that older
 * flow (api/chatApi.ts) was dead code with zero callers besides its own
 * re-export — removed. The backend (AiConversationController/
 * ChatContextAiService) stays: it's still used by the Filament admin panel
 * (AiChatOverviewWidget, AdminPanelProvider) and still wraps
 * SemanticCacheService (5.4) for its own cache lookups.
 *
 * streamMessage() uses the native fetch API rather than axios: axios (via
 * XHR) cannot expose a readable byte stream of an in-flight response body in
 * the browser, which SSE parsing needs. It replicates just the one bit of
 * axios's request setup this endpoint actually needs — the bearer token
 * (see api/client.ts's interceptor, which fetch bypasses) — directly from
 * the same localStorage key authStore persists it to.
 */
const AUTH_TOKEN_STORAGE_KEY = 'auth_token';

interface TutorApiError extends Error {
    response?: { status?: number; data?: { message?: string } };
}

/**
 * Task 6.2: one tool-call boundary as it happens mid-turn — `kind` mirrors
 * the SSE event key verbatim (`tool_start`/`tool_end`/`handoff`, see
 * StudentTutorAgentService::observerFor()'s docblock for why handoff has no
 * matching "end"). `label` is a human-readable status line derived
 * client-side from the raw tool name — kept here rather than on the backend
 * so wording changes don't need a deploy on that side.
 */
export interface TutorToolEvent {
    kind: 'tool_start' | 'tool_end' | 'handoff';
    tool: string;
    label: string;
}

const TOOL_LABELS: Record<string, string> = {
    get_user_level: 'Checking your level',
    get_user_mistakes: 'Looking at your mistakes',
    get_learning_history: 'Reviewing your learning history',
    get_weak_topics: 'Finding your weak topics',
    get_vocabulary_size: 'Counting your vocabulary',
    get_review_schedule: 'Checking your review schedule',
    search_vocabulary: 'Searching the dictionary',
    explain_grammar: 'Looking up grammar',
    find_examples: 'Finding examples',
    generate_quiz: 'Putting together a quiz',
    handoff_to_grammar_specialist: 'Bringing in the grammar specialist',
    handoff_to_review_planner: 'Bringing in the review planner',
};

function labelForTool(tool: string): string {
    return TOOL_LABELS[tool] ?? 'Working on it';
}

/**
 * Task 6.3/6.4: matches GenerateQuizTool::sanitizeQuestions()'s shape
 * exactly (`type`/`prompt`/`answer` always present, `choices` only for
 * multiple_choice) — see that class for the sanitization guarantees this
 * relies on (e.g. multiple_choice always has >= 2 choices).
 */
export const tutorApi = {
    createConversation(): Promise<{ conversation_id: number }> {
        return axios.post('/api/tutor/conversations').then((r) => r.data);
    },

    /**
     * Posts a message and streams the assistant's reply. Calls `onDelta`
     * with each text chunk as it arrives; resolves with the persisted
     * assistant message id once the server sends its "done" event.
     *
     * `context` (task 6.1) is only ever sent on the one message ChatPage.vue
     * injects entry-page context into — it lands on that message's
     * `context_type`/`context_ref_id`/`context_label` columns for
     * observability, see `add_context_columns_to_agent_messages_table`.
     *
     * `onToolEvent` (task 6.2) fires zero or more times before the final
     * delta/done, whenever the agent starts or finishes a tool call (or
     * hands off to a specialist) mid-turn.
     *
     * The resolved `quiz` (task 6.3) is GenerateQuizTool's draft questions,
     * flattened out of the `done` event's `toolResults` — empty when no
     * quiz tool was called this turn. See
     * TutorConversationController::streamTurn()'s docblock for exactly how
     * that JSON gets from tool execution to this event.
     */
    async streamMessage(
        conversationId: number,
        content: string,
        onDelta: (chunk: string) => void,
        context?: { context_type: string; context_ref_id: number; context_label: string },
        onToolEvent?: (event: TutorToolEvent) => void
    ): Promise<{ message_id: number | null; quiz: QuizQuestion[] }> {
        const token = localStorage.getItem(AUTH_TOKEN_STORAGE_KEY);

        const response = await fetch(`/api/tutor/conversations/${conversationId}/messages`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'text/event-stream',
                'X-Requested-With': 'XMLHttpRequest',
                ...(token ? { Authorization: `Bearer ${token}` } : {}),
            },
            body: JSON.stringify({ content, ...context }),
        });

        if (!response.ok || !response.body) {
            let data: { message?: string } = {};
            try {
                data = await response.json();
            } catch {
                // Body wasn't JSON (e.g. a plain error page) — fall back to the status alone.
            }
            const error: TutorApiError = new Error(data.message ?? `Request failed with status ${response.status}`);
            error.response = { status: response.status, data };
            throw error;
        }

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';
        let messageId: number | null = null;
        let quiz: QuizQuestion[] = [];

        // eslint-disable-next-line no-constant-condition
        while (true) {
            const { done, value } = await reader.read();
            if (done) break;
            buffer += decoder.decode(value, { stream: true });

            let eventEnd: number;
            while ((eventEnd = buffer.indexOf('\n\n')) !== -1) {
                const rawEvent = buffer.slice(0, eventEnd);
                buffer = buffer.slice(eventEnd + 2);

                const dataLine = rawEvent.split('\n').find((line) => line.startsWith('data:'));
                if (!dataLine) continue;

                const payload = JSON.parse(dataLine.slice(5).trim()) as {
                    delta?: string;
                    done?: boolean;
                    message_id?: number;
                    error?: string;
                    tool_start?: string;
                    tool_end?: string;
                    handoff?: string;
                    toolResults?: Array<{ quiz?: QuizQuestion[] }>;
                };

                if (payload.error) {
                    throw new Error(payload.error);
                }
                if (payload.delta) {
                    onDelta(payload.delta);
                }
                if (payload.tool_start) {
                    onToolEvent?.({ kind: 'tool_start', tool: payload.tool_start, label: labelForTool(payload.tool_start) });
                }
                if (payload.tool_end) {
                    onToolEvent?.({ kind: 'tool_end', tool: payload.tool_end, label: labelForTool(payload.tool_end) });
                }
                if (payload.handoff) {
                    onToolEvent?.({ kind: 'handoff', tool: payload.handoff, label: labelForTool(payload.handoff) });
                }
                if (payload.done) {
                    messageId = payload.message_id ?? null;
                    if (Array.isArray(payload.toolResults)) {
                        quiz = payload.toolResults.flatMap((result) => (Array.isArray(result?.quiz) ? result.quiz : []));
                    }
                }
            }
        }

        return { message_id: messageId, quiz };
    },
};
