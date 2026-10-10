<script setup lang="ts">
import { RouterLink } from 'vue-router';
import WordExamples from './WordExamples.vue';
import type { LexemeExampleItem } from '../../types/lexeme';

defineProps<{ text: string; lexemeId?: number | null; language?: string | null; examples?: LexemeExampleItem[]; example?: string | null }>();
</script>

<template>
    <div class="min-w-0 space-y-2 [overflow-wrap:anywhere] px-3 pb-3">
        <slot />
        <WordExamples :examples="examples" :fallback-example="example" :language="language" :truncate="false" />
        <div class="flex flex-wrap items-center gap-1">
            <slot name="actions" />
            <RouterLink v-if="lexemeId" :to="{ name: 'word.details', params: { id: lexemeId } }" class="inline-flex min-h-11 items-center rounded-md px-3 text-sm text-primary hover:underline">Word page</RouterLink>
        </div>
        <slot name="source" />
    </div>
</template>
