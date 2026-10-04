<script setup lang="ts">
import { ref } from 'vue';
import { ChevronDown } from 'lucide-vue-next';
import UiBadge from './UiBadge.vue';
import WordCard from './WordCard.vue';
import type { LexemeExampleItem } from '../../types/lexeme';

const props = defineProps<{ text: string; translation?: string | null; level?: string | null; lexemeId?: number | null; language?: string | null; examples?: LexemeExampleItem[]; example?: string | null; selectable?: boolean; selected?: boolean; defaultExpanded?: boolean }>();
const emit = defineEmits<{ toggleSelect: [] }>();
const expanded = ref(Boolean(props.defaultExpanded));
</script>

<template>
    <div class="min-w-0 bg-surface">
        <div class="flex min-h-[52px] min-w-0 items-center gap-1 pr-1">
            <label v-if="selectable" class="flex h-11 w-11 shrink-0 cursor-pointer items-center justify-center">
                <input type="checkbox" class="h-5 w-5 rounded border-border accent-primary" :checked="selected" :aria-label="`Select ${text}`" @change="emit('toggleSelect')" />
            </label>
            <button type="button" class="flex min-h-11 min-w-0 flex-1 items-center gap-2 py-1.5 text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" :class="selectable ? '' : 'pl-3'" :aria-expanded="expanded" :aria-label="`${text}${translation ? ` — ${translation}` : ''}. ${expanded ? 'Hide' : 'Show'} details`" @click="expanded = !expanded">
                <span class="min-w-0 flex-1"><span class="block truncate font-medium text-fg">{{ text }}</span><span v-if="translation" class="block truncate text-sm text-fg-secondary">{{ translation }}</span></span>
                <UiBadge v-if="level" tone="primary" class="shrink-0" title="CEFR level">{{ level }}</UiBadge>
                <ChevronDown :size="16" class="shrink-0 text-muted-foreground transition-transform" :class="{ 'rotate-180': expanded }" aria-hidden="true" />
            </button>
            <slot name="row-actions" />
        </div>
        <WordCard v-if="expanded" :text="text" :lexeme-id="lexemeId" :language="language" :examples="examples" :example="example">
            <slot />
            <template #actions><slot name="actions" /></template>
            <template #source><slot name="source" /></template>
        </WordCard>
    </div>
</template>
