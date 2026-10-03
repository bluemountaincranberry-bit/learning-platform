import { ref, computed } from 'vue';
import { contentApi } from '../domains/content';
import { selfCheckApi } from '../domains/learning';
import type { Content } from '../types';
import type { LexemeExampleItem } from '../types/lexeme';

/**
 * One word's context-sentence card: a native-language sentence (the prompt)
 * paired with the target-language sentence it was translated from (the
 * answer to compare against once revealed). Built entirely from example
 * sentences the content-authoring AI pipeline already generates and already
 * translates into the learner's own `translation_language` — no separate
 * generation step needed for this mode.
 */
export interface ContextCard {
    contentLexemeId: number;
    lexemeDisplay: string;
    language: string | null;
    sentenceNative: string;
    sentenceTarget: string;
    /** False when no stored example sentence was usable for this word — ContextSentenceCard shows a "Generate a sentence" prompt instead of the normal Q/A flow until one is generated. */
    hasExample: boolean;
    /** True when the learner's last self-check on this word was "Needs work" (see LexemeWithLearned.needs_context_review) — startSession sorts these to the front of the queue. */
    needsWork: boolean;
}

type Phase = 'loading' | 'session' | 'summary' | 'empty';

/** Prefers the primary example, falling back to the lexeme's own single example/translation pair — skips words with no usable (sentence, translation) pair entirely, since this mode has nothing to build a card from otherwise. */
function pickSentence(
    examples: LexemeExampleItem[] | undefined,
    fallbackExample?: string | null,
    fallbackTranslation?: string | null,
): { example: string; translation: string } | null {
    const withTranslation = (examples ?? []).filter((e) => e.example && e.translation);
    const primary = withTranslation.find((e) => e.is_primary) ?? withTranslation[0];
    if (primary) return { example: primary.example, translation: primary.translation as string };
    if (fallbackExample && fallbackTranslation) return { example: fallbackExample, translation: fallbackTranslation };
    return null;
}

/**
 * A learner-picked-words session: unlike the adaptive queue in
 * useTrainingSession, every card here is explicitly chosen by the learner
 * (via "Practice in context" on a word list) and the exercise is always the
 * same shape — read a native-language sentence, produce its target-language
 * translation, compare against the real one, self-grade. If a word has no
 * stored example, the learner can generate one with AI in place. Kept as its own
 * composable rather than folded into useTrainingSession because the queue
 * source (explicit ids, not due/new picking) and the card shape (sentence
 * pairs, not single words) are both genuinely different.
 */
export function useContextPracticeSession() {
    const phase = ref<Phase>('loading');
    const error = ref('');
    const busy = ref(false);

    const contentId = ref<number | null>(null);
    const contentTitle = ref('');
    const queue = ref<ContextCard[]>([]);
    const currentIndex = ref(0);
    const correctCount = ref(0);
    const pendingAnswers = ref<{ content_lexeme_id: number; known: boolean }[]>([]);

    const currentCard = computed<ContextCard | null>(() => queue.value[currentIndex.value] ?? null);
    const sessionTotal = computed(() => queue.value.length);
    const isLastCard = computed(() => currentIndex.value >= sessionTotal.value - 1);

    async function startSession(cid: number, lexemeIds: number[]) {
        phase.value = 'loading';
        error.value = '';
        try {
            const [contentList, lexemeData] = await Promise.all([contentApi.getList({}), contentApi.getLexemes(cid)]);
            contentId.value = cid;
            const content = (contentList.data ?? []).find((c: Content) => c.id === cid);
            contentTitle.value = content?.title ?? 'Selected words';
            const language = content?.language ?? null;

            const idSet = new Set(lexemeIds);
            const cards = (lexemeData.lexemes ?? [])
                .filter((l) => idSet.has(l.id))
                .map((l): ContextCard => {
                    const sentence = pickSentence(l.examples, l.example, l.translation);
                    return {
                        contentLexemeId: l.id,
                        lexemeDisplay: l.text,
                        language,
                        sentenceNative: sentence?.translation ?? '',
                        sentenceTarget: sentence?.example ?? '',
                        hasExample: sentence !== null,
                        needsWork: !!l.needs_context_review,
                    };
                });

            // Prioritize retrying words last marked "Needs work" — a simple
            // filter-first reorder, not a scheduler (SrsCard/IntervalCalculator
            // already own real spaced repetition).
            queue.value = [...cards.filter((c) => c.needsWork), ...cards.filter((c) => !c.needsWork)];

            currentIndex.value = 0;
            correctCount.value = 0;
            pendingAnswers.value = [];
            phase.value = queue.value.length > 0 ? 'session' : 'empty';
        } catch (caught) {
            const responseMessage = (caught as { response?: { data?: { message?: string } } }).response?.data?.message;
            error.value = responseMessage ?? 'Failed to load context practice. Please try again.';
            phase.value = 'empty';
        }
    }

    async function finishSession() {
        busy.value = true;
        try {
            if (pendingAnswers.value.length > 0 && contentId.value !== null) {
                await selfCheckApi.submit({ content_id: contentId.value, answers: pendingAnswers.value });
            }
        } catch {
            error.value = 'Failed to save some practice results.';
        } finally {
            busy.value = false;
            phase.value = 'summary';
        }
    }

    async function submitResult(correct: boolean) {
        const card = currentCard.value;
        if (!card || busy.value) return;
        pendingAnswers.value.push({ content_lexeme_id: card.contentLexemeId, known: correct });
        if (correct) correctCount.value += 1;

        if (isLastCard.value) {
            await finishSession();
        } else {
            currentIndex.value += 1;
        }
    }

    const generating = ref(false);

    /**
     * On-demand AI sentence (enhancement on top of the default reused-examples
     * flow) — used when the current card has no usable stored example, or the
     * learner wants a different one. Replaces just the current card's sentence
     * pair in place; the rest of the queue and progress are untouched.
     */
    async function generateNewSentence() {
        const card = currentCard.value;
        if (!card || generating.value) return;
        generating.value = true;
        error.value = '';
        try {
            const { sentence, translation } = await contentApi.generateSentence(card.contentLexemeId);
            queue.value[currentIndex.value] = {
                ...card,
                sentenceNative: translation,
                sentenceTarget: sentence,
                hasExample: true,
            };
        } catch {
            error.value = 'Failed to generate a new sentence.';
        } finally {
            generating.value = false;
        }
    }

    return {
        phase,
        error,
        busy,
        generating,
        contentTitle,
        currentCard,
        currentIndex,
        sessionTotal,
        correctCount,
        startSession,
        submitResult,
        generateNewSentence,
    };
}
