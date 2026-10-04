<script setup lang="ts">
import { computed } from 'vue';
import { ExternalLink, Languages, LoaderCircle, RotateCcw } from 'lucide-vue-next';
import UiDialog from './UiDialog.vue';
import UiButton from './UiButton.vue';
import SpeakButton from './SpeakButton.vue';
import { useProfileStore } from '../../domains/user';

/**
 * Shared AI-explanation dialog (ContentDetailsPage, StudyPage, MyWordsPage).
 *
 * Opens immediately with a loading skeleton while the explanation streams in,
 * so the learner gets instant feedback instead of a pulsing button. Translation
 * is a single Google Translate link — no second AI path to maintain, works even
 * when AI is down, and brings TTS for free. The word's own AI translation is
 * already shown in the word row; it is repeated here next to the link target.
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

const profileStore = useProfileStore();
const nativeLanguage = computed(() => profileStore.profile?.user.translation_language ?? 'ru');

const googleTranslateUrl = computed(() => {
    const params = new URLSearchParams({
        sl: props.language ?? 'auto',
        tl: nativeLanguage.value,
        text: props.lexemeText,
        op: 'translate',
    });
    return `https://translate.google.com/?${params.toString()}`;
});
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
                <a
                    :href="googleTranslateUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex h-11 items-center gap-1.5 rounded-md px-3 text-sm text-primary hover:underline"
                >
                    <Languages :size="16" /> Google Translate <ExternalLink :size="13" class="text-muted-foreground" />
                </a>
            </div>
            <p class="whitespace-pre-wrap text-sm leading-6 text-fg-secondary">{{ explanation }}</p>
        </div>
    </UiDialog>
</template>
