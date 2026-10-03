<script setup lang="ts">
import { BookPlus, Check, Eye, EyeOff, Lightbulb, Minus, Plus, Undo2 } from 'lucide-vue-next';
import UiBadge from './UiBadge.vue';
import UiButton from './UiButton.vue';
import SpeakButton from './SpeakButton.vue';
import WordExamples from './WordExamples.vue';
import { formatGrammarFeatures } from '../grammarFeatures';
import { groupAssociationsByType } from '../lexemeAssociations';
import type { LexemeWithLearned } from '../../types';

const props = defineProps<{
    lexeme: LexemeWithLearned;
    language?: string;
    marking?: boolean;
    startingReview?: boolean;
    explaining?: boolean;
    aiUnavailable?: boolean;
    /** Task 7.4: bulk selection. Only StudyPage.vue opts into this — when
     * false/omitted (e.g. ContentDetailsPage.vue's word list), no checkbox
     * renders at all. */
    selectable?: boolean;
    selected?: boolean;
    fetchingExamples?: boolean;
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

const associationGroups = () => groupAssociationsByType(props.lexeme.associations);
</script>

<template>
    <div
        class="flex flex-col gap-2 rounded-spa border border-border p-2.5 sm:flex-row sm:items-start sm:justify-between"
        :class="lexeme.learned || lexeme.skipped ? 'bg-muted/60' : 'bg-surface'"
    >
        <div v-if="selectable" class="shrink-0 pt-1">
            <input
                type="checkbox"
                class="h-4 w-4 rounded border-border accent-primary"
                :checked="selected"
                :aria-label="`Select ${lexeme.text}`"
                @change="emit('toggleSelect', lexeme)"
            />
        </div>
        <div class="min-w-0 flex-1 space-y-1">
            <div class="flex flex-wrap items-center gap-1.5">
                <SpeakButton :text="lexeme.text" :language="language" />
                <RouterLink
                    v-if="lexeme.lexeme_id"
                    :to="{ name: 'word.details', params: { id: lexeme.lexeme_id } }"
                    class="font-medium text-fg hover:text-primary hover:underline"
                >
                    {{ lexeme.text }}
                </RouterLink>
                <span v-else class="font-medium text-fg">{{ lexeme.text }}</span>
                <UiBadge tone="neutral">{{ lexeme.type }}</UiBadge>
                <UiBadge v-if="lexeme.level" tone="primary" title="CEFR level">{{ lexeme.level }}</UiBadge>
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
                <UiBadge v-if="lexeme.skipped" tone="neutral" title="You marked this word as not interested">
                    Not interested
                </UiBadge>
                <UiBadge
                    v-if="formatGrammarFeatures(lexeme.grammar_features)"
                    tone="neutral"
                    title="Grammar of this specific occurrence"
                >
                    {{ formatGrammarFeatures(lexeme.grammar_features) }}
                </UiBadge>
            </div>
            <div v-if="lexeme.translation" class="text-sm font-medium text-fg-secondary">
                {{ lexeme.translation }}
                <span v-if="lexeme.sense_gloss" class="font-normal text-muted-foreground">({{ lexeme.sense_gloss }})</span>
            </div>
            <div v-if="lexeme.confidence" class="flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-muted-foreground" title="Skill confidence from practice">
                <span>Recognition {{ lexeme.confidence.recognition }}%</span>
                <span>Recall {{ lexeme.confidence.recall }}%</span>
                <span>Listening {{ lexeme.confidence.listening }}%</span>
            </div>
            <div
                v-for="group in associationGroups()"
                :key="group.type"
                class="flex flex-wrap items-center gap-1.5"
            >
                <span class="text-xs text-muted-foreground">{{ group.label }}:</span>
                <UiBadge v-for="item in group.items" :key="item" :tone="group.tone">{{ item }}</UiBadge>
            </div>
            <WordExamples :examples="lexeme.examples" :fallback-example="lexeme.example" :language="language" />
        </div>
        <div class="flex shrink-0 items-center gap-1.5">
            <UiButton
                v-if="lexeme.skipped"
                variant="ghost"
                size="icon"
                class="h-9 w-9"
                :disabled="marking"
                aria-label="Show this word again"
                title="Show this word again"
                @click="emit('unskip', lexeme)"
            >
                <Eye :size="16" />
            </UiButton>
            <UiButton
                v-else
                variant="ghost"
                size="icon"
                class="h-9 w-9"
                :disabled="marking"
                aria-label="Not interested — hide this word"
                title="Not interested — hide this word from your list"
                @click="emit('skip', lexeme)"
            >
                <EyeOff :size="16" />
            </UiButton>

            <UiButton
                v-if="!aiUnavailable"
                variant="ghost"
                size="icon"
                class="h-9 w-9"
                :disabled="explaining"
                :aria-label="explaining ? 'Loading explanation…' : 'See what this word means, with an example'"
                title="See what this word means, with an example"
                @click="emit('explain', lexeme)"
            >
                <Lightbulb :size="16" :class="{ 'animate-pulse': explaining }" />
            </UiButton>
            <UiButton
                v-if="!aiUnavailable && lexeme.lexeme_id"
                variant="ghost"
                size="icon"
                class="h-9 w-9"
                :disabled="fetchingExamples"
                :aria-label="fetchingExamples ? 'Loading more examples…' : 'Get more example sentences for this word'"
                title="Get more example sentences for this word"
                @click="emit('moreExamples', lexeme)"
            >
                <BookPlus :size="16" :class="{ 'animate-pulse': fetchingExamples }" />
            </UiButton>

            <UiBadge v-if="lexeme.learned" tone="success" title="You've marked this word as learned">
                Learned
            </UiBadge>
            <UiButton
                v-else-if="lexeme.in_review"
                variant="secondary"
                size="icon"
                class="h-9 w-9"
                :disabled="startingReview"
                aria-label="Remove from your learning queue"
                title="Remove from your spaced-repetition learning queue"
                @click="emit('stopReview', lexeme)"
            >
                <Minus :size="16" />
            </UiButton>
            <UiButton
                v-else
                variant="primary"
                size="icon"
                class="h-9 w-9"
                :disabled="startingReview"
                aria-label="Add to your learning queue"
                title="Add to your spaced-repetition learning queue, to practice it later on the Repetitions page"
                @click="emit('startReview', lexeme)"
            >
                <Plus :size="16" />
            </UiButton>

            <UiButton
                v-if="lexeme.learned"
                variant="secondary"
                size="icon"
                class="h-9 w-9"
                :disabled="marking"
                aria-label="Remove from learned words"
                title="Remove from learned words"
                @click="emit('unmarkLearned', lexeme)"
            >
                <Undo2 :size="16" />
            </UiButton>
            <UiButton
                v-else
                variant="success"
                size="icon"
                class="h-9 w-9"
                :disabled="marking"
                aria-label="Mark this word as learned"
                title="Mark this word as learned — you already know it"
                @click="emit('markLearned', lexeme)"
            >
                <Check :size="16" />
            </UiButton>
        </div>
    </div>
</template>
