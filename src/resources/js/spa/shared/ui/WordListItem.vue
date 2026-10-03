<script setup lang="ts">
import { ref } from 'vue';
import { BookPlus, Check, ChevronDown, ExternalLink, Eye, EyeOff, Lightbulb, Minus, Plus, Undo2 } from 'lucide-vue-next';
import UiBadge from './UiBadge.vue';
import UiButton from './UiButton.vue';
import SpeakButton from './SpeakButton.vue';
import WordExamples from './WordExamples.vue';
import { formatGrammarFeatures } from '../grammarFeatures';
import { groupAssociationsByType } from '../lexemeAssociations';
import type { LexemeWithLearned } from '../../types';

/**
 * One word in a content's word list (ContentDetailsPage.vue, StudyPage.vue).
 *
 * VIK-38: a compact, full-width row so ~8+ words fit on a phone screen —
 * checkbox · word + translation · level · add · known. Everything else
 * (speak, badges, confidence, associations, examples, skip/explain/more
 * examples, word page link) opens on tap of the word, so scanning and
 * picking words never needs scrolling past details.
 */
const props = defineProps<{
    lexeme: LexemeWithLearned;
    language?: string;
    marking?: boolean;
    startingReview?: boolean;
    explaining?: boolean;
    aiUnavailable?: boolean;
    /** Task 7.4: bulk selection — renders the row checkbox. */
    selectable?: boolean;
    selected?: boolean;
    fetchingExamples?: boolean;
    /** Open the details on first render (e.g. the single word card under the transcript). */
    defaultExpanded?: boolean;
}>();

const emit = defineEmits<{
    markLearned: [lexeme: LexemeWithLearned];
    unmarkLearned: [lexeme: LexemeWithLearned];
    startReview: [lexeme: LexemeWithLearned];
    stopReview: [lexeme: LexemeWithLearned];
    explain: [lexeme: LexemeWithLearned];
    toggleSelect: [lexeme: LexemeWithLearned];
    skip: [lexeme: LexemeWithLearned];
    unskip: [lexeme: LexemeWithLearned];
    moreExamples: [lexeme: LexemeWithLearned];
}>();

const expanded = ref(Boolean(props.defaultExpanded));
const associationGroups = () => groupAssociationsByType(props.lexeme.associations);
</script>

