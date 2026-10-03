<script setup lang="ts">
import { ref, watch } from 'vue';
import UiButton from '../../shared/ui/UiButton.vue';
import UiDialog from '../../shared/ui/UiDialog.vue';
import { GRAMMAR_PRACTICE_COUNTS, useGrammarPracticeSetting } from '../../domains/learning';
import type { GrammarPracticeLevel } from '../../types';
import { LEVEL_HINT, LEVEL_LABEL } from './practice/exerciseLabels';

/** "Change": Level (Easy / Medium / Hard) and Count (5 · 10 · 15), as in VIK-29. */
const props = defineProps<{ open: boolean }>();
const emit = defineEmits<{ close: []; practice: [] }>();

const { setting } = useGrammarPracticeSetting();
const level = ref<GrammarPracticeLevel>(setting.value.level);
const count = ref<number>(setting.value.count);
const levels: GrammarPracticeLevel[] = ['easy', 'medium', 'hard'];

watch(() => props.open, (open) => {
    if (!open) return;
    level.value = setting.value.level;
    count.value = setting.value.count;
});

function practice(): void {
    setting.value = { level: level.value, count: count.value };
    emit('practice');
}

const segment = 'min-h-12 rounded-xl border px-2 py-2 text-center text-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';
</script>

<template>
    <UiDialog :open="open" title="Practice settings" sheet @close="emit('close')">
        <p class="mb-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">Level</p>
        <div class="grid grid-cols-3 gap-2" role="radiogroup" aria-label="Level">
            <button
                v-for="item in levels"
                :key="item"
                type="button"
                role="radio"
                :aria-checked="level === item"
                :class="[segment, level === item ? 'border-2 border-primary bg-card text-fg' : 'border-border bg-surface-alt text-fg']"
                :data-test="`level-${item}`"
                @click="level = item"
            >
                {{ LEVEL_LABEL[item] }}
                <span class="block text-[11px] text-muted-foreground">{{ LEVEL_HINT[item] }}</span>
            </button>
        </div>
        <p class="mb-2 mt-5 text-xs font-medium uppercase tracking-wide text-muted-foreground">Exercises</p>
        <div class="grid grid-cols-3 gap-2" role="radiogroup" aria-label="Exercises">
            <button
                v-for="item in GRAMMAR_PRACTICE_COUNTS"
                :key="item"
                type="button"
                role="radio"
                :aria-checked="count === item"
                :class="[segment, count === item ? 'border-2 border-primary bg-card text-fg' : 'border-border bg-surface-alt text-fg']"
                :data-test="`count-${item}`"
                @click="count = item"
            >
                {{ item }}
            </button>
        </div>
        <UiButton variant="primary" size="lg" class="mt-6 w-full" data-test="sheet-practice" @click="practice">Practice</UiButton>
    </UiDialog>
</template>
