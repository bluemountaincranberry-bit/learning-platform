<script setup lang="ts">
import { computed } from 'vue';
import { ExternalLink, Languages } from 'lucide-vue-next';
import { translatorLinks } from '../lib/translators';
import { useProfileStore } from '../../domains/user';

/**
 * Row of external translator deep-links for a word or phrase (ExplainDialog,
 * LexemeDetailPage). Target language defaults to the learner's own
 * `translation_language`.
 */
const props = withDefaults(defineProps<{
    text: string;
    /** ISO 639-1 source language; DeepL/Reverso links hide when unknown. */
    sourceLanguage?: string | null;
    /** ISO 639-1 target language; falls back to the learner's own language. */
    targetLanguage?: string | null;
}>(), { sourceLanguage: null, targetLanguage: null });

const profileStore = useProfileStore();
const target = computed(() => props.targetLanguage ?? profileStore.profile?.user.translation_language ?? 'ru');
const links = computed(() => translatorLinks(props.text, props.sourceLanguage, target.value));
</script>

<template>
    <div v-if="links.length > 0" class="flex flex-wrap items-center gap-1">
        <span class="mr-1 inline-flex items-center gap-1 text-xs text-muted-foreground">
            <Languages :size="14" aria-hidden="true" /> Translate:
        </span>
        <a
            v-for="link in links"
            :key="link.key"
            :href="link.url"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex h-11 items-center gap-1 rounded-md px-2.5 text-sm text-primary hover:underline"
        >
            {{ link.label }} <ExternalLink :size="12" class="text-muted-foreground" aria-hidden="true" />
        </a>
    </div>
</template>
