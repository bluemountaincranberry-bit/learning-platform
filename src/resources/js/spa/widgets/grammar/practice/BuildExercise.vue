<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { GrammarPracticeExercise } from '../../../types';

/**
 * Build the sentence (Easy): tap tiles into the answer line; tap a placed
 * tile to send it back. The built sentence is the v-model.
 */
const props = defineProps<{ exercise: GrammarPracticeExercise; disabled: boolean }>();
const model = defineModel<string>({ required: true });

const placed = ref<number[]>([]);
const tiles = computed(() => props.exercise.tiles ?? []);

watch(() => props.exercise.id, () => { placed.value = []; });
watch(placed, (value) => { model.value = value.map((i) => tiles.value[i]).join(' '); }, { deep: true });

function place(index: number): void {
    if (props.disabled || placed.value.includes(index)) return;
    placed.value = [...placed.value, index];
}

function unplace(position: number): void {
    if (props.disabled) return;
    placed.value = placed.value.filter((_, i) => i !== position);
}
</script>

<template>
    <div class="space-y-5">
        <p class="break-words text-base text-muted-foreground">{{ exercise.prompt }}</p>
        <div class="flex min-h-14 flex-wrap gap-2 border-b-2 border-border pb-2" data-test="answer-line" aria-label="Your sentence">
            <button
                v-for="(tileIndex, position) in placed"
                :key="`placed-${position}`"
                type="button"
                class="min-h-11 rounded-lg border border-primary/40 bg-card px-3 py-2 text-base text-fg"
                :disabled="disabled"
                data-test="placed-tile"
                @click="unplace(position)"
            >
                {{ tiles[tileIndex] }}
            </button>
        </div>
        <div class="flex flex-wrap gap-2" aria-label="Words">
            <button
                v-for="(tile, index) in tiles"
                :key="`tile-${index}`"
                type="button"
                class="min-h-11 rounded-lg border border-border bg-surface-alt px-3 py-2 text-base text-fg transition-opacity"
                :class="placed.includes(index) ? 'opacity-25' : ''"
                :disabled="disabled || placed.includes(index)"
                data-test="tile"
                @click="place(index)"
            >
                {{ tile }}
            </button>
        </div>
    </div>
</template>
