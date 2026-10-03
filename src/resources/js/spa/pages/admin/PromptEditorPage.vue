<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import UiBadge from '../../shared/ui/UiBadge.vue';
import UiButton from '../../shared/ui/UiButton.vue';
import { aiBuilderApi } from '../../domains/ai-builder/api';
import { useDraftState } from '../../domains/ai-builder/useDraftState';
import DraftSaveBar from '../../domains/ai-builder/DraftSaveBar.vue';
import PromptMetadataFields from '../../domains/ai-builder/PromptMetadataFields.vue';
import AiBuilderTopBar from '../../domains/ai-builder/AiBuilderTopBar.vue';
import type {
    AgentToolSummary,
    PromptChatMessage,
    PromptTemplateSummary,
    PromptTemplateVersion,
    TestRunResult,
} from '../../domains/ai-builder/types';

const route = useRoute();
const router = useRouter();
const promptKey = computed(() => String(route.params.key));

const list = ref<PromptTemplateSummary[]>([]);
const versions = ref<PromptTemplateVersion[]>([]);
const activeVersionId = ref<number | null>(null);
const kind = ref<'agent' | 'prompt'>('prompt');
const tools = ref<AgentToolSummary[]>([]);
const usingCodeDefault = ref(false);

const name = ref('');
const description = ref('');
const systemTemplate = ref('');
const userTemplate = ref('');
const model = ref('');

const loading = ref(true);
const saving = ref(false);
const publishingId = ref<number | null>(null);
const errorMessage = ref<string | null>(null);
const savedMessage = ref<string | null>(null);

const testVariablesJson = ref('{}');
const testRunning = ref(false);
const testError = ref<string | null>(null);
const testResult = ref<TestRunResult | null>(null);
const { isDirty, markSaved } = useDraftState(() => ({
    name: name.value,
    description: description.value,
    system: systemTemplate.value,
    user: userTemplate.value,
    model: model.value,
}));

const SIDE_EFFECT_LABEL: Record<AgentToolSummary['side_effect'], string> = {
    read_only: 'только читает',
    draft_only: 'пишет черновик',
    publish: 'публикует напрямую',
};

const SIDE_EFFECT_TONE: Record<AgentToolSummary['side_effect'], 'neutral' | 'warning' | 'danger'> = {
    read_only: 'neutral',
    draft_only: 'warning',
    publish: 'danger',
};

// Right-side panel tabs — chat is a scratchpad (see PromptImprovementAssistantService's
// docblock): conversation lives only here, never persisted, reset on prompt switch.
const rightTab = ref<'versions' | 'chat'>('versions');
const chatMessages = ref<PromptChatMessage[]>([]);
const chatInput = ref('');
const chatSending = ref(false);
const chatError = ref<string | null>(null);
const chatProposal = ref<{ system: string | null; user: string | null } | null>(null);

async function loadList(): Promise<void> {
    list.value = await aiBuilderApi.promptTemplates();
}

async function loadEditor(): Promise<void> {
    loading.value = true;
    errorMessage.value = null;
    savedMessage.value = null;
    rightTab.value = 'versions';
    chatMessages.value = [];
    chatProposal.value = null;
    chatError.value = null;

    try {
        const detail = await aiBuilderApi.promptTemplate(promptKey.value);
        name.value = detail.name;
        description.value = detail.description ?? '';
        versions.value = detail.versions;
        activeVersionId.value = detail.active_version_id;
        kind.value = detail.kind;
        tools.value = detail.tools;

        const latest = detail.versions.at(-1);
        if (latest) {
            systemTemplate.value = latest.system_template;
            userTemplate.value = latest.user_template;
            model.value = latest.model ?? '';
            usingCodeDefault.value = false;
        } else {
            // No draft has ever been saved for this key — pre-fill from the
            // code default (an agent's static system prompt) instead of an
            // empty textarea, so there's something to read/edit rather than
            // silence. Plain (non-agent) prompts genuinely have no static
            // default to show — see PromptCatalogService's docblock.
            systemTemplate.value = detail.code_default_system ?? '';
            userTemplate.value = '';
            model.value = '';
            usingCodeDefault.value = detail.code_default_system !== null;
        }
    } catch (e: unknown) {
        const message = (e as { response?: { data?: { message?: string } } })?.response?.data?.message;
        errorMessage.value = message ?? 'Could not load this prompt template.';
        name.value = promptKey.value;
        description.value = '';
        versions.value = [];
        activeVersionId.value = null;
        kind.value = 'prompt';
        tools.value = [];
        systemTemplate.value = '';
        userTemplate.value = '';
        model.value = '';
        usingCodeDefault.value = false;
    }

    loading.value = false;
}

