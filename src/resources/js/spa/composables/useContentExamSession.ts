import { ref, computed } from 'vue';
import { contentReadinessApi } from '../domains/content/api/contentReadinessApi';
import type { ContentExamAttempt, ExamResultItem } from '../domains/content/api/contentReadinessApi';
import { sentencePracticeApi } from '../domains/ai';
import type { SentencePracticeCard, SentencePracticeCheckResponse } from '../domains/ai';

type Phase = 'loading' | 'blocked' | 'session' | 'summary' | 'error';

/**
 * "Ready to watch" exam session: a fixed-length, mixed-direction set of
 * cards from ContentReadinessController::examStart(), graded per-card via
 * the same server-side AI grading as the general Speaking Practice
 * (sentencePracticeApi.check), then submitted as a batch to
 * ContentReadinessController::examComplete() to persist the attempt and
 * (if passed) flip the content's readiness.
 */
export function useContentExamSession(contentId: number) {
    const phase = ref<Phase>('loading');
    const error = ref('');
    const busy = ref(false);

    const queue = ref<SentencePracticeCard[]>([]);
    const currentIndex = ref(0);
    const results = ref<ExamResultItem[]>([]);
    const finalAttempt = ref<ContentExamAttempt | null>(null);

    const currentCard = computed<SentencePracticeCard | null>(() => queue.value[currentIndex.value] ?? null);
    const sessionTotal = computed(() => queue.value.length);
    const isLastCard = computed(() => currentIndex.value >= sessionTotal.value - 1);

    async function start() {
        phase.value = 'loading';
        error.value = '';
        try {
            const data = await contentReadinessApi.startExam(contentId);
            queue.value = data.cards;
            currentIndex.value = 0;
            results.value = [];
            finalAttempt.value = null;
            phase.value = 'session';
        } catch (e: unknown) {
            const err = e as { response?: { status?: number; data?: { message?: string } } };
            if (err.response?.status === 409) {
                error.value = err.response.data?.message ?? 'Finish all words and grammar first.';
                phase.value = 'blocked';
            } else {
                error.value = err.response?.data?.message ?? 'Failed to start the exam.';
                phase.value = 'error';
            }
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
        try {
            finalAttempt.value = await contentReadinessApi.completeExam(contentId, results.value);
            phase.value = 'summary';
        } catch {
            error.value = 'Failed to save your exam result.';
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

    return {
        phase,
        error,
        busy,
        currentCard,
        currentIndex,
        sessionTotal,
        results,
        finalAttempt,
        start,
        submitAnswer,
        next,
    };
}
