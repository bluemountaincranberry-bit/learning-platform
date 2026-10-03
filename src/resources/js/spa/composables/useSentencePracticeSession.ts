import { ref, computed } from 'vue';
import { sentencePracticeApi } from '../domains/ai';
import type { SentencePracticeCard, SentencePracticeCheckMode, SentencePracticeDirection } from '../domains/ai';

type Phase = 'idle' | 'loading' | 'session' | 'summary' | 'empty';

const SESSION_SIZE = 5;

/**
 * AI-generated, AI-graded sentence-translation practice: unlike
 * useContextPracticeSession (reuses existing example sentences, self-graded)
 * this generates fresh sentences and has the AI judge each typed answer —
 * see SentencePracticeService for why this needs a real LLM call both ways
 * instead of self-report.
 *
 * `contentId`, when given, scopes generation to that content's own
 * words/grammar ("Reinforce" — see ContentDetailsPage) instead of the
 * learner's global recent-study pool; omitted, this is the general
 * `/practice/speaking` entry.
 */
export function useSentencePracticeSession(contentId?: number) {
    const phase = ref<Phase>('idle');
    const error = ref('');
    const note = ref('');
    const busy = ref(false);

    const direction = ref<SentencePracticeDirection>('to_target');
    const queue = ref<SentencePracticeCard[]>([]);
    const currentIndex = ref(0);
    const correctCount = ref(0);

    const currentCard = computed<SentencePracticeCard | null>(() => queue.value[currentIndex.value] ?? null);
    const sessionTotal = computed(() => queue.value.length);
    const isLastCard = computed(() => currentIndex.value >= sessionTotal.value - 1);

    async function startSession(newDirection: SentencePracticeDirection) {
        direction.value = newDirection;
        phase.value = 'loading';
        error.value = '';
        note.value = '';
        try {
            const data = await sentencePracticeApi.start(newDirection, SESSION_SIZE, contentId);
            queue.value = data.cards;
            currentIndex.value = 0;
            correctCount.value = 0;
            if (data.cards.length === 0) {
                note.value = data.note ?? '';
                phase.value = 'empty';
            } else {
                phase.value = 'session';
            }
        } catch {
            error.value = 'Failed to load speaking practice.';
            phase.value = 'empty';
        }
    }

    async function checkAnswer(answer: string, checkMode: SentencePracticeCheckMode = 'flexible') {
        const card = currentCard.value;
        if (!card || busy.value || !answer.trim()) return null;
        busy.value = true;
        try {
            const result = await sentencePracticeApi.check({
                prompt_sentence: card.prompt_sentence,
                prompt_language: card.prompt_language,
                answer_language: card.answer_language,
                answer,
                check_mode: checkMode,
            });
            if (result.correct) correctCount.value += 1;
            return result;
        } catch {
            error.value = 'Failed to grade that answer.';
            return null;
        } finally {
            busy.value = false;
        }
    }

    function advance() {
        if (isLastCard.value) {
            phase.value = 'summary';
        } else {
            currentIndex.value += 1;
        }
    }

    function restart() {
        void startSession(direction.value);
    }

    return {
        phase,
        error,
        note,
        busy,
        direction,
        currentCard,
        currentIndex,
        sessionTotal,
        correctCount,
        startSession,
        checkAnswer,
        advance,
        restart,
    };
}
