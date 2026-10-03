<script setup lang="ts">
import '@vue-flow/core/dist/style.css';
import '@vue-flow/controls/dist/style.css';

import { Background } from '@vue-flow/background';
import { Controls } from '@vue-flow/controls';
import { VueFlow, useVueFlow, type Edge, type EdgeMouseEvent, type Node, type NodeMouseEvent } from '@vue-flow/core';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import UiBadge from '../../shared/ui/UiBadge.vue';
import UiButton from '../../shared/ui/UiButton.vue';
import UiInput from '../../shared/ui/UiInput.vue';
import { aiBuilderApi, streamGraphRunStatus } from '../../domains/ai-builder/api';
import { useDraftState } from '../../domains/ai-builder/useDraftState';
import DraftSaveBar from '../../domains/ai-builder/DraftSaveBar.vue';
import AiBuilderTopBar from '../../domains/ai-builder/AiBuilderTopBar.vue';
import GraphCanvasNode from '../../domains/ai-builder/GraphCanvasNode.vue';
import {
    domainEdgeToFlowEdge,
    domainNodeToFlowNode,
    flowEdgeToDomainEdge,
    flowNodeToDomainNode,
    type GraphCanvasNodeData,
} from '../../domains/ai-builder/vueFlowAdapter';
import type { GraphDefinitionVersion, GraphTestRunResult, NodePaletteEntry, PromptTemplateVersion } from '../../domains/ai-builder/types';

const route = useRoute();
const graphKey = computed(() => String(route.params.key));
const runId = computed(() => (route.query.run ? Number(route.query.run) : null));

const { screenToFlowCoordinate, addNodes, addEdges, removeNodes, removeEdges } = useVueFlow();

const palette = ref<NodePaletteEntry[]>([]);
const nodes = ref<Node<GraphCanvasNodeData>[]>([]);
const edges = ref<Edge[]>([]);
const versions = ref<GraphDefinitionVersion[]>([]);
const activeVersionId = ref<number | null>(null);
const selectedVersionId = ref<number | null>(null);
const name = ref('');
const description = ref('');

const loading = ref(true);
const saving = ref(false);
const publishing = ref(false);
const errorMessage = ref<string | null>(null);
const savedMessage = ref<string | null>(null);
const { isDirty, markSaved } = useDraftState(() => ({
    name: name.value,
    description: description.value,
    nodes: (nodes.value as unknown[]).map((node) => JSON.stringify(node)),
    edges: (edges.value as unknown[]).map((edge) => JSON.stringify(edge)),
}));

const selectedNodeId = ref<string | null>(null);
const selectedEdgeId = ref<string | null>(null);
const selectedNode = computed(() => {
    const graphNodes = nodes.value as Array<{ id: string; data: GraphCanvasNodeData }>;
    return graphNodes.find((item) => item.id === selectedNodeId.value) ?? null;
});

const nodeStatuses = ref<Record<string, 'running' | 'completed' | 'failed'>>({});

// Whole-graph test run (GraphDefinitionTestRunService) — runs the
// currently *saved* selectedVersionId for real against a test transcript,
// inside a DB transaction that's always rolled back server-side, so
// nothing here ever touches the real catalog. Independent of the
// node/edge property panels below — its own toggle, own right-side panel.
const showTestPanel = ref(false);
const testTranscript = ref('');
const testRunning = ref(false);
const testError = ref<string | null>(null);
const testResult = ref<GraphTestRunResult | null>(null);

function openTestPanel(): void {
    selectedNodeId.value = null;
    selectedEdgeId.value = null;
    showTestPanel.value = false;
    showHelpPanel.value = false;
    showTestPanel.value = true;
}

// Static "how this screen works" legend — the graph-canvas feedback's ask
// for on-screen orientation, not an AI-generated per-graph explanation
// (that's a separate, deferred feature). Toggled from the header like the
// test-run panel; shares the same right-hand aside slot.
const showHelpPanel = ref(false);

function toggleHelpPanel(): void {
    selectedNodeId.value = null;
    selectedEdgeId.value = null;
    showTestPanel.value = false;
    showHelpPanel.value = !showHelpPanel.value;
}

const SIDE_EFFECT_LABELS: Record<string, string> = {
    read_only: 'Только чтение — ничего не меняет и не сохраняет',
    draft_only: 'Черновик — пишет данные, но не публикует их в каталог',
    publish: 'Публикация — применяет изменения к живому каталогу',
};