function loadVersionIntoEditor(version: PromptTemplateVersion): void {
    systemTemplate.value = version.system_template;
    userTemplate.value = version.user_template;
    model.value = version.model ?? '';
    usingCodeDefault.value = false;
}

onMounted(() => {
    loadList();
    loadEditor();
});

watch(promptKey, () => {
    loadEditor();
});

async function saveDraft(): Promise<void> {
    saving.value = true;
    errorMessage.value = null;
    savedMessage.value = null;

    try {
        const version = await aiBuilderApi.savePromptTemplateDraft(promptKey.value, {
            name: name.value || promptKey.value,
            description: description.value || null,
            system_template: systemTemplate.value,
            user_template: String(userTemplate.value ?? ''),
            model: model.value || null,
        });
        versions.value = [...versions.value, version];
        savedMessage.value = `Saved as draft v${version.version}.`;
        usingCodeDefault.value = false;
        markSaved();
        loadList();
    } catch {
        errorMessage.value = 'Could not save this draft — check that the system/user template fields are filled in.';
    } finally {
        saving.value = false;
    }
}

async function publishVersion(versionId: number): Promise<void> {
    publishingId.value = versionId;
    errorMessage.value = null;
    savedMessage.value = null;

    try {
        const summary = await aiBuilderApi.publishPromptTemplateVersion(promptKey.value, versionId);
        activeVersionId.value = summary.active_version_id;
        savedMessage.value = 'Published — this version is now live.';
        loadList();
    } catch {
        errorMessage.value = 'Could not publish this version.';
    } finally {
        publishingId.value = null;
    }
}

function goTo(key: string): void {
    router.push({ name: 'admin.aiBuilder.prompt', params: { key } });
}

async function testRun(): Promise<void> {
    testError.value = null;
    testResult.value = null;

    let variables: Record<string, string> = {};
    if (testVariablesJson.value.trim() !== '') {
        try {
            variables = JSON.parse(testVariablesJson.value);
        } catch {
            testError.value = 'Test variables must be valid JSON, e.g. {"lexeme": "run", "language": "English"}.';
            return;
        }
    }

    testRunning.value = true;
    try {
        testResult.value = await aiBuilderApi.testRunPrompt(promptKey.value, {
            system_template: systemTemplate.value,
            user_template: userTemplate.value,
            variables,
            model: model.value || null,
        });
    } catch (e: unknown) {
        const message = (e as { response?: { data?: { message?: string } } })?.response?.data?.message;
        testError.value = message ?? 'Test run failed.';
    } finally {
        testRunning.value = false;
    }
}

async function sendChatMessage(): Promise<void> {
    const message = chatInput.value.trim();
    if (!message || chatSending.value) return;

    const historyBeforeThisTurn = [...chatMessages.value];
    chatMessages.value.push({ role: 'user', content: message });
    chatInput.value = '';
    chatError.value = null;
    chatSending.value = true;

    try {
        const result = await aiBuilderApi.chatAboutPrompt(promptKey.value, {
            system_template: systemTemplate.value,
            user_template: userTemplate.value,
            history: historyBeforeThisTurn,
            message,
        });
        chatMessages.value.push({ role: 'assistant', content: result.reply });
        chatProposal.value =
            result.proposed_system_template !== null || result.proposed_user_template !== null
                ? { system: result.proposed_system_template, user: result.proposed_user_template }
                : null;
    } catch (e: unknown) {
        const responseMessage = (e as { response?: { data?: { message?: string } } })?.response?.data?.message;
        chatError.value = responseMessage ?? 'Не удалось получить ответ ассистента.';
    } finally {
        chatSending.value = false;
    }
}

