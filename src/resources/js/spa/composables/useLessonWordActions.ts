import { computed, ref, type ComputedRef, type Ref } from 'vue';
import { lessonApi, myWordsApi, type LessonDetail, type LessonLexemeCandidate } from '../domains/learning';
import type { BulkLexemeActionResponse, LexemeWithLearned } from '../types';

type LessonWordAction = 'start' | 'stop' | 'known' | 'unknown';
type LessonBulkAction = 'start' | 'stop' | 'known';

/** Maps lesson candidates onto the shared word row and owns their personal-list actions. */
export function useLessonWordActions(lesson: Ref<LessonDetail | null>, lessonId: ComputedRef<number>) {
    const itemSaving = ref(false);
    const itemError = ref('');
    const bulkPending = ref(false);
    const bulkMessage = ref('');

    const lexemes = computed<LexemeWithLearned[]>(() => (lesson.value?.lexemes ?? []).map((word, index) => ({
        id: word.id,
        lexeme_id: word.matched_lexeme_id,
        type: word.type,
        text: word.text,
        sort_order: index,
        level: word.level,
        learned: word.learned ?? false,
        in_review: word.in_review,
        translation: word.translation,
        example: word.example,
        examples: word.example ? [{ example: word.example, translation: word.example_translation, is_primary: true }] : [],
    })));

    function lessonWordById(id: number): LessonLexemeCandidate | undefined {
        return lesson.value?.lexemes.find((item) => item.id === id);
    }

    async function ensureInMyWords(item: LessonLexemeCandidate): Promise<number> {
        if (item.in_my_words && item.matched_lexeme_id !== null) return item.matched_lexeme_id;
        const result = await lessonApi.addLexemeToMyWords(lessonId.value, item.id);
        item.matched_lexeme_id = result.lexeme_id;
        item.in_my_words = result.in_my_words;
        item.in_review = result.in_review;
        item.status = result.status;
        return result.lexeme_id;
    }

    async function applyAction(item: LessonLexemeCandidate, action: LessonWordAction): Promise<void> {
        if (action === 'start') {
            const lexemeId = await ensureInMyWords(item);
            if (!item.in_review) await myWordsApi.startLearning(lexemeId);
            item.in_review = true;
        } else if (action === 'stop') {
            if (!item.in_review || item.matched_lexeme_id === null) return;
            await myWordsApi.stopLearning(item.matched_lexeme_id);
            item.in_review = false;
        } else if (action === 'known') {
            const lexemeId = await ensureInMyWords(item);
            await myWordsApi.markKnown(lexemeId);
            item.learned = true;
        } else if (item.matched_lexeme_id !== null) {
            await myWordsApi.unmarkKnown(item.matched_lexeme_id);
            item.learned = false;
        }
    }

    async function runAction(item: LessonLexemeCandidate, action: LessonWordAction): Promise<void> {
        itemSaving.value = true;
        itemError.value = '';
        try {
            await applyAction(item, action);
        } catch {
            itemError.value = action === 'known' ? 'Failed to mark this word as known. Try again.' : 'Failed to update this word. Try again.';
        } finally {
            itemSaving.value = false;
        }
    }

    async function runBulkAction(ids: number[], action: LessonBulkAction): Promise<BulkLexemeActionResponse> {
        const results = await Promise.all(ids.map(async (id) => {
            const item = lessonWordById(id);
            if (!item) return { id, ok: false };
            try {
                await applyAction(item, action);
                return { id, ok: true };
            } catch {
                return { id, ok: false };
            }
        }));
        return {
            results,
            succeeded: results.filter((result) => result.ok).length,
            failed: results.filter((result) => !result.ok).length,
        };
    }

    async function bulkAction(ids: number[], action: LessonBulkAction): Promise<BulkLexemeActionResponse> {
        bulkPending.value = true;
        try {
            const result = await runBulkAction(ids, action);
            const label = action === 'start' ? 'Added' : action === 'stop' ? 'Removed' : 'Marked';
            const destination = action === 'start' ? 'to practice' : action === 'stop' ? 'from practice' : 'as known';
            bulkMessage.value = `${label} ${result.succeeded} of ${ids.length} ${destination}${result.failed ? ` (${result.failed} failed)` : ''}.`;
            return result;
        } finally {
            bulkPending.value = false;
        }
    }

    return {
        lexemes,
        itemSaving,
        itemError,
        bulkPending,
        bulkMessage,
        lessonWordById,
        runAction,
        bulkAction,
    };
}
