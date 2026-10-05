<script setup lang="ts">
import { BookPlus, BookMinus, LoaderCircle, RotateCcw } from 'lucide-vue-next';
import UiDialog from './UiDialog.vue';
import UiButton from './UiButton.vue';
import SpeakButton from './SpeakButton.vue';
import TranslatableText from './TranslatableText.vue';
import TranslatorLinks from './TranslatorLinks.vue';

/**
 * Shared AI-explanation dialog (ContentDetailsPage, StudyPage, MyWordsPage).
 *
 * Opens immediately with a loading skeleton while the explanation streams in,
 * so the learner gets instant feedback instead of a pulsing button. The
 * footer carries the learning-queue toggle and Regenerate where the host page
 * supports them; external translator links come from the shared
 * TranslatorLinks row. The word's own AI translation is already shown in the
 * word row; it is repeated here.
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
    /** Host page supports the learning-queue toggle (content/study pages). */
    showReviewToggle?: boolean;
    inReview?: boolean;
    reviewPending?: boolean;
    /** Host page supports forced regeneration of this variant. */
    showRegenerate?: boolean;
    regenerating?: boolean;
}>(), {
    translation: null,
    language: null,
    error: '',
    showReviewToggle: false,
    inReview: false,
    reviewPending: false,
    showRegenerate: false,
    regenerating: false,
});

const emit = defineEmits<{
    close: [];
    retry: [];
    toggleReview: [];
    regenerate: [];
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
            <TranslatableText :text="explanation" />
            <TranslatorLinks :text="lexemeText" :source-language="language" />
        </div>

        <template v-if="!loading && !error && (showReviewToggle || showRegenerate)" #footer>
            <UiButton v-if="showReviewToggle" variant="ghost" size="sm" :disabled="reviewPending" @click="emit('toggleReview')">
                <BookMinus v-if="inReview" :size="14" /> <BookPlus v-else :size="14" />
                {{ inReview ? 'In learning — remove' : 'Add to learning' }}
            </UiButton>
            <UiButton v-if="showRegenerate" variant="ghost" size="sm" :disabled="regenerating" @click="emit('regenerate')">
                <RotateCcw :size="14" :class="{ 'animate-spin': regenerating }" /> {{ regenerating ? 'Regenerating…' : 'Regenerate' }}
            </UiButton>
        </template>
    </UiDialog>
</template>