function applyChatProposal(): void {
    if (!chatProposal.value) return;
    if (chatProposal.value.system !== null) systemTemplate.value = chatProposal.value.system;
    if (chatProposal.value.user !== null) userTemplate.value = chatProposal.value.user;
    usingCodeDefault.value = false;
    chatProposal.value = null;
}
</script>

<template>
    <div class="flex h-screen flex-col bg-background text-fg">
        <AiBuilderTopBar :crumb="promptKey" />

        <div class="flex flex-1 overflow-hidden">
        <aside class="w-64 shrink-0 overflow-y-auto border-r border-border bg-card p-3">
            <h2 class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Prompt templates</h2>
            <button
                v-for="item in list"
                :key="item.key"
                type="button"
                class="mb-1 flex w-full items-center justify-between gap-2 rounded-lg px-2 py-1.5 text-left text-xs hover:bg-accent"
                :class="item.key === promptKey ? 'bg-accent' : ''"
                @click="goTo(item.key)"
            >
                <span class="truncate font-mono">{{ item.key }}</span>
                <UiBadge v-if="item.active_version_id" tone="success">live</UiBadge>
                <UiBadge v-else tone="neutral">draft</UiBadge>
            </button>
            <p v-if="list.length === 0" class="text-[11px] text-muted-foreground">No prompt templates saved yet.</p>
        </aside>

        <div v-if="loading" class="flex flex-1 items-center justify-center text-sm text-muted-foreground">Loading…</div>

        <div v-else class="flex flex-1 overflow-hidden">
            <main class="min-w-0 flex-1 overflow-y-auto p-6">
                <div class="mx-auto max-w-3xl">
                    <div class="flex items-center gap-2">
                        <h1 class="font-mono text-lg font-semibold">{{ promptKey }}</h1>
                        <UiBadge :tone="activeVersionId ? 'success' : versions.length > 0 ? 'warning' : 'neutral'">
                            {{ activeVersionId ? 'Published' : versions.length > 0 ? 'Draft saved — not published' : 'Никогда не сохранялся' }}
                        </UiBadge>
                        <UiBadge v-if="kind === 'agent'" tone="primary">агентный промпт</UiBadge>
                    </div>

                    <p v-if="usingCodeDefault" class="mt-2 text-[11px] leading-4 text-muted-foreground">
                        Показан текст по умолчанию из кода — черновик ещё не сохранён. Отредактируйте и нажмите
                        <span class="font-medium text-foreground">Save draft</span>, чтобы сохранить как v1.
                    </p>
                    <p v-else-if="!systemTemplate && kind === 'prompt'" class="mt-2 text-[11px] leading-4 text-muted-foreground">
                        Этот промпт собирается в коде из динамических данных при каждом вызове — статичного текста по умолчанию
                        нет. Впишите текст ниже, чтобы задать переопределение.
                    </p>

                    <div v-if="tools.length > 0" class="mt-4">
                        <div class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                            Доступные инструменты ({{ tools.length }}) — модель сама решает, какой вызвать и когда
                        </div>
                        <div class="mt-1.5 flex flex-wrap gap-1.5">
                            <span
                                v-for="tool in tools"
                                :key="tool.name"
                                :title="tool.description"
                                class="inline-flex cursor-help items-center gap-1 rounded-md border border-border bg-background px-2 py-1 text-[11px]"
                            >
                                <span class="font-mono">{{ tool.name }}</span>
                                <UiBadge :tone="SIDE_EFFECT_TONE[tool.side_effect]">{{ SIDE_EFFECT_LABEL[tool.side_effect] }}</UiBadge>
                            </span>
                        </div>
                    </div>

                    <PromptMetadataFields v-model:name="name" v-model:description="description" v-model:model="model" class="mt-4" />

                    <div class="mt-4">
                        <label class="mb-1 block text-xs font-medium text-muted-foreground">System template</label>
                        <textarea
                            v-model="systemTemplate"
                            rows="6"
                            placeholder="You are a language tutor. Use {{variable}} placeholders for anything the caller fills in at request time."
                            class="w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-xs leading-5 text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        />
                    </div>

                    <div v-if="kind !== 'agent'" class="mt-4">
                        <label class="mb-1 block text-xs font-medium text-muted-foreground">User template</label>
                        <textarea
                            v-model="userTemplate"
                            rows="4"
                            placeholder="Explain {{lexeme}} in {{language}}."
                            class="w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-xs leading-5 text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        />
                    </div>
                    <p v-else class="mt-3 text-[11px] leading-4 text-muted-foreground">
                        У агентных промптов нет отдельного user-шаблона — это системный промпт для цикла вызова
                        инструментов, реальные сообщения приходят из самой переписки с пользователем.
                    </p>

                    <DraftSaveBar class="mt-4" :saving="saving" :dirty="isDirty" :saved-message="savedMessage" :error-message="errorMessage" @save="saveDraft" />

                    <div class="mt-8 rounded-lg border border-border p-4">
                        <h2 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Test run</h2>
                        <p class="mt-1 text-[11px] text-muted-foreground">
                            Renders the templates above with these variables and makes a real call — nothing is saved.
                        </p>

                        <label class="mb-1 mt-3 block text-xs font-medium text-muted-foreground">Variables (JSON)</label>
                        <textarea
                            v-model="testVariablesJson"
                            rows="2"
                            placeholder='{"lexeme": "run", "language": "English"}'
                            class="w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-xs leading-5 text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        />

                        <UiButton variant="secondary" size="sm" class="mt-3" :disabled="testRunning" @click="testRun">
                            {{ testRunning ? 'Running…' : 'Test run' }}
                        </UiButton>
                        <span v-if="testError" class="ml-3 text-xs text-danger">{{ testError }}</span>

                        <div v-if="testResult" class="mt-3 space-y-2">
                            <div>
                                <div class="text-[11px] font-medium text-muted-foreground">Rendered system prompt</div>
                                <p class="whitespace-pre-wrap rounded-md bg-muted/40 p-2 font-mono text-[11px] leading-4">{{ testResult.rendered_system }}</p>
                            </div>
                            <div>
                                <div class="text-[11px] font-medium text-muted-foreground">Rendered user prompt</div>
                                <p class="whitespace-pre-wrap rounded-md bg-muted/40 p-2 font-mono text-[11px] leading-4">{{ testResult.rendered_user }}</p>
                            </div>
                            <div>
                                <div class="text-[11px] font-medium text-muted-foreground">Response</div>
                                <p class="whitespace-pre-wrap rounded-md border border-success bg-success-bg p-2 text-xs leading-5">{{ testResult.response }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </main>

            <aside class="flex w-80 shrink-0 flex-col overflow-hidden border-l border-border bg-card">
                <div class="flex shrink-0 border-b border-border">
                    <button
                        type="button"
                        class="flex-1 px-3 py-2 text-[11px] font-semibold uppercase tracking-wide transition-colors"
                        :class="rightTab === 'versions' ? 'border-b-2 border-primary text-fg' : 'text-muted-foreground hover:text-fg'"
                        @click="rightTab = 'versions'"
                    >
                        Version history
                    </button>
                    <button
                        type="button"
                        class="flex-1 px-3 py-2 text-[11px] font-semibold uppercase tracking-wide transition-colors"
                        :class="rightTab === 'chat' ? 'border-b-2 border-primary text-fg' : 'text-muted-foreground hover:text-fg'"
                        @click="rightTab = 'chat'"
                    >
                        AI-ассистент
                    </button>
                </div>

                <div v-if="rightTab === 'versions'" class="flex-1 overflow-y-auto p-4">
                    <div v-if="versions.length === 0" class="text-xs text-muted-foreground">No versions saved yet — save a draft to create v1.</div>
                    <div v-for="version in [...versions].reverse()" :key="version.id" class="mb-2 rounded-lg border border-border p-2.5">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium">v{{ version.version }}</span>
                            <UiBadge v-if="version.id === activeVersionId" tone="success">published</UiBadge>
                        </div>
                        <p class="mt-1 line-clamp-2 text-[11px] leading-4 text-muted-foreground">{{ version.system_template }}</p>
                        <div class="mt-2 flex gap-2">
                            <button type="button" class="text-[11px] text-primary hover:underline" @click="loadVersionIntoEditor(version)">
                                Load into editor
                            </button>
                            <button
                                v-if="version.id !== activeVersionId"
                                type="button"
                                class="text-[11px] text-primary hover:underline disabled:opacity-50"
                                :disabled="publishingId === version.id"
                                @click="publishVersion(version.id)"
                            >
                                {{ publishingId === version.id ? 'Publishing…' : 'Publish' }}
                            </button>
                        </div>
                    </div>
                </div>

                <div v-else class="flex flex-1 flex-col overflow-hidden">
                    <p class="shrink-0 border-b border-border px-4 py-2 text-[11px] leading-4 text-muted-foreground">
                        Обсуждает именно этот промпт (видит текст выше). Ничего не сохраняет и не публикует сам — только
                        предлагает текст, вы вставляете вручную.
                    </p>

                    <div class="flex-1 space-y-3 overflow-y-auto p-4">
                        <p v-if="chatMessages.length === 0" class="text-xs text-muted-foreground">
                            Спросите, например: «как сделать этот промпт короче» или «добавь просьбу отвечать в формальном тоне».
                        </p>
                        <div
                            v-for="(msg, i) in chatMessages"
                            :key="i"
                            class="rounded-lg px-3 py-2 text-xs leading-5"
                            :class="msg.role === 'user' ? 'ml-4 bg-primary/10 text-fg' : 'mr-4 bg-muted/50 text-fg'"
                        >
                            <div class="mb-0.5 text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
                                {{ msg.role === 'user' ? 'Вы' : 'Ассистент' }}
                            </div>
                            <p class="whitespace-pre-wrap">{{ msg.content }}</p>
                        </div>

                        <div v-if="chatSending" class="mr-4 text-xs text-muted-foreground">Ассистент печатает…</div>
                        <p v-if="chatError" class="text-xs text-danger">{{ chatError }}</p>

                        <div v-if="chatProposal" class="rounded-lg border border-primary/40 bg-primary/5 p-3">
                            <div class="text-[11px] font-medium text-fg">Предложенный вариант</div>
                            <p v-if="chatProposal.system" class="mt-2 whitespace-pre-wrap rounded-md bg-background p-2 font-mono text-[11px] leading-4">{{ chatProposal.system }}</p>
                            <p v-if="chatProposal.user" class="mt-2 whitespace-pre-wrap rounded-md bg-background p-2 font-mono text-[11px] leading-4">{{ chatProposal.user }}</p>
                            <div class="mt-2 flex items-center gap-2">
                                <UiButton variant="primary" size="sm" @click="applyChatProposal">Вставить в редактор</UiButton>
                                <button type="button" class="text-[11px] text-muted-foreground hover:underline" @click="chatProposal = null">Отклонить</button>
                            </div>
                        </div>
                    </div>

                    <div class="shrink-0 border-t border-border p-3">
                        <textarea
                            v-model="chatInput"
                            rows="2"
                            placeholder="Спросите об этом промпте…"
                            class="w-full rounded-md border border-input bg-background px-2 py-1.5 text-xs leading-5 text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            @keydown.enter.exact.prevent="sendChatMessage"
                        />
                        <UiButton variant="secondary" size="sm" class="mt-2 w-full" :disabled="chatSending || !chatInput.trim()" @click="sendChatMessage">
                            {{ chatSending ? 'Отправка…' : 'Отправить' }}
                        </UiButton>
                    </div>
                </div>
            </aside>
        </div>
        </div>
    </div>
</template>
