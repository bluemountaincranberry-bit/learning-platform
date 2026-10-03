<script setup lang="ts">
import { computed } from 'vue';
import { promptParts } from './exerciseLabels';

const props = defineProps<{ prompt: string; filled?: string | null }>();
const parts = computed(() => promptParts(props.prompt));
</script>

<template>
    <p class="break-words text-xl leading-snug text-fg" data-test="prompt">
        <template v-if="parts">
            {{ parts.before }}<span
                class="mx-0.5 inline-block min-w-[4.5rem] border-b-2 border-primary px-1 text-center font-semibold text-primary"
                data-test="gap"
            >{{ filled || ' ' }}</span>{{ parts.after }}
        </template>
        <template v-else>{{ prompt }}</template>
    </p>
</template>
