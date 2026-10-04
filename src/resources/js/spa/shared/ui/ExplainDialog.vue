<script setup lang="ts">
import { LoaderCircle, RotateCcw } from 'lucide-vue-next';
import UiDialog from './UiDialog.vue';
import UiButton from './UiButton.vue';
import SpeakButton from './SpeakButton.vue';
import TranslatorLinks from './TranslatorLinks.vue';

/**
 * Shared AI-explanation dialog (ContentDetailsPage, StudyPage, MyWordsPage).
 *
 * Opens immediately with a loading skeleton while the explanation streams in,
 * so the learner gets instant feedback instead of a pulsing button. External
 * translator links come from the shared TranslatorLinks row — no second AI
 * path to maintain, and they work even when AI is down. The word's own AI
 * translation is already shown in the word row; it is repeated here.
 */
const props = withDefaults(defineProps<{
    open: boolean;
    lexemeText: string;
    translation?: string | null;
    /** ISO 639-1 source language for the Google link and pronunciation. */
    language?: string | null;
    explanation: string;
    loading: boolean;
    error?: string;
}>(), { translation: null, language: null, error: '' });

const emit = defineEmits<{
    close: [];
    retry: [];
}>();
</script>

<template>
    <UiDialog :open="open" :title="lexemeText" sheet @close="emit('close')">
        <div v-if="loading" class="space-y-3" role="status" aria-label="Loading explanation">
            <div class="flex items-center gap-2 text-sm text-muted-foreground">
                <LoaderCircle :size="16" class="animate-spin text-primary" aria-hidden="true" />
                AI is explaining…
            </div>
            <div class="space-y-2" aria-hidden="true">
                <div class="h-3.5 animate-pulse rounded bg-muted" />
                <div class="h-3.5 animate-pulse rounded bg-muted" />
                <div class="h-3.5 w-2/3 animate-pulse rounded bg-muted" />
            </div>
        </div>

        <div v-else-if="error" class="space-y-3">
            <p class="text-sm text-warning" role="alert">{{ error }}</p>
            <UiButton variant="secondary" size="sm" @click="emit('retry')">
                <RotateCcw :size="14" /> Try again
            </UiButton>
        </div>

        <div v-else class="space-y-4">
            <div class="flex flex-wrap items-center gap-2">
                <SpeakButton v-if="language" :text="lexemeText" :language="language" />
                <span v-if="translation" class="text-sm font-medium text-fg">{{ translation }}</span>
            </div>
            <TranslatorLinks :text="lexemeText" :source-language="language" />
            <p class="whitespace-pre-wrap text-sm leading-6 text-fg-secondary">{{ explanation }}</p>
        </div>
    </UiDialog>
</template>
