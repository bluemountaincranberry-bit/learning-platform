<script setup lang="ts">
import { Handle, Position } from '@vue-flow/core';
import { computed } from 'vue';
import type { GraphCanvasNodeData } from './vueFlowAdapter';

const props = defineProps<{
    data: GraphCanvasNodeData;
    selected?: boolean;
    status?: 'running' | 'completed' | 'failed' | null;
}>();

const ringClass = computed(() => {
    switch (props.status) {
        case 'running':
            return 'ring-2 ring-primary animate-pulse';
        case 'completed':
            return 'ring-2 ring-success';
        case 'failed':
            return 'ring-2 ring-danger';
        default:
            return props.selected ? 'ring-2 ring-primary/40' : '';
    }
});

/**
 * Left accent strip color, by node category — priority order matters
 * (a node can match more than one signal, e.g. ApplyNode is both
 * side_effect=publish and non-pausable): publish (most consequential) >
 * human checkpoint > LLM/agent call > everything else (plain
 * deterministic logic). Answers "what kind of thing is this, at a
 * glance" without reading the type name — the same visual-differentiation
 * gap the graph-canvas feedback called out.
 */
const category = computed(() => {
    const contract = props.data.contract;
    if (contract?.side_effect === 'publish') return { border: 'border-l-danger', badge: 'публикует' };
    if (contract?.can_pause) return { border: 'border-l-warning', badge: 'пауза' };
    if (props.data.promptKey || contract?.execution === 'async_fanout') return { border: 'border-l-primary', badge: 'LLM/агент' };
    return { border: 'border-l-border', badge: null };
});
</script>

<template>
    <div
        class="min-w-[180px] max-w-[220px] rounded-xl border border-l-4 border-border bg-card px-3 py-2.5 text-card-foreground shadow-sm transition-shadow"
        :class="[ringClass, category.border]"
    >
        <Handle type="target" :position="Position.Left" class="!h-2.5 !w-2.5 !border-border !bg-muted-foreground" />

        <div class="flex items-center gap-1.5 text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
            <span>{{ data.nodeType }}</span>
            <span v-if="data.promptKey" title="Есть редактируемый промпт">💬</span>
            <span v-if="category.badge" class="ml-auto rounded-sm bg-muted px-1 py-0.5 text-[9px] normal-case text-foreground/70">{{ category.badge }}</span>
        </div>
        <div class="mt-0.5 text-sm font-medium leading-snug">{{ data.label }}</div>
        <div class="mt-0.5 font-mono text-[11px] text-muted-foreground">{{ data.stepKey }}</div>

        <Handle type="source" :position="Position.Right" class="!h-2.5 !w-2.5 !border-border !bg-muted-foreground" />
    </div>
</template>
