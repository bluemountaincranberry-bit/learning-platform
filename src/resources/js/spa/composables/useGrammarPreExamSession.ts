import { ref, computed } from 'vue';
import { grammarPreExamApi } from '../domains/learning/api/grammarPreExamApi';
import { sentencePracticeApi } from '../domains/ai';
import type { GrammarPreExamCard, GrammarPreExamResultItem, GrammarPreExamType, GrammarPreExamAttemptGroup } from '../domains/learning/api/grammarPreExamApi';
import type { SentencePracticeCheckResponse } from '../domains/ai';

type Phase = 'select' | 'loading' | 'session' | 'summary' | 'error' | 'empty';

/**
 * Grammar warm-up session: mirrors useContentExamSession's "generate once,
 * grade each card via the shared sentence-practice check endpoint, submit
 * the batch at the end" shape, but starts from an explicit topic selection
 * (`start()` takes the chosen grammar_rule_ids) instead of auto-starting,
 * and groups the final submission's response by rule so the summary can
 * show a per-topic confidence change rather than one pass/fail.
 */
export function useGrammarPreExamSession(contentId: number) {
    const phase = ref<Phase>('select');
    const error = ref('');
    const busy = ref(false);
    const type = ref<GrammarPreExamType>('pre');

    const queue = ref<GrammarPreExamCard[]>([]);
    const currentIndex = ref(0);
    const results = ref<GrammarPreExamResultItem[]>([]);
    const attemptGroups = ref<GrammarPreExamAttemptGroup[]>([]);

    const currentCard = computed<GrammarPreExamCard | null>(() => queue.value[currentIndex.value] ?? null);
    const sessionTotal = computed(() => queue.value.length);
    const isLastCard = computed(() => currentIndex.value >= sessionTotal.value - 1);

    async function start(selectedType: GrammarPreExamType, grammarRuleIds: number[]) {
        if (grammarRuleIds.length === 0) return;
        type.value = selectedType;
        phase.value = 'loading';
        error.value = '';
        try {
            const data = await grammarPreExamApi.start(contentId, grammarRuleIds);
            queue.value = data.cards;
            currentIndex.value = 0;
            results.value = [];
            attemptGroups.value = [];
            phase.value = data.cards.length === 0 ? 'empty' : 'session';
        } catch (e: unknown) {
            const err = e as { response?: { data?: { message?: string } } };
            error.value = err.response?.data?.message ?? 'Failed to start the warm-up.';
            phase.value = 'error';
        }
    }

    async function submitAnswer(answer: string): Promise<SentencePracticeCheckResponse | null> {
        const card = currentCard.value;
        if (!card || busy.value || !answer.trim()) return null;
        busy.value = true;
        try {
            const result = await sentencePracticeApi.check({
                prompt_sentence: card.prompt_sentence,
                prompt_language: card.prompt_language,
                answer_language: card.answer_language,
                answer,
            });
            results.value.push({
                grammar_rule_id: card.grammar_rule_id,
                prompt_sentence: card.prompt_sentence,
                prompt_language: card.prompt_language,
                answer_language: card.answer_language,
                answer,
                correct: result.correct,
                model_answer: result.model_answer,
                hint_words: card.hint_words,
            });
            return result;
        } catch {
            error.value = 'Failed to grade that answer.';
            return null;
        } finally {
            busy.value = false;
        }
    }

    async function finish() {
        busy.value = true;
        error.value = '';
        try {
            const data = await grammarPreExamApi.complete(contentId, type.value, results.value);
            attemptGroups.value = data.attempts;
            phase.value = 'summary';
        } catch {
            error.value = 'Failed to save your warm-up results.';
        } finally {
            busy.value = false;
        }
    }

    async function next() {
        if (isLastCard.value) {
            await finish();
        } else {
            currentIndex.value += 1;
        }
    }

    function reset() {
        phase.value = 'select';
        error.value = '';
        queue.value = [];
        currentIndex.value = 0;
        results.value = [];
        attemptGroups.value = [];
    }

    return {
        phase,
        error,
        busy,
        type,
        currentCard,
        currentIndex,
        sessionTotal,
        attemptGroups,
        start,
        submitAnswer,
        next,
        reset,
    };
}