const EXECUTION_LABELS: Record<string, string> = {
    sync: 'Синхронно — выполняется как один шаг',
    async_fanout: 'Параллельно — запускает под-агента/ветвление',
};

const FAILURE_POLICY_LABELS: Record<string, string> = {
    fail: 'Ошибка останавливает весь граф',
    best_effort: 'Ошибка не останавливает граф (best effort)',
};

// Publish-time safety check (per user request): a node whose contract
// declares side_effect="publish" (currently only ApplyNode) means running
// this graph for real can write to the live catalog — not the Test run's
// always-rolled-back transaction. Checked against the *saved* version
// (versions.value), never the live `nodes` ref, because Publish always
// operates on selectedVersionId — warning about unsaved canvas edits would
// be misleading either way (warn about something not being published, or
// miss something that is).
function publishSideEffectStepKeys(): string[] {
    const version = versions.value.find((v) => v.id === selectedVersionId.value);
    if (!version) return [];

    return version.nodes
        .filter((n) => palette.value.find((p) => p.node_key === n.node)?.contract?.side_effect === 'publish')
        .map((n) => n.key);
}

async function testRunGraph(): Promise<void> {
    if (!selectedVersionId.value) return;

    testRunning.value = true;
    testError.value = null;
    testResult.value = null;

    try {
        testResult.value = await aiBuilderApi.testRunGraphVersion(graphKey.value, selectedVersionId.value, {
            test_transcript: testTranscript.value,
        });
    } catch (e: unknown) {
        const message = (e as { response?: { data?: { message?: string } } })?.response?.data?.message;
        testError.value = message ?? 'Test run failed.';
    } finally {
        testRunning.value = false;
    }
}

// Node prompt panel — the currently selected node's associated prompt
// (AnalyzeNode/GrammarAgentGraphNode/etc, see DescribesGraphNode::promptKey()),
// loaded lazily whenever selection changes to a node that has one.
const nodePromptLoading = ref(false);
const nodePromptSaving = ref(false);
const nodePromptPublishingId = ref<number | null>(null);
const nodePromptError = ref<string | null>(null);
const nodePromptSavedMessage = ref<string | null>(null);
const nodePromptActiveVersionId = ref<number | null>(null);
const nodePromptVersions = ref<PromptTemplateVersion[]>([]);
const nodePromptSystemTemplate = ref('');
const nodePromptUserTemplate = ref('');
const nodePromptModel = ref('');

async function loadNodePrompt(promptKey: string): Promise<void> {
    nodePromptLoading.value = true;
    nodePromptError.value = null;
    nodePromptSavedMessage.value = null;

    try {
        const detail = await aiBuilderApi.promptTemplate(promptKey);
        nodePromptVersions.value = detail.versions;
        nodePromptActiveVersionId.value = detail.active_version_id;
        const latest = detail.versions.at(-1);
        // No draft saved yet for this node's prompt — pre-fill from the code
        // default instead of a blank textarea (same fix as PromptEditorPage.vue).
        nodePromptSystemTemplate.value = latest?.system_template ?? detail.code_default_system ?? '';
        nodePromptUserTemplate.value = latest?.user_template ?? '';
        nodePromptModel.value = latest?.model ?? '';
    } catch (e: unknown) {
        const status = (e as { response?: { status?: number } })?.response?.status;
        if (status !== 404) {
            nodePromptError.value = 'Could not load this prompt.';
        }
        nodePromptVersions.value = [];
        nodePromptActiveVersionId.value = null;
        nodePromptSystemTemplate.value = '';
        nodePromptUserTemplate.value = '';
        nodePromptModel.value = '';
    }

    nodePromptLoading.value = false;
}

watch(selectedNode, (node) => {
    const promptKey = node?.data?.promptKey ?? null;
    if (promptKey) {
        loadNodePrompt(promptKey);
    }
});