<template>
    <div :class="lexeme.learned || lexeme.skipped ? 'bg-muted/60' : 'bg-surface'">
        <div class="flex min-h-[52px] items-center gap-1 pr-1">
            <label v-if="selectable" class="flex h-11 w-11 shrink-0 cursor-pointer items-center justify-center">
                <input
                    type="checkbox"
                    class="h-5 w-5 rounded border-border accent-primary"
                    :checked="selected"
                    :aria-label="`Select ${lexeme.text}`"
                    @change="emit('toggleSelect', lexeme)"
                />
            </label>
            <button
                type="button"
                class="flex min-h-11 min-w-0 flex-1 items-center gap-2 py-1.5 text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                :class="selectable ? '' : 'pl-3'"
                :aria-expanded="expanded"
                :aria-label="`${lexeme.text}${lexeme.translation ? ` — ${lexeme.translation}` : ''}. ${expanded ? 'Hide' : 'Show'} details`"
                @click="expanded = !expanded"
            >
                <span class="min-w-0 flex-1">
                    <span class="block truncate font-medium text-fg">{{ lexeme.text }}</span>
                    <span v-if="lexeme.translation" class="block truncate text-sm text-fg-secondary">{{ lexeme.translation }}</span>
                </span>
                <UiBadge v-if="lexeme.level" tone="primary" class="shrink-0" title="CEFR level">{{ lexeme.level }}</UiBadge>
                <ChevronDown :size="16" class="shrink-0 text-muted-foreground transition-transform" :class="{ 'rotate-180': expanded }" aria-hidden="true" />
            </button>

            <UiBadge v-if="lexeme.learned" tone="success" class="shrink-0" title="You've marked this word as learned">Learned</UiBadge>
            <!-- 44px tap target, lighter 32px visual so a long list doesn't read as a wall of buttons. -->
            <UiButton
                v-else-if="lexeme.in_review"
                variant="ghost"
                size="icon"
                class="h-11 w-11 shrink-0"
                :disabled="startingReview"
                aria-label="Remove from your learning queue"
                title="Remove from your spaced-repetition learning queue"
                @click="emit('stopReview', lexeme)"
            >
                <span class="flex h-8 w-8 items-center justify-center rounded-full border border-border bg-secondary text-secondary-foreground"><Minus :size="16" /></span>
            </UiButton>
            <UiButton
                v-else
                variant="ghost"
                size="icon"
                class="h-11 w-11 shrink-0"
                :disabled="startingReview"
                aria-label="Add to your learning queue"
                title="Add to your spaced-repetition learning queue, to practice it later on the Repetitions page"
                @click="emit('startReview', lexeme)"
            >
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-primary-foreground"><Plus :size="16" /></span>
            </UiButton>

            <UiButton
                v-if="lexeme.learned"
                variant="ghost"
                size="icon"
                class="h-11 w-11 shrink-0"
                :disabled="marking"
                aria-label="Remove from learned words"
                title="Remove from learned words"
                @click="emit('unmarkLearned', lexeme)"
            >
                <Undo2 :size="18" />
            </UiButton>
            <UiButton
                v-else
                variant="ghost"
                size="icon"
                class="h-11 w-11 shrink-0 text-success-fg"
                :disabled="marking"
                aria-label="I know this word — mark as learned"
                title="I know this word — mark as learned"
                @click="emit('markLearned', lexeme)"
            >
                <Check :size="18" />
            </UiButton>
        </div>

        <div v-if="expanded" class="space-y-2 px-3 pb-3" :class="selectable ? 'sm:pl-11' : ''">
            <div class="flex flex-wrap items-center gap-1.5">
                <SpeakButton :text="lexeme.text" :language="language" />
                <UiBadge tone="neutral">{{ lexeme.type }}</UiBadge>
                <UiBadge v-if="lexeme.learning_category" tone="neutral" :title="(lexeme.learning_reasons ?? []).join(', ')">
                    {{ lexeme.learning_category.replaceAll('_', ' ') }}
                </UiBadge>
                <UiBadge
                    v-if="lexeme.not_analyzed"
                    tone="warning"
                    title="Left over from basic tokenization — AI analysis hasn't covered this word yet"
                >
                    Not yet analyzed
                </UiBadge>
                <UiBadge v-if="lexeme.skipped" tone="neutral" title="You marked this word as not interested">Not interested</UiBadge>
                <UiBadge
                    v-if="formatGrammarFeatures(lexeme.grammar_features)"
                    tone="neutral"
                    title="Grammar of this specific occurrence"
                >
                    {{ formatGrammarFeatures(lexeme.grammar_features) }}
                </UiBadge>
            </div>
            <p v-if="lexeme.sense_gloss" class="text-sm text-muted-foreground">{{ lexeme.sense_gloss }}</p>
            <div v-if="lexeme.confidence" class="flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-muted-foreground" title="Skill confidence from practice">
                <span>Recognition {{ lexeme.confidence.recognition }}%</span>
                <span>Recall {{ lexeme.confidence.recall }}%</span>
                <span>Listening {{ lexeme.confidence.listening }}%</span>
            </div>
            <div v-for="group in associationGroups()" :key="group.type" class="flex flex-wrap items-center gap-1.5">
                <span class="text-xs text-muted-foreground">{{ group.label }}:</span>
                <UiBadge v-for="item in group.items" :key="item" :tone="group.tone">{{ item }}</UiBadge>
            </div>
            <WordExamples :examples="lexeme.examples" :fallback-example="lexeme.example" :language="language" />
            <div class="flex flex-wrap items-center gap-1">
                <UiButton v-if="lexeme.skipped" variant="ghost" class="h-11 px-3" :disabled="marking" @click="emit('unskip', lexeme)">
                    <Eye :size="16" /> Show again
                </UiButton>
                <UiButton v-else variant="ghost" class="h-11 px-3" :disabled="marking" title="Hide this word from your list" @click="emit('skip', lexeme)">
                    <EyeOff :size="16" /> Not interested
                </UiButton>
                <UiButton v-if="!aiUnavailable" variant="ghost" class="h-11 px-3" :disabled="explaining" @click="emit('explain', lexeme)">
                    <Lightbulb :size="16" :class="{ 'animate-pulse': explaining }" /> Explain
                </UiButton>
                <UiButton v-if="!aiUnavailable && lexeme.lexeme_id" variant="ghost" class="h-11 px-3" :disabled="fetchingExamples" @click="emit('moreExamples', lexeme)">
                    <BookPlus :size="16" :class="{ 'animate-pulse': fetchingExamples }" /> More examples
                </UiButton>
                <RouterLink
                    v-if="lexeme.lexeme_id"
                    :to="{ name: 'word.details', params: { id: lexeme.lexeme_id } }"
                    class="inline-flex h-11 items-center gap-1.5 rounded-md px-3 text-sm text-primary hover:underline"
                >
                    <ExternalLink :size="16" /> Word page
                </RouterLink>
            </div>
        </div>
    </div>
</template>
