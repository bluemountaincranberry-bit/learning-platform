<script setup lang="ts">
import { onMounted, ref } from 'vue';
import UiBadge from '../../shared/ui/UiBadge.vue';
import AiBuilderTopBar from '../../domains/ai-builder/AiBuilderTopBar.vue';
import { aiBuilderApi } from '../../domains/ai-builder/api';
import type { AgentToolSummary, PromptCatalogFlow } from '../../domains/ai-builder/types';

const flows = ref<PromptCatalogFlow[]>([]);
const loading = ref(true);
const errorMessage = ref<string | null>(null);

onMounted(async () => {
    try {
        flows.value = await aiBuilderApi.promptCatalog();
    } catch {
        errorMessage.value = 'Не удалось загрузить каталог промптов.';
    } finally {
        loading.value = false;
    }
});

/**
 * content_analysis_system_prompt is the one key shared between the live
 * AiContentAnalysisService path and the ai_analysis graph's Analyze node
 * (see PromptCatalogService's docblock) — offer both entry points for it,
 * not just the prompt editor, so it's clear the graph canvas edits the
 * same real prompt, not a separate beta-only copy.
 */
function graphLinkFor(key: string): { name: string; params: { key: string } } | null {
    if (key === 'content_analysis_system_prompt') {
        return { name: 'admin.aiBuilder.graph', params: { key: 'ai_analysis' } };
    }
    return null;
}

const FLOW_ICON: Record<string, string> = {
    agent_chat: '💬',
    content_ingestion: '📥',
    student_practice: '🎓',
};

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
</script>

<template>
    <div class="flex h-screen flex-col bg-background text-fg">
        <AiBuilderTopBar />

        <header class="shrink-0 border-b border-border bg-card px-6 py-4">
            <h1 class="text-lg font-semibold">Каталог промптов</h1>
            <p class="mt-1 max-w-2xl text-xs leading-5 text-muted-foreground">
                Каждый промпт, который реально уходит в модель — сгруппирован по тому, что в этот момент делает пользователь,
                а не по структуре кода. Ключ без опубликованной версии сейчас использует текст по умолчанию из кода — это
                нормальное состояние, а не пропущенная настройка.
            </p>
        </header>

        <div class="flex-1 overflow-y-auto p-6">
        <div class="mx-auto max-w-4xl">
            <div v-if="loading" class="text-sm text-muted-foreground">Загрузка…</div>
            <div v-else-if="errorMessage" class="text-sm text-danger">{{ errorMessage }}</div>

            <div v-else class="space-y-10">
                <section v-for="flow in flows" :key="flow.flow">
                    <div class="flex items-baseline gap-2">
                        <span class="text-base leading-none">{{ FLOW_ICON[flow.flow] ?? '•' }}</span>
                        <h2 class="text-sm font-semibold">{{ flow.label }}</h2>
                        <span class="text-xs text-muted-foreground">({{ flow.entries.length }})</span>
                    </div>

                    <div class="mt-3 space-y-3">
                        <div v-for="entry in flow.entries" :key="entry.key" class="rounded-xl border border-border bg-card p-4">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-sm font-semibold">{{ entry.label }}</span>
                                        <UiBadge :tone="entry.has_override ? 'success' : 'neutral'">
                                            {{ entry.has_override ? '✓ изменён' : 'по умолчанию' }}
                                        </UiBadge>
                                    </div>
                                    <p class="mt-1 text-xs leading-5 text-muted-foreground">{{ entry.description }}</p>
                                </div>

                                <div class="flex shrink-0 flex-col items-end gap-1">
                                    <RouterLink
                                        v-if="graphLinkFor(entry.key)"
                                        :to="graphLinkFor(entry.key)!"
                                        class="text-xs font-medium text-primary hover:underline"
                                    >
                                        Открыть в графе →
                                    </RouterLink>
                                    <RouterLink
                                        :to="{ name: 'admin.aiBuilder.prompt', params: { key: entry.key } }"
                                        class="text-xs font-medium text-primary hover:underline"
                                    >
                                        Открыть редактор промпта →
                                    </RouterLink>
                                </div>
                            </div>

                            <div v-if="entry.code_default_preview" class="mt-3 rounded-lg bg-muted/40 px-3 py-2">
                                <div class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">Текст по умолчанию (из кода)</div>
                                <p class="mt-0.5 truncate text-xs text-muted-foreground">{{ entry.code_default_preview }}</p>
                            </div>

                            <div v-if="entry.tools.length > 0" class="mt-3">
                                <div class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
                                    Доступные инструменты ({{ entry.tools.length }}) — модель сама решает, какой вызвать и когда
                                </div>
                                <div class="mt-1.5 flex flex-wrap gap-1.5">
                                    <span
                                        v-for="tool in entry.tools"
                                        :key="tool.name"
                                        :title="tool.description"
                                        class="inline-flex cursor-help items-center gap-1 rounded-md border border-border bg-background px-2 py-1 text-[11px]"
                                    >
                                        <span class="font-mono">{{ tool.name }}</span>
                                        <UiBadge :tone="SIDE_EFFECT_TONE[tool.side_effect]">{{ SIDE_EFFECT_LABEL[tool.side_effect] }}</UiBadge>
                                    </span>
                                </div>
                                <p class="mt-1.5 text-[11px] leading-4 text-muted-foreground">
                                    Список инструментов и что они умеют — только для просмотра, задаётся в коде агента.
                                    Наведите на инструмент, чтобы увидеть, что именно он делает.
                                </p>
                            </div>

                            <p class="mt-3 font-mono text-[10px] text-muted-foreground/60">{{ entry.key }}</p>
                        </div>
                    </div>
                </section>
            </div>
        </div>
        </div>
    </div>
</template>