async function saveNodePromptDraft(): Promise<void> {
    const promptKey = selectedNode.value?.data?.promptKey;
    if (!promptKey) return;

    nodePromptSaving.value = true;
    nodePromptError.value = null;
    nodePromptSavedMessage.value = null;

    try {
        const version = await aiBuilderApi.savePromptTemplateDraft(promptKey, {
            name: promptKey,
            system_template: nodePromptSystemTemplate.value,
            user_template: nodePromptUserTemplate.value,
            model: nodePromptModel.value || null,
        });
        nodePromptVersions.value = [...nodePromptVersions.value, version];
        nodePromptSavedMessage.value = `Saved as draft v${version.version}.`;
    } catch {
        nodePromptError.value = 'Could not save this draft.';
    } finally {
        nodePromptSaving.value = false;
    }
}

async function publishNodePromptVersion(versionId: number): Promise<void> {
    const promptKey = selectedNode.value?.data?.promptKey;
    if (!promptKey) return;

    nodePromptPublishingId.value = versionId;
    nodePromptError.value = null;
    nodePromptSavedMessage.value = null;

    try {
        const summary = await aiBuilderApi.publishPromptTemplateVersion(promptKey, versionId);
        nodePromptActiveVersionId.value = summary.active_version_id;
        nodePromptSavedMessage.value = 'Published — live for every call that uses this prompt.';
    } catch {
        nodePromptError.value = 'Could not publish this version.';
    } finally {
        nodePromptPublishingId.value = null;
    }
}

function loadVersionIntoCanvas(version: GraphDefinitionVersion | null): void {
    const nextNodes: Node<GraphCanvasNodeData>[] = [];
    const nextEdges: Edge[] = [];
    for (const instance of version?.nodes ?? []) {
        nextNodes.push(domainNodeToFlowNode(instance, palette.value));
    }
    for (const edge of version?.edges ?? []) {
        nextEdges.push(domainEdgeToFlowEdge(edge));
    }
    nodes.value = nextNodes;
    edges.value = nextEdges;
    selectedVersionId.value = version?.id ?? null;
    selectedNodeId.value = null;
    selectedEdgeId.value = null;
    showTestPanel.value = false;
    showHelpPanel.value = false;
    testResult.value = null;
    testError.value = null;
    markSaved();
}

async function load(): Promise<void> {
    loading.value = true;
    errorMessage.value = null;

    try {
        palette.value = await aiBuilderApi.nodePalette();
    } catch {
        errorMessage.value = 'Could not load the node palette.';
        loading.value = false;
        return;
    }

    try {
        const detail = await aiBuilderApi.graphDefinition(graphKey.value);
        name.value = detail.name;
        description.value = detail.description ?? '';
        versions.value = detail.versions;
        activeVersionId.value = detail.active_version_id;

        const toShow = detail.versions.find((v) => v.id === detail.active_version_id) ?? detail.versions.at(-1) ?? null;
        loadVersionIntoCanvas(toShow);
    } catch (e: unknown) {
        // 404 — no draft/override exists yet for this graph_name; start
        // from an empty canvas rather than treating it as a load failure.
        const status = (e as { response?: { status?: number } })?.response?.status;
        if (status !== 404) {
            errorMessage.value = 'Could not load this graph definition.';
        }
        name.value = graphKey.value;
        loadVersionIntoCanvas(null);
    }

    loading.value = false;
}

let stopStream: (() => void) | null = null;

onMounted(() => {
    load();

    if (runId.value) {
        stopStream = streamGraphRunStatus(runId.value, (event) => {
            if (!event.node_key) return;

            if (event.status === 'running' || event.status === 'paused') {
                nodeStatuses.value = { [event.node_key]: 'running' };
            } else if (event.status === 'completed') {
                nodeStatuses.value = { ...nodeStatuses.value, [event.node_key]: 'completed' };
            } else if (event.status === 'failed') {
                nodeStatuses.value = { ...nodeStatuses.value, [event.node_key]: 'failed' };
            }
        });
    }
});

onBeforeUnmount(() => {
    stopStream?.();
});

function onPaletteDragStart(event: DragEvent, entry: NodePaletteEntry): void {
    event.dataTransfer?.setData('application/json', JSON.stringify(entry));
    if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move';
}

function onDrop(event: DragEvent): void {
    const raw = event.dataTransfer?.getData('application/json');
    if (!raw) return;
    const entry = JSON.parse(raw) as NodePaletteEntry;

    const stepKey = window.prompt(`Step key for this "${entry.label}" node (unique within this graph):`, entry.node_key);
    if (!stepKey || stepKey.trim() === '') return;
    if (nodes.value.some((n) => n.id === stepKey)) {
        window.alert(`Step key "${stepKey}" is already used in this graph.`);
        return;
    }

    const position = screenToFlowCoordinate({ x: event.clientX, y: event.clientY });
    addNodes([domainNodeToFlowNode({ key: stepKey.trim(), node: entry.node_key, x: position.x, y: position.y }, palette.value)]);
}

