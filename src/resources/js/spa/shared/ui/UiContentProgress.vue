<script setup lang="ts">
import { computed } from 'vue';
import UiBadge from './UiBadge.vue';

const props = defineProps<{
    learnedCount?: number;
    inLearningCount?: number;
    totalLexemes?: number;
}>();

const hasData = computed(() => props.totalLexemes != null && props.totalLexemes > 0);
const learningCount = computed(() => props.inLearningCount ?? props.learnedCount ?? 0);
const pct = computed(() => {
    if (!hasData.value) return 0;
    return Math.min(100, Math.round((learningCount.value / (props.totalLexemes as number)) * 100));
});
const isCompleted = computed(() => hasData.value && learningCount.value >= (props.totalLexemes as number));
const isStarted = computed(() => hasData.value && learningCount.value > 0);
</script>

<template>
    <div v-if="hasData" class="space-y-1.5">
        <div class="flex items-center justify-between gap-2">
            <span class="text-xs text-muted-foreground">{{ learningCount }}/{{ totalLexemes }} words in learning</span>
            <span v-if="inLearningCount != null" class="text-xs text-muted-foreground">{{ learnedCount ?? 0 }} learned</span>
            <UiBadge v-if="isCompleted" tone="success">Studied — revisit?</UiBadge>
            <UiBadge v-else-if="isStarted" tone="primary">In progress</UiBadge>
        </div>
        <div class="h-1.5 w-full overflow-hidden rounded-full bg-muted">
            <div
                class="h-full rounded-full transition-[width]"
                :class="isCompleted ? 'bg-success' : 'bg-primary'"
                :style="{ width: `${pct}%` }"
            />
        </div>
    </div>
    <div v-else class="text-xs text-muted-foreground">Not started</div>
</template>
