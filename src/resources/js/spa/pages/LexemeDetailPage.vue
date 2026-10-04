<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Lightbulb, LoaderCircle, Trash2 } from 'lucide-vue-next';
import PageState from '../components/ui/PageState.vue';
import AskAiButton from '../shared/ui/AskAiButton.vue';
import UiBadge from '../shared/ui/UiBadge.vue';
import UiButton from '../shared/ui/UiButton.vue';
import UiCard from '../shared/ui/UiCard.vue';
import UiSectionHeader from '../shared/ui/UiSectionHeader.vue';
import SpeakButton from '../shared/ui/SpeakButton.vue';
import TranslatableText from '../shared/ui/TranslatableText.vue';
import TranslatorLinks from '../shared/ui/TranslatorLinks.vue';
import WordExamples from '../shared/ui/WordExamples.vue';
import { dictionaryApi } from '../domains/content';
import { formatGrammarFeatures } from '../shared/grammarFeatures';
import { groupAssociationsByType } from '../shared/lexemeAssociations';
import type { LexemeDetail } from '../types';

const route = useRoute();
const router = useRouter();
const wordId = computed(() => route.params.id as string);

const loading = ref(true);
const error = ref('');
const lexeme = ref<LexemeDetail | null>(null);
const explaining = ref(false);
const explainError = ref('');
const deletingId = ref<number | null>(null);

const associationGroups = computed(() => groupAssociationsByType(lexeme.value?.associations));
const primaryTranslation = computed(() => lexeme.value?.translations.find((t) => t.is_primary) ?? lexeme.value?.translations[0]);
const otherTranslations = computed(() => (lexeme.value?.translations ?? []).filter((t) => t !== primaryTranslation.value));

// Task 10.5: when this lemma has distinct meanings, each one shows its own
// translations/examples instead of the flat arrays above — avoids showing
// the same example twice under two different headings.
const senses = computed(() => lexeme.value?.senses ?? []);
const forms = computed(() => lexeme.value?.forms ?? []);

function sensePrimaryTranslation(sense: { translations: { translation: string; is_primary: boolean }[] }): string | null {
    return (sense.translations.find((t) => t.is_primary) ?? sense.translations[0])?.translation ?? null;
}

async function loadLexeme(): Promise<void> {
    loading.value = true;
    error.value = '';
    try {
        const data = await dictionaryApi.getOne(wordId.value);
        lexeme.value = data.lexeme;
    } catch {
        error.value = 'Word not found or unavailable.';
    } finally {
        loading.value = false;
    }
}

// Task 6.1: context for AskAiButton.
const aiContext = computed(() => ({
    type: 'lexeme' as const,
    id: lexeme.value?.id ?? '',
    title: lexeme.value?.lemma ?? '',
}));

/** On-demand AI explanation, saved server-side — the same durable store the
 * per-content Explain buttons read from, so either side reuses the other. */
async function explainWord(): Promise<void> {
    if (!lexeme.value || explaining.value) return;
    explaining.value = true;
    explainError.value = '';
    try {
        const data = await dictionaryApi.explain(lexeme.value.id);
        if (lexeme.value && data.explanation_id !== null) {
            const rest = lexeme.value.explanations.filter((item) => item.content !== null);
            lexeme.value = {
                ...lexeme.value,
                explanations: [...rest, { id: data.explanation_id, explanation: data.explanation, content: null }],
            };
        }
    } catch (e: unknown) {
        const err = e as { response?: { status?: number; data?: { message?: string } } };
        explainError.value = err.response?.status === 503
            ? 'AI temporarily unavailable.'
            : (err.response?.data?.message ?? 'Failed to get explanation.');
    } finally {
        explaining.value = false;
    }
}

async function deleteExplanation(explanationId: number): Promise<void> {
    if (!lexeme.value || deletingId.value !== null) return;
    deletingId.value = explanationId;
    try {
        await dictionaryApi.deleteExplanation(lexeme.value.id, explanationId);
        lexeme.value = {
            ...lexeme.value,
            explanations: lexeme.value.explanations.filter((item) => item.id !== explanationId),
        };
    } catch {
        explainError.value = 'Failed to delete this explanation.';
    } finally {
        deletingId.value = null;
    }
}

onMounted(loadLexeme);
</script>

