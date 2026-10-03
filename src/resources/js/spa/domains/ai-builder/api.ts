import axios from 'axios';
import type {
    AgentSummary,
    GraphDefinitionDetail,
    GraphDefinitionSummary,
    GraphDefinitionVersion,
    GraphTestRunResult,
    NodePaletteEntry,
    PromptCatalogFlow,
    PromptChatMessage,
    PromptChatReply,
    PromptTemplateDetail,
    PromptTemplateSummary,
    PromptTemplateVersion,
    TestRunResult,
} from './types';

const base = '/api/admin/ai-builder';

export const aiBuilderApi = {
    nodePalette(): Promise<NodePaletteEntry[]> {
        return axios.get(`${base}/node-palette`).then((r) => r.data.data);
    },

    promptCatalog(): Promise<PromptCatalogFlow[]> {
        return axios.get(`${base}/prompt-catalog`).then((r) => r.data.data);
    },

    agents(): Promise<AgentSummary[]> {
        return axios.get(`${base}/agents`).then((r) => r.data.data);
    },

    promptTemplates(): Promise<PromptTemplateSummary[]> {
        return axios.get(`${base}/prompt-templates`).then((r) => r.data.data);
    },

    promptTemplate(key: string): Promise<PromptTemplateDetail> {
        return axios.get(`${base}/prompt-templates/${key}`).then((r) => r.data.data);
    },

    savePromptTemplateDraft(
        key: string,
        payload: { name: string; description?: string | null; system_template: string; user_template: string; model?: string | null },
    ): Promise<PromptTemplateVersion> {
        return axios.post(`${base}/prompt-templates/${key}/versions`, payload).then((r) => r.data.data);
    },

    testRunPrompt(
        key: string,
        payload: { system_template: string; user_template: string; variables?: Record<string, string>; model?: string | null },
    ): Promise<TestRunResult> {
        return axios.post(`${base}/prompt-templates/${key}/test`, payload).then((r) => r.data.data);
    },

    publishPromptTemplateVersion(key: string, versionId: number): Promise<PromptTemplateSummary> {
        return axios.post(`${base}/prompt-templates/${key}/versions/${versionId}/publish`).then((r) => r.data.data);
    },

    chatAboutPrompt(
        key: string,
        payload: { system_template: string; user_template: string; history: PromptChatMessage[]; message: string },
    ): Promise<PromptChatReply> {
        return axios.post(`${base}/prompt-templates/${key}/chat`, payload).then((r) => r.data.data);
    },

    graphDefinitions(): Promise<GraphDefinitionSummary[]> {
        return axios.get(`${base}/graph-definitions`).then((r) => r.data.data);
    },

    graphDefinition(key: string): Promise<GraphDefinitionDetail> {
        return axios.get(`${base}/graph-definitions/${key}`).then((r) => r.data.data);
    },

    saveGraphDefinitionDraft(
        key: string,
        payload: { name: string; description?: string | null; nodes: GraphDefinitionDetail['versions'][number]['nodes']; edges: GraphDefinitionDetail['versions'][number]['edges'] },
    ): Promise<GraphDefinitionVersion> {
        return axios.post(`${base}/graph-definitions/${key}/versions`, payload).then((r) => r.data.data);
    },

    publishGraphDefinitionVersion(key: string, versionId: number): Promise<GraphDefinitionSummary> {
        return axios.post(`${base}/graph-definitions/${key}/versions/${versionId}/publish`).then((r) => r.data.data);
    },

    testRunGraphVersion(
        key: string,
        versionId: number,
        payload: { test_transcript: string; source_language?: string },
    ): Promise<GraphTestRunResult> {
        return axios.post(`${base}/graph-definitions/${key}/versions/${versionId}/test`, payload).then((r) => r.data.data);
    },
};

export interface GraphRunStatusEvent {
    node_key: string | null;
    status: string;
}

/**
 * `EventSource` can't carry the app's Bearer token (Sanctum, not
 * cookie-session — see api/client.ts's axios interceptor), so this uses
 * `fetch()` + a manual reader instead, the same pattern tutorApi.ts's
 * streamMessage() already established for the chat SSE endpoint. Returns
 * an abort function the caller runs on unmount so the connection doesn't
 * keep polling after the canvas is closed.
 */
export function streamGraphRunStatus(runId: number, onEvent: (event: GraphRunStatusEvent) => void): () => void {
    const controller = new AbortController();
    const token = localStorage.getItem('auth_token');

    (async () => {
        let response: Response;
        try {
            response = await fetch(`/api/admin/ai-builder/graph-runs/${runId}/stream`, {
                headers: {
                    Accept: 'text/event-stream',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(token ? { Authorization: `Bearer ${token}` } : {}),
                },
                signal: controller.signal,
            });
        } catch {
            return;
        }

        if (!response.ok || !response.body) return;

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';

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
                onEvent(JSON.parse(dataLine.slice(5).trim()) as GraphRunStatusEvent);
            }
        }
    })().catch(() => {
        // Aborted or connection dropped — nothing to surface, the caller
        // already stopped caring once it unmounted/called the cancel fn.
    });

    return () => controller.abort();
}
