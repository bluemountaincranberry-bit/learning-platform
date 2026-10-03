<script setup lang="ts">
import { computed, ref } from 'vue';
import { ChevronDown } from 'lucide-vue-next';
import UiButton from './UiButton.vue';
import SpeakButton from './SpeakButton.vue';
import type { LexemeExampleItem } from '../../types/lexeme';

const props = withDefaults(defineProps<{
    examples?: LexemeExampleItem[];
    /** Legacy single-example fallback for lexemes with no `examples` array. */
    fallbackExample?: string | null;
    /** When false, all examples render up front with no "show more" toggle (used on the word detail page). */
    collapsible?: boolean;
    /** Example sentences are in this language — when given, each one gets a pronounce button. Omit where the caller doesn't have it handy; the button just won't render. */
    language?: string | null;
    /** Keep list examples compact while allowing full sentences in the trainer. */
    truncate?: boolean;
}>(), {
    examples: () => [],
    collapsible: true,
    language: null,
    truncate: true,
});

const expanded = ref(false);
const shownTranslations = ref<Set<number>>(new Set());

function toggleTranslation(idx: number) {
    const next = new Set(shownTranslations.value);
    if (next.has(idx)) {
        next.delete(idx);
    } else {
        next.add(idx);
    }
    shownTranslations.value = next;
}

const visibleExamples = computed(() => (!props.collapsible || expanded.value ? props.examples : props.examples.slice(0, 1)));
</script>

<template>
    <template v-if="examples && examples.length > 0">
        <div v-for="(example, idx) in visibleExamples" :key="idx" class="text-sm" @pointerdown.stop>
            <div class="flex items-center gap-2 rounded-lg px-1 py-1.5 transition-colors hover:bg-surface-alt/60">
                <button
                    type="button"
                    class="flex min-w-0 flex-1 items-center gap-2 text-left italic leading-6 text-fg-secondary hover:text-fg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    :title="example.example"
                    :aria-expanded="shownTranslations.has(idx)"
                    :aria-label="shownTranslations.has(idx) ? 'Hide example translation' : 'Show example translation'"
                    @click.stop="toggleTranslation(idx)"
                >
                    <span class="min-w-0 flex-1" :class="truncate ? 'truncate' : 'whitespace-normal break-words'">{{ example.example }}</span>
                    <ChevronDown
                        v-if="example.translation"
                        :size="14"
                        class="shrink-0 text-primary transition-transform"
                        :class="shownTranslations.has(idx) ? 'rotate-180' : ''"
                        aria-hidden="true"
                    />
                </button>
                <SpeakButton v-if="language" :text="example.example" :language="language" />
            </div>
            <div
                v-if="example.translation && shownTranslations.has(idx)"
                class="mt-1 pl-3 text-sm not-italic leading-6 text-muted-foreground"
            >
                <p
                    class="leading-relaxed"
                    :class="truncate ? 'truncate' : 'whitespace-normal break-words'"
                    :title="example.translation"
                >
                    {{ example.translation }}
                </p>
            </div>
        </div>
        <UiButton
            v-if="collapsible && examples.length > 1"
            variant="ghost"
            size="sm"
            class="h-auto px-0 py-0 text-xs"
            @click.stop="expanded = !expanded"
        >
            <ChevronDown :size="15" class="text-primary transition-transform" :class="expanded ? 'rotate-180' : ''" aria-hidden="true" />
            <span>{{ expanded ? 'Show less' : `+${examples.length - 1} more example${examples.length > 2 ? 's' : ''}` }}</span>
        </UiButton>
    </template>
    <div v-else-if="fallbackExample" class="flex items-center gap-2 rounded-lg px-1 py-1.5" @pointerdown.stop>
        <span class="min-w-0 flex-1 text-sm italic leading-6 text-fg-secondary" :class="truncate ? 'truncate' : 'whitespace-normal break-words'" :title="fallbackExample">{{ fallbackExample }}</span>
        <SpeakButton v-if="language" :text="fallbackExample" :language="language" />
    </div>
</template>
