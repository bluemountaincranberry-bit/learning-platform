<script setup lang="ts">
import { BookPlus, Check, Eye, EyeOff, Lightbulb, LoaderCircle, Minus, Plus, Undo2 } from 'lucide-vue-next';
import UiBadge from './UiBadge.vue';
import UiButton from './UiButton.vue';
import WordRow from './WordRow.vue';
import { formatGrammarFeatures } from '../grammarFeatures';
import { groupAssociationsByType } from '../lexemeAssociations';
import type { LexemeWithLearned } from '../../types';

/**
 * One word in a content's word list (ContentDetailsPage.vue, StudyPage.vue).
 *
 * VIK-38: a compact, full-width row so ~8+ words fit on a phone screen —
 * checkbox · pronunciation · word + translation · practice · known. Level,
 * confidence, associations, examples and secondary actions open with the
 * word details, so scanning and picking words stays compact.
 */
defineProps<{
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


</script>

<template>
    <WordRow :text="lexeme.text" :translation="lexeme.translation" :level="lexeme.level" level-in-details :lexeme-id="lexeme.lexeme_id" :language="language" :examples="lexeme.examples" :example="lexeme.example" :selectable="selectable" :selected="selected" :default-expanded="defaultExpanded" @toggle-select="emit('toggleSelect', lexeme)">
        <template #row-actions>
            <UiBadge v-if="lexeme.learned" tone="success" class="shrink-0" title="You've marked this word as learned">Learned</UiBadge>
            <!-- 44px tap target, lighter 32px visual so a long list doesn't read as a wall of buttons. -->
            <UiButton
                v-if="lexeme.in_review"
                variant="ghost"
                size="icon-touch"
                class="shrink-0"
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
                size="icon-touch"
                class="shrink-0"
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
                size="icon-touch"
                class="shrink-0"
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
                size="icon-touch"
                class="shrink-0 text-success-fg"
                :disabled="marking"
                aria-label="I know this word — mark as learned"
                title="I know this word — mark as learned"
                @click="emit('markLearned', lexeme)"
            >
                <Check :size="18" />
            </UiButton>
        </template>
            <div class="flex flex-wrap items-center gap-1.5">
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
                <UiBadge v-if="lexeme.skipped" tone="neutral" title="You hid this word from your list">Hidden</UiBadge>
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
            <div v-for="group in groupAssociationsByType(lexeme.associations)" :key="group.type" class="flex flex-wrap items-center gap-1.5">
                <span class="text-xs text-muted-foreground">{{ group.label }}:</span>
                <UiBadge v-for="item in group.items" :key="item" :tone="group.tone">{{ item }}</UiBadge>
            </div>
            <p v-if="fetchingExamples" class="flex items-center gap-2 px-1 py-1 text-xs text-muted-foreground" role="status">
                <LoaderCircle :size="14" class="animate-spin text-primary" aria-hidden="true" /> Loading more examples…
            </p>
        <template #actions>
                <UiButton v-if="lexeme.skipped" variant="ghost" size="touch" :disabled="marking" @click="emit('unskip', lexeme)">
                    <Eye :size="16" /> Show again
                </UiButton>
                <UiButton v-else variant="ghost" size="touch" :disabled="marking" title="Hide this word from your list" @click="emit('skip', lexeme)">
                    <EyeOff :size="16" /> Hide
                </UiButton>
                <UiButton v-if="!aiUnavailable" variant="ghost" size="touch" :disabled="explaining" @click="emit('explain', lexeme)">
                    <Lightbulb :size="16" :class="{ 'animate-pulse': explaining }" /> Explain
                </UiButton>
                <UiButton v-if="!aiUnavailable && lexeme.lexeme_id" variant="ghost" size="touch" :disabled="fetchingExamples" @click="emit('moreExamples', lexeme)">
                    <BookPlus :size="16" :class="{ 'animate-pulse': fetchingExamples }" /> More examples
                </UiButton>
        </template>
    </WordRow>
</template>