function onConnect(connection: { source: string; target: string }): void {
    addEdges([{ id: `${connection.source}->${connection.target}`, source: connection.source, target: connection.target }]);
}

function onNodeClick({ node }: NodeMouseEvent): void {
    selectedNodeId.value = node.id;
    selectedEdgeId.value = null;
    showTestPanel.value = false;
    showHelpPanel.value = false;
}

function onEdgeClick({ edge }: EdgeMouseEvent): void {
    selectedEdgeId.value = edge.id;
    selectedNodeId.value = null;
    showTestPanel.value = false;
    showHelpPanel.value = false;
}

function deselect(): void {
    selectedNodeId.value = null;
    selectedEdgeId.value = null;
    showTestPanel.value = false;
    showHelpPanel.value = false;
}

function deleteSelectedNode(): void {
    if (!selectedNodeId.value) return;
    removeNodes([selectedNodeId.value]);
    const edgeIds: string[] = [];
    for (const edge of edges.value as Array<{ id: string; source: string; target: string }>) {
        if (edge.source === selectedNodeId.value || edge.target === selectedNodeId.value) edgeIds.push(edge.id);
    }
    removeEdges(edgeIds);
    deselect();
}

function deleteSelectedEdge(): void {
    if (!selectedEdgeId.value) return;
    removeEdges([selectedEdgeId.value]);
    deselect();
}

async function saveDraft(): Promise<void> {
    saving.value = true;
    errorMessage.value = null;
    savedMessage.value = null;

    try {
        const domainNodes = [] as GraphDefinitionVersion['nodes'];
        const domainEdges = [] as GraphDefinitionVersion['edges'];
        for (const node of nodes.value as Array<Node<GraphCanvasNodeData>>) {
            domainNodes.push(flowNodeToDomainNode(node));
        }
        for (const edge of edges.value as Array<Edge>) {
            domainEdges.push(flowEdgeToDomainEdge(edge));
        }
        const version = await aiBuilderApi.saveGraphDefinitionDraft(graphKey.value, {
            name: name.value || graphKey.value,
            description: description.value || null,
            nodes: domainNodes,
            edges: domainEdges,
        });
        versions.value = [...versions.value, version];
        selectedVersionId.value = version.id;
        savedMessage.value = `Saved as draft v${version.version}.`;
        markSaved();
    } catch {
        errorMessage.value = 'Could not save this draft.';
    } finally {
        saving.value = false;
    }
}

async function publish(): Promise<void> {
    if (!selectedVersionId.value) return;

    const publishNodes = publishSideEffectStepKeys();
    if (publishNodes.length > 0) {
        const version = versions.value.find((v) => v.id === selectedVersionId.value);
        const confirmed = window.confirm(
            `Внимание: в этой версии (v${version?.version ?? '?'}) есть узел(-ы) с реальным побочным эффектом ` +
                `публикации в живой каталог: ${publishNodes.join(', ')}.\n\n` +
                `Публикация версии сама по себе ничего не применяет к каталогу — но при следующем реальном ` +
                `(не тестовом) запуске графа эти узлы применят изменения к живым данным.\n\n` +
                `Опубликовать эту версию графа?`
        );
        if (!confirmed) return;
    }

    publishing.value = true;
    errorMessage.value = null;
    savedMessage.value = null;

    try {
        const summary = await aiBuilderApi.publishGraphDefinitionVersion(graphKey.value, selectedVersionId.value);
        activeVersionId.value = summary.active_version_id;
        savedMessage.value = 'Published — this version now runs live.';
    } catch (e: unknown) {
        const message = (e as { response?: { data?: { message?: string } } })?.response?.data?.message;
        errorMessage.value = message ?? 'This version could not be published — check the wiring for unregistered node types or dangling edges.';
    } finally {
        publishing.value = false;
    }
}
</script>