<template>
    <PageState :loading="loading" :error="error">
        <div class="space-y-6">
            <UiCard class="space-y-4">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="space-y-3">
                        <div class="flex flex-wrap gap-2">
                            <UiBadge tone="primary">dictionary</UiBadge>
                            <UiBadge tone="neutral">{{ lexeme?.language }}</UiBadge>
                            <UiBadge v-if="lexeme?.part_of_speech" tone="neutral">{{ lexeme?.part_of_speech }}</UiBadge>
                            <UiBadge v-if="lexeme?.level" tone="neutral">{{ lexeme?.level }}</UiBadge>
                        </div>
                        <div class="flex items-center gap-2">
                            <SpeakButton v-if="lexeme" :text="lexeme.lemma" :language="lexeme.language" />
                            <h2 class="text-2xl font-semibold text-fg">{{ lexeme?.lemma }}</h2>
                        </div>
                        <div v-if="primaryTranslation" class="text-lg font-medium text-fg-secondary">{{ primaryTranslation.translation }}</div>
                        <div v-if="otherTranslations.length > 0" class="flex flex-wrap gap-1.5">
                            <UiBadge v-for="t in otherTranslations" :key="t.translation" tone="neutral">{{ t.translation }}</UiBadge>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <AskAiButton v-if="lexeme" :context="aiContext" label="Ask about this word" />
                        <UiButton variant="secondary" @click="router.back()">Back</UiButton>
                    </div>
                </div>
                <TranslatorLinks v-if="lexeme" :text="lexeme.lemma" :source-language="lexeme.language" />
            </UiCard>

            <UiCard class="space-y-3">
                <UiSectionHeader title="AI explanations" subtitle="One per context where you met this word" />
                <div v-if="lexeme && lexeme.explanations.length > 0" class="space-y-4">
                    <div v-for="item in lexeme.explanations" :key="item.id" class="space-y-1.5">
                        <div class="flex items-center justify-between gap-2">
                            <RouterLink
                                v-if="item.content"
                                :to="{ name: 'catalog.details', params: { id: item.content.id } }"
                                class="text-xs font-medium text-primary hover:underline"
                            >
                                From: {{ item.content.title }}
                            </RouterLink>
                            <span v-else class="text-xs font-medium text-muted-foreground">General explanation</span>
                            <UiButton
                                variant="ghost"
                                size="sm"
                                class="h-8 px-2 text-xs text-muted-foreground"
                                :disabled="deletingId !== null"
                                :aria-label="`Delete this explanation${item.content ? ` from ${item.content.title}` : ''}`"
                                @click="deleteExplanation(item.id)"
                            >
                                <Trash2 :size="13" />
                            </UiButton>
                        </div>
                        <TranslatableText :text="item.explanation" />
                    </div>
                </div>
                <div class="space-y-2">
                    <p v-if="explaining" class="flex items-center gap-2 text-sm text-muted-foreground" role="status">
                        <LoaderCircle :size="16" class="animate-spin text-primary" aria-hidden="true" /> AI is explaining…
                    </p>
                    <p v-else-if="explainError" class="text-sm text-warning" role="alert">{{ explainError }}</p>
                    <p v-else-if="!lexeme?.explanations.length" class="text-sm text-muted-foreground">No explanation yet — generate one with AI.</p>
                    <UiButton variant="secondary" size="sm" :disabled="explaining" @click="explainWord">
                        <Lightbulb :size="14" :class="{ 'animate-pulse': explaining }" /> Explain with AI
                    </UiButton>
                </div>
            </UiCard>

            <UiCard v-if="senses.length > 0" class="space-y-4">
                <UiSectionHeader title="Meanings" subtitle="This word has more than one common meaning" />
                <div v-for="(sense, index) in senses" :key="sense.id" class="space-y-2 border-b border-border pb-4 last:border-b-0 last:pb-0">
                    <div class="flex flex-wrap items-baseline gap-2">
                        <span class="text-sm font-semibold text-muted-foreground">{{ index + 1 }}.</span>
                        <span class="font-medium text-fg">{{ sensePrimaryTranslation(sense) ?? sense.gloss }}</span>
                        <UiBadge v-if="sense.part_of_speech" tone="neutral">{{ sense.part_of_speech }}</UiBadge>
                    </div>
                    <p class="text-sm text-muted-foreground">{{ sense.gloss }}</p>
                    <WordExamples v-if="sense.examples.length > 0" :examples="sense.examples" :collapsible="false" :language="lexeme?.language" />
                </div>
            </UiCard>

            <UiCard v-if="forms.length > 0" class="space-y-3">
                <UiSectionHeader title="Forms" subtitle="Forms actually seen in your content" />
                <div class="flex flex-wrap gap-1.5">
                    <UiBadge v-for="form in forms" :key="form.text" tone="neutral">
                        {{ form.text }}
                        <span v-if="formatGrammarFeatures(form.grammar_features)" class="text-muted-foreground">
                            ({{ formatGrammarFeatures(form.grammar_features) }})
                        </span>
                    </UiBadge>
                </div>
            </UiCard>

            <UiCard v-if="associationGroups.length > 0" class="space-y-3">
                <UiSectionHeader title="Related words" />
                <div v-for="group in associationGroups" :key="group.type" class="flex flex-wrap items-center gap-1.5">
                    <span class="text-xs text-muted-foreground">{{ group.label }}:</span>
                    <UiBadge v-for="item in group.items" :key="item" :tone="group.tone">{{ item }}</UiBadge>
                </div>
            </UiCard>

            <UiCard v-if="senses.length === 0 && lexeme && lexeme.examples.length > 0" class="space-y-3">
                <UiSectionHeader title="Examples" subtitle="Tap a sentence to see its translation" />
                <WordExamples :examples="lexeme.examples" :collapsible="false" :language="lexeme.language" />
            </UiCard>
        </div>
    </PageState>
</template>