<template>
    <div class="flex h-screen flex-col bg-background text-fg">
        <AiBuilderTopBar :crumb="graphKey" />

        <header class="flex shrink-0 items-center justify-between gap-4 border-b border-border bg-card px-4 py-3">
            <div class="flex min-w-0 items-center gap-3">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <h1 class="truncate font-mono text-sm font-semibold">{{ graphKey }}</h1>
                        <UiBadge :tone="activeVersionId && activeVersionId === selectedVersionId ? 'success' : 'neutral'">
                            {{ activeVersionId && activeVersionId === selectedVersionId ? 'Published' : 'Draft' }}
                        </UiBadge>
                    </div>
                    <p class="text-xs text-muted-foreground">Graph builder</p>
                </div>
                <UiInput v-model="name" placeholder="Name" class="!h-8 w-48" />
            </div>

            <div class="flex items-center gap-3">
                <select
                    v-if="versions.length > 0"
                    v-model.number="selectedVersionId"
                    class="h-8 rounded-md border border-input bg-background px-2 text-xs"
                    @change="loadVersionIntoCanvas(versions.find((v) => v.id === selectedVersionId) ?? null)"
                >
                    <option v-for="v in versions" :key="v.id" :value="v.id">
                        v{{ v.version }}{{ v.id === activeVersionId ? ' (published)' : '' }}
                    </option>
                </select>

                <span v-if="savedMessage" class="text-xs text-success-fg">{{ savedMessage }}</span>
                <span v-if="errorMessage" class="text-xs text-danger">{{ errorMessage }}</span>

                <UiButton :variant="showHelpPanel ? 'primary' : 'secondary'" size="sm" @click="toggleHelpPanel">
                    ? Как это читать
                </UiButton>
                <UiButton variant="secondary" size="sm" :disabled="!selectedVersionId" @click="openTestPanel">
                    Test run
                </UiButton>
                <DraftSaveBar :saving="saving" :dirty="isDirty" :saved-message="savedMessage" :error-message="errorMessage" @save="saveDraft" />
                <UiButton variant="primary" size="sm" :disabled="!selectedVersionId || publishing" @click="publish">
                    {{ publishing ? 'Publishing…' : 'Publish' }}
                </UiButton>
            </div>
        </header>

        <div v-if="loading" class="flex flex-1 items-center justify-center text-sm text-muted-foreground">Loading…</div>

        <div v-else class="flex flex-1 overflow-hidden">
            <aside class="w-60 shrink-0 overflow-y-auto border-r border-border bg-card p-3">
                <h2 class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                    Node palette
                </h2>
                <p class="mb-3 text-[11px] leading-5 text-muted-foreground">
                    Drag a node onto the canvas, then connect it to wire up the sequence.
                </p>
                <div
                    v-for="entry in palette"
                    :key="entry.node_key"
                    draggable="true"
                    class="mb-2 cursor-grab rounded-lg border border-border bg-background p-2 active:cursor-grabbing"
                    @dragstart="onPaletteDragStart($event, entry)"
                >
                    <div class="text-xs font-medium">{{ entry.label }}</div>
                    <div class="mt-0.5 text-[11px] leading-4 text-muted-foreground">{{ entry.description }}</div>
                </div>
            </aside>

            <div class="relative min-w-0 flex-1" @drop.prevent="onDrop" @dragover.prevent>
                <VueFlow v-model:nodes="nodes" v-model:edges="edges" fit-view-on-init :default-edge-options="{ type: 'smoothstep' }" @connect="onConnect" @node-click="onNodeClick" @edge-click="onEdgeClick" @pane-click="deselect">
                    <template #node-graphStep="nodeProps">
                        <GraphCanvasNode v-bind="nodeProps" :status="nodeStatuses[nodeProps.id] ?? null" />
                    </template>
                    <Background :gap="18" pattern-color="var(--spa-border)" />
                    <Controls />
                </VueFlow>

                <div v-if="nodes.length === 0" class="pointer-events-none absolute inset-0 flex items-center justify-center">
                    <p class="rounded-lg border border-dashed border-border bg-card/80 px-4 py-3 text-sm text-muted-foreground">
                        Drag a node from the palette to start wiring this graph.
                    </p>
                </div>
            </div>

            <aside v-if="selectedNode" class="w-96 shrink-0 overflow-y-auto border-l border-border bg-card p-4">
                <h2 class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Node</h2>
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-[11px] text-muted-foreground">Step key</dt>
                        <dd class="font-mono">{{ selectedNode.data!.stepKey }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] text-muted-foreground">Node type</dt>
                        <dd>{{ selectedNode.data!.label }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] text-muted-foreground">Description</dt>
                        <dd class="leading-5 text-muted-foreground">{{ selectedNode.data!.description }}</dd>
                    </div>
                </dl>

                <div v-if="selectedNode.data!.contract" class="mt-4 rounded-lg border border-border bg-background p-3">
                    <h3 class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Контракт узла</h3>

                    <UiBadge
                        :tone="selectedNode.data!.contract.side_effect === 'publish' ? 'danger' : selectedNode.data!.contract.side_effect === 'draft_only' ? 'warning' : 'neutral'"
                    >
                        {{ SIDE_EFFECT_LABELS[selectedNode.data!.contract.side_effect] }}
                    </UiBadge>

                    <dl class="mt-2 space-y-2 text-[11px] leading-4">
                        <div v-if="selectedNode.data!.contract.reads.length > 0">
                            <dt class="text-muted-foreground">Читает из состояния</dt>
                            <dd class="font-mono">{{ selectedNode.data!.contract.reads.join(', ') }}</dd>
                        </div>
                        <div v-if="selectedNode.data!.contract.writes.length > 0">
                            <dt class="text-muted-foreground">Пишет в состояние</dt>
                            <dd class="font-mono">{{ selectedNode.data!.contract.writes.join(', ') }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Выполнение</dt>
                            <dd>{{ EXECUTION_LABELS[selectedNode.data!.contract.execution] }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">При ошибке</dt>
                            <dd>{{ FAILURE_POLICY_LABELS[selectedNode.data!.contract.failure_policy] }}</dd>
                        </div>
                        <div v-if="selectedNode.data!.contract.can_pause">
                            <dd class="text-warning-fg">⏸ Может приостановить граф и ждать человека (checkpoint)</dd>
                        </div>
                        <div v-if="selectedNode.data!.contract.queues_jobs">
                            <dd class="text-muted-foreground">⚙ Ставит фоновую задачу в очередь — переживает откат транзакции теста</dd>
                        </div>
                    </dl>
                </div>

                <UiButton variant="danger" size="sm" class="mt-4 w-full" @click="deleteSelectedNode">Delete node</UiButton>

                <div v-if="selectedNode.data!.promptKey" class="mt-6 border-t border-border pt-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Prompt</h3>
                        <RouterLink
                            :to="{ name: 'admin.aiBuilder.prompt', params: { key: selectedNode.data!.promptKey } }"
                            class="text-[11px] text-primary hover:underline"
                        >
                            Open full editor
                        </RouterLink>
                    </div>
                    <p class="mt-1 font-mono text-[11px] text-muted-foreground">{{ selectedNode.data!.promptKey }}</p>
                    <p v-if="selectedNode.data!.promptKey.startsWith('agent_')" class="mt-1 text-[11px] leading-4 text-warning-fg">
                        This is the specialist agent's own system prompt — shared with every graph/handoff that uses this agent, not scoped to this one step.
                    </p>

                    <div v-if="nodePromptLoading" class="mt-3 text-xs text-muted-foreground">Loading…</div>
                    <template v-else>
                        <UiBadge class="mt-2" :tone="nodePromptActiveVersionId ? 'success' : 'neutral'">
                            {{ nodePromptActiveVersionId ? 'Published' : 'No published version — code default in use' }}
                        </UiBadge>

                        <label class="mb-1 mt-3 block text-[11px] font-medium text-muted-foreground">System template</label>
                        <textarea
                            v-model="nodePromptSystemTemplate"
                            rows="6"
                            class="w-full rounded-md border border-input bg-background px-2 py-1.5 font-mono text-[11px] leading-4 text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        />

                        <label class="mb-1 mt-2 block text-[11px] font-medium text-muted-foreground">User template</label>
                        <textarea
                            v-model="nodePromptUserTemplate"
                            rows="3"
                            class="w-full rounded-md border border-input bg-background px-2 py-1.5 font-mono text-[11px] leading-4 text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        />

                        <div class="mt-2 flex items-center gap-2">
                            <UiButton variant="secondary" size="sm" :disabled="nodePromptSaving" @click="saveNodePromptDraft">
                                {{ nodePromptSaving ? 'Saving…' : 'Save draft' }}
                            </UiButton>
                            <button
                                v-if="nodePromptVersions.length > 0 && nodePromptVersions.at(-1)!.id !== nodePromptActiveVersionId"
                                type="button"
                                class="text-[11px] text-primary hover:underline disabled:opacity-50"
                                :disabled="nodePromptPublishingId === nodePromptVersions.at(-1)!.id"
                                @click="publishNodePromptVersion(nodePromptVersions.at(-1)!.id)"
                            >
                                Publish latest draft
                            </button>
                        </div>
                        <span v-if="nodePromptSavedMessage" class="mt-1 block text-[11px] text-success-fg">{{ nodePromptSavedMessage }}</span>
                        <span v-if="nodePromptError" class="mt-1 block text-[11px] text-danger">{{ nodePromptError }}</span>
                    </template>
                </div>
            </aside>

            <aside v-else-if="selectedEdgeId" class="w-72 shrink-0 border-l border-border bg-card p-4">
                <h2 class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Connection</h2>
                <p class="font-mono text-xs text-muted-foreground">{{ selectedEdgeId }}</p>
                <UiButton variant="danger" size="sm" class="mt-4 w-full" @click="deleteSelectedEdge">Delete connection</UiButton>
            </aside>

            <aside v-else-if="showTestPanel" class="w-96 shrink-0 overflow-y-auto border-l border-border bg-card p-4">
                <h2 class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Test run this graph</h2>
                <p class="mt-1 text-[11px] leading-5 text-muted-foreground">
                    Runs the saved version <span class="font-mono">v{{ versions.find((v) => v.id === selectedVersionId)?.version }}</span>
                    for real against the text below — a real AI call happens, but nothing is saved: it runs inside a
                    database transaction that is always rolled back afterward, and never publishes to the live catalog
                    (see <span class="font-mono">ApplyNode</span>'s test-mode guard). Unsaved canvas edits are not
                    included — save a draft first if you changed anything.
                </p>

                <label class="mb-1 mt-3 block text-[11px] font-medium text-muted-foreground">Test transcript</label>
                <textarea
                    v-model="testTranscript"
                    rows="6"
                    placeholder="I gave up smoking last year, but yesterday I ran into my old friend and we caught up for hours."
                    class="w-full rounded-md border border-input bg-background px-2 py-1.5 text-xs leading-5 text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                />

                <UiButton variant="secondary" size="sm" class="mt-3" :disabled="testRunning || !testTranscript.trim()" @click="testRunGraph">
                    {{ testRunning ? 'Running…' : 'Test run' }}
                </UiButton>
                <span v-if="testError" class="ml-3 text-xs text-danger">{{ testError }}</span>

                <div v-if="testResult" class="mt-4 space-y-3">
                    <div class="flex items-center gap-2">
                        <UiBadge :tone="testResult.status === 'failed' ? 'danger' : testResult.status === 'paused' ? 'warning' : 'success'">
                            {{ testResult.status }}
                        </UiBadge>
                        <span v-if="testResult.current_node" class="font-mono text-[11px] text-muted-foreground">at {{ testResult.current_node }}</span>
                    </div>
                    <p v-if="testResult.pause_reason" class="text-[11px] text-muted-foreground">Paused: {{ testResult.pause_reason }}</p>
                    <p v-if="testResult.failure_reason" class="text-[11px] text-danger">{{ testResult.failure_reason }}</p>

                    <div v-if="testResult.lexeme_candidates.length > 0">
                        <div class="text-[11px] font-medium text-muted-foreground">Lexeme candidates ({{ testResult.lexeme_candidates.length }})</div>
                        <ul class="mt-1 space-y-1">
                            <li v-for="(c, i) in testResult.lexeme_candidates" :key="i" class="rounded-md bg-muted/40 p-2 text-[11px] leading-4">
                                <span class="font-medium">{{ c.text }}</span>
                                <span v-if="c.translation" class="text-muted-foreground"> — {{ c.translation }}</span>
                            </li>
                        </ul>
                    </div>

                    <div v-if="testResult.grammar_candidates.length > 0">
                        <div class="text-[11px] font-medium text-muted-foreground">Grammar candidates ({{ testResult.grammar_candidates.length }})</div>
                        <ul class="mt-1 space-y-1">
                            <li v-for="(c, i) in testResult.grammar_candidates" :key="i" class="rounded-md bg-muted/40 p-2 text-[11px] leading-4">
                                <span class="font-medium">{{ c.title }}</span>
                            </li>
                        </ul>
                    </div>

                    <p v-if="testResult.lexeme_candidates.length === 0 && testResult.grammar_candidates.length === 0" class="text-[11px] text-muted-foreground">
                        No candidates yet at this point in the run (expected if the run stopped before the analyze step).
                    </p>
                </div>
            </aside>

            <aside v-else-if="showHelpPanel" class="w-96 shrink-0 overflow-y-auto border-l border-border bg-card p-4">
                <h2 class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Как читать этот экран</h2>

                <div class="mt-3 space-y-4 text-[11px] leading-5 text-muted-foreground">
                    <div>
                        <h3 class="font-medium text-foreground">Узлы (карточки)</h3>
                        <p class="mt-1">Каждая карточка — это один готовый, заранее написанный шаг (PHP-класс). Здесь нельзя написать новый тип шага
                            или произвольный код — только выбрать из уже существующих (палитра слева) и соединить их между собой.</p>
                        <ul class="mt-1 space-y-1">
                            <li class="flex items-center gap-2"><span class="h-3 w-1 shrink-0 rounded-sm bg-danger"></span>красная полоса слева — публикует изменения в живой каталог</li>
                            <li class="flex items-center gap-2"><span class="h-3 w-1 shrink-0 rounded-sm bg-warning"></span>жёлтая полоса — может приостановить граф и ждать решения человека</li>
                            <li class="flex items-center gap-2"><span class="h-3 w-1 shrink-0 rounded-sm bg-primary"></span>фиолетовая полоса — делает вызов LLM/агента</li>
                            <li class="flex items-center gap-2"><span class="h-3 w-1 shrink-0 rounded-sm bg-border"></span>серая полоса — обычная логика без побочных эффектов</li>
                            <li>значок 💬 — у узла есть редактируемый промпт</li>
                        </ul>
                    </div>

                    <div>
                        <h3 class="font-medium text-foreground">Связи (стрелки)</h3>
                        <p class="mt-1">Стрелка задаёт порядок выполнения: после шага A выполняется шаг B. Если у узла несколько исходящих
                            стрелок, PHP-код узла сам решает, по какой из них пойти дальше (например, куда вернуться после проверки человеком) —
                            стрелки только показывают возможные пути, а не гарантируют, что граф пойдёт именно так при каждом запуске.</p>
                    </div>

                    <div>
                        <h3 class="font-medium text-foreground">Контракт узла</h3>
                        <p class="mt-1">Кликните по узлу, чтобы увидеть его контракт справа: что он читает/пишет в общем состоянии графа,
                            синхронный он или запускает параллельного агента, останавливает ли граф при ошибке, и есть ли у него побочный эффект.</p>
                    </div>

                    <div>
                        <h3 class="font-medium text-foreground">Save draft vs Publish</h3>
                        <p class="mt-1"><b class="text-foreground">Save draft</b> сохраняет текущую схему как новую версию — ничего не меняет
                            в реальной работе приложения, можно сохранять сколько угодно раз.</p>
                        <p class="mt-1"><b class="text-foreground">Publish</b> делает выбранную версию активной — именно она будет использоваться
                            при следующих реальных запусках этого графа. Если в версии есть узел с публикацией в каталог, при публикации появится
                            отдельное предупреждение.</p>
                    </div>

                    <div>
                        <h3 class="font-medium text-foreground">Test run</h3>
                        <p class="mt-1">Прогоняет сохранённую версию по-настоящему (реальный вызов ИИ), но в транзакции, которая всегда откатывается —
                            результат нигде не сохраняется и в каталог не публикуется, даже если в графе есть публикующий узел.</p>
                    </div>

                    <div>
                        <h3 class="font-medium text-foreground">Подсветка во время реального запуска</h3>
                        <p class="mt-1">Если вы открыли эту страницу по ссылке на конкретный запуск, узлы подсвечиваются по статусу:
                            <span class="text-primary">выполняется</span>, <span class="text-success-fg">завершён</span>,
                            <span class="text-danger">ошибка</span> — обновляется в реальном времени.</p>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</template>
