import { ref, computed } from 'vue';
import { contentApi } from '../domains/content';
import { srsApi, trainingApi, selfCheckApi, type SelfCheckItem } from '../domains/learning';
import type { Content } from '../types';
import type { WordCardWord } from '../widgets/trainer/WordCard.vue';

/**
 * A session card's activity. There is deliberately no upfront "pick a mode"
 * step anymore (Task: full trainer redesign) — a single adaptive queue mixes
 * these, chosen automatically from what's due/new/already-learned for the
 * scope the session was started with.
 */
export type CardActivity = 'review' | 'learn' | 'quick-check' | 'cloze' | 'listening' | 'listen-recognize';
export type FocusedPracticeMode = 'adaptive' | 'cloze' | 'listening';

export interface SessionCard {
    activity: CardActivity;
    word: WordCardWord;
    /** review: the SrsCard being graded. */
    cardId?: number;
    /** learn / quick-check / cloze / listening: the ContentLexeme this card is about. */
    contentLexemeId?: number;
    contentId?: number;
    /** quick-check: shuffled translation choices, one of which is correct. cloze: shuffled word-form choices (only set when enough distractors exist), for the 'choose-word' answer style. */
    options?: string[];
    /** cloze: the example sentence split around the blanked-out target word. */
    clozeBefore?: string;
    clozeAfter?: string;
    replayCount?: number;
    selectionReason?: string;
    targetDimension?: string;
    retryId?: number;
}

export interface TrainingSourceScope {
    contentIds: number[];
    canonicalLexemeIds: number[];
    lessonWords: TrainingLessonWord[];
    label?: string;
}

export interface TrainingLessonWord {
    lexemeId: number;
    text: string;
    language: string;
    translation: string | null;
    level: string | null;
    example: string | null;
    exampleTranslation: string | null;
}

const LEARN_SESSION_SIZE = 8;
const REINFORCE_SESSION_SIZE = 6;
// Cap for an explicit word selection (e.g. "Practice these" from a word
// list) — generous enough to cover a real selection without turning a
// mis-click on "select all" into an unusably long single sitting.
const EXPLICIT_SESSION_CAP = 30;

function shuffle<T>(items: T[]): T[] {
    const copy = [...items];
    for (let i = copy.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [copy[i], copy[j]] = [copy[j], copy[i]];
    }
    return copy;
}

/** Finds `word` inside `example` (case-insensitive) and splits around it, for rendering a blank. */
function splitCloze(example: string, word: string): { before: string; after: string } | null {
    const idx = example.toLowerCase().indexOf(word.toLowerCase());
    if (idx === -1) return null;
    return { before: example.slice(0, idx), after: example.slice(idx + word.length) };
}

type Phase = 'loading' | 'session' | 'summary' | 'empty';

export function useTrainingSession() {
    const phase = ref<Phase>('loading');
    const loading = ref(false);
    const error = ref('');
    const busy = ref(false);

    const catalogContents = ref<Content[]>([]);
    const queue = ref<SessionCard[]>([]);
    const currentIndex = ref(0);

    const scopeContentId = ref<number | undefined>(undefined);
    const explicitScopeLabel = ref('');
    const scopeLabel = computed(() => {
        if (explicitScopeLabel.value) return explicitScopeLabel.value;
        if (scopeContentId.value === undefined) return 'Practice today';
        return catalogContents.value.find((c) => c.id === scopeContentId.value)?.title ?? 'This content';
    });

    const reviewedCount = ref(0);
    const learnedCount = ref(0);
    const addedToReviewCount = ref(0);
    const reinforceCorrect = ref(0);
    const reinforceTotal = ref(0);
    const pendingReinforceAnswers = ref<{ content_id: number; content_lexeme_id: number; known: boolean; error_type: string; hint_used: boolean; retry_id?: number; exercise_type?: string }[]>([]);
    const submissionOperationIds = ref(new Map<number, string>());

    const sessionTotal = computed(() => queue.value.length);
    const currentCard = computed<SessionCard | null>(() => queue.value[currentIndex.value] ?? null);
    const isLastCard = computed(() => currentIndex.value >= sessionTotal.value - 1);

    function languageOfContent(id: number | undefined): string | null {
        if (id === undefined) return null;
        return catalogContents.value.find((c) => c.id === id)?.language ?? null;
    }

    async function ensureCatalogLoaded() {
        if (catalogContents.value.length === 0) {
            const firstPage = await contentApi.getList({ page: 1, per_page: 100 });
            const lastPage = Number((firstPage.meta as { last_page?: number } | undefined)?.last_page ?? 1);
            const remainingPages = await Promise.all(Array.from(
                { length: Math.max(0, lastPage - 1) },
                (_, index) => contentApi.getList({ page: index + 2, per_page: 100 }),
            ));
            catalogContents.value = [
                ...(firstPage.data ?? []),
                ...remainingPages.flatMap((page) => page.data ?? []),
            ];
        }
    }

    function reviewCards(items: import('../types/api/TrainingReviewQueueResponse').TrainingReviewItem[]): SessionCard[] {
        return items.map((item) => ({
            activity: 'review' as const,
            cardId: item.card_id,
            contentLexemeId: item.content_lexeme_id ?? undefined,
            contentId: item.content_id ?? undefined,
            word: {
                lexeme_display: item.lexeme_display,
                lexeme_id: item.lexeme_id,
                part_of_speech: item.part_of_speech,
                level: item.level,
                language: languageOfContent(item.content_id ?? undefined),
                translation: item.translation,
                example: item.example,
                examples: item.examples,
                associations: item.associations,
            },
        }));
    }

    function selectedLexemeCards(items: import('../types/api/TrainingReviewQueueResponse').TrainingSelectedLexemeItem[]): SessionCard[] {
        return items.map((item) => ({
            activity: 'learn' as const,
            contentLexemeId: item.content_lexeme_id,
            contentId: item.content_id,
            word: {
                lexeme_display: item.lexeme_display,
                lexeme_id: item.lexeme_id,
                part_of_speech: item.part_of_speech,
                level: item.level,
                language: languageOfContent(item.content_id),
                translation: item.translation,
                example: item.example,
                examples: item.examples,
                associations: item.associations,
            },
        }));
    }

    /** `onlyIds`, when given, scopes to an explicit ContentLexeme selection (e.g. "Practice these" from a word list) instead of auto-picking the next `LEARN_SESSION_SIZE` new words. */
    async function learnCards(contentId: number, exclude: Set<number>, onlyIds?: Set<number>): Promise<SessionCard[]> {
        const data = await contentApi.getLexemes(contentId);
        const limit = onlyIds ? EXPLICIT_SESSION_CAP : LEARN_SESSION_SIZE;
        return (data.lexemes ?? [])
            .filter((l) => !l.learned && !exclude.has(l.id) && (!onlyIds || onlyIds.has(l.id)))
            .slice(0, limit)
            .map((l) => ({
                activity: 'learn' as const,
                contentLexemeId: l.id,
                contentId,
                word: {
                    lexeme_display: l.text,
                    lexeme_id: l.lexeme_id,
                    part_of_speech: l.part_of_speech,
                    level: l.level,
                    language: languageOfContent(contentId),
                    translation: l.translation,
                    example: l.example,
                    examples: l.examples,
                    associations: l.associations,
                },
            }));
    }

    /** Uses the server's adaptive recommendation and falls back to a local capability-aware choice. */
    function chooseReinforceActivity(
        item: SelfCheckItem,
        idx: number,
        pool: SelfCheckItem[],
    ): 'quick-check' | 'cloze' | 'listening' | 'listen-recognize' {
        const recommended = item.adaptive_activity;
        if (recommended === 'cloze' || recommended === 'listening' || recommended === 'quick-check') return recommended;
        if (recommended === 'recall' || recommended === 'recognition') return item.translation ? 'quick-check' : 'listen-recognize';
        if (recommended === 'encounter' || recommended === 'shadowing') return item.translation ? 'listen-recognize' : 'listening';

        const distractorCount = new Set(
            pool
                .filter((p) => p.content_lexeme_id !== item.content_lexeme_id && p.translation && p.translation !== item.translation)
                .map((p) => p.translation),
        ).size;

        const candidates: ('quick-check' | 'cloze' | 'listening' | 'listen-recognize')[] = [];
        if (item.translation && distractorCount >= 1) candidates.push('quick-check');
        // Cloze can be generated on demand from the card itself when no
        // stored example exists; do not silently remove the activity.
        candidates.push('cloze');
        candidates.push('listening');
        if (item.translation) candidates.push('listen-recognize');
        return candidates[idx % candidates.length];
    }

    async function reinforceCards(contentId: number, exclude: Set<number>): Promise<SessionCard[]> {
        let pool: SelfCheckItem[] = [];
        try {
            const data = await selfCheckApi.start(contentId, REINFORCE_SESSION_SIZE + exclude.size);
            pool = (data.items ?? []).filter((i) => !exclude.has(i.content_lexeme_id));
        } catch {
            return [];
        }

        return pool.slice(0, REINFORCE_SESSION_SIZE).map((item, idx) => {
            const activity = chooseReinforceActivity(item, idx, pool);
            const card: SessionCard = {
                activity,
                contentLexemeId: item.content_lexeme_id,
                contentId,
                word: {
                    lexeme_display: item.lexeme_display,
                    part_of_speech: item.part_of_speech,
                    level: item.level,
                    language: languageOfContent(contentId),
                    translation: item.translation,
                    example: item.example,
                    examples: item.examples,
                },
                selectionReason: item.selection_reason,
                targetDimension: item.target_dimension,
                retryId: item.retry_id ?? undefined,
            };

            if (activity === 'quick-check') {
                const distractors = shuffle([
                    ...new Set(
                        pool
                            .filter((p) => p.content_lexeme_id !== item.content_lexeme_id && p.translation && p.translation !== item.translation)
                            .map((p) => p.translation as string),
                    ),
                ]).slice(0, 3);
                card.options = shuffle([item.translation as string, ...distractors]);
            }

            if (activity === 'cloze') {
                const sentence = item.example ?? item.examples?.[0]?.example ?? '';
                const split = splitCloze(sentence, item.lexeme_display);
                card.clozeBefore = split?.before ?? '';
                card.clozeAfter = split?.after ?? sentence.slice(item.lexeme_display.length);

                const wordDistractors = shuffle([
                    ...new Set(
                        pool
                            .filter((p) => p.content_lexeme_id !== item.content_lexeme_id && p.lexeme_display !== item.lexeme_display)
                            .map((p) => p.lexeme_display),
                    ),
                ]).slice(0, 3);
                if (wordDistractors.length > 0) card.options = shuffle([item.lexeme_display, ...wordDistractors]);
            }

            return card;
        });
    }

    /**
     * Builds and starts one adaptive session. `contentId` present = the
     * content-scoped session a "Study words" / content card entry point
     * wants (due reviews + new words + reinforcement for that content,
     * all in one queue, no extra picking). `contentId` undefined = the
     * global "Practice today" entry (due reviews across everything + new
     * words from whichever content needs it most).
     *
     * `lexemeIds`, when given alongside `contentId`, requests a focused
     * session on exactly those ContentLexeme ids instead — the "Practice
     * these" path from a word list's selection — skipping the due-reviews
     * and reinforcement mix so the learner gets only what they picked.
     */
    function applyFocusedMode(cards: SessionCard[], mode: FocusedPracticeMode): SessionCard[] {
        if (mode === 'adaptive') return cards;
        return cards.map((card, index) => {
            if (mode === 'listening') return { ...card, activity: 'listening' as const };

            const sentence = card.word.example ?? card.word.examples?.[0]?.example ?? '';
            const split = splitCloze(sentence, card.word.lexeme_display);
            const distractors = shuffle(
                cards
                    .filter((candidate, candidateIndex) => candidateIndex !== index && candidate.word.lexeme_display !== card.word.lexeme_display)
                    .map((candidate) => candidate.word.lexeme_display),
            ).slice(0, 3);
            return {
                ...card,
                activity: 'cloze' as const,
                clozeBefore: split?.before ?? '',
                clozeAfter: split?.after ?? '',
                options: card.word.lexeme_display ? shuffle([card.word.lexeme_display, ...distractors]) : undefined,
            };
        });
    }

    async function startSession(contentId?: string | number | TrainingSourceScope, lexemeIds?: number[], mode: FocusedPracticeMode = 'adaptive') {
        loading.value = true;
        error.value = '';
        phase.value = 'loading';
        try {
            await ensureCatalogLoaded();
            let cards: SessionCard[];

            if (typeof contentId === 'object' && contentId !== null) {
                const contentIds = [...new Set(contentId.contentIds.filter((id) => Number.isInteger(id) && id > 0))];
                const selectedCards: SessionCard[] = [];
                let remainingNewSlots = LEARN_SESSION_SIZE;
                let remainingReinforceSlots = REINFORCE_SESSION_SIZE;
                for (const selectedContentId of contentIds) {
                    const reviewData = await trainingApi.getReviewQueue(selectedContentId);
                    const [learned, reinforce] = await Promise.all([
                        remainingNewSlots > 0 ? learnCards(selectedContentId, new Set()) : Promise.resolve([]),
                        remainingReinforceSlots > 0 ? reinforceCards(selectedContentId, new Set()) : Promise.resolve([]),
                    ]);
                    selectedCards.push(...reviewCards(reviewData.items ?? []));
                    const selectedNew = learned.slice(0, remainingNewSlots);
                    remainingNewSlots -= selectedNew.length;
                    selectedCards.push(...selectedNew);
                    const selectedReinforce = reinforce.slice(0, remainingReinforceSlots);
                    remainingReinforceSlots -= selectedReinforce.length;
                    selectedCards.push(...selectedReinforce);
                }
                const canonicalIds = [...new Set(contentId.canonicalLexemeIds.filter((id) => Number.isInteger(id) && id > 0))];
                if (canonicalIds.length > 0) {
                    const canonicalLexemes = await trainingApi.getSelectedCanonicalLexemes(canonicalIds);
                    const lessonWords = new Map(contentId.lessonWords.map((word) => [word.lexemeId, word]));
                    selectedCards.push(...(canonicalLexemes.items ?? []).map((item) => ({
                        activity: 'review' as const,
                        cardId: item.card_id,
                        contentLexemeId: undefined,
                        contentId: undefined,
                        word: {
                            lexeme_display: lessonWords.get(item.lexeme_id)?.text ?? item.lexeme_display,
                            lexeme_id: item.lexeme_id,
                            language: lessonWords.get(item.lexeme_id)?.language ?? item.language,
                            part_of_speech: item.part_of_speech,
                            level: lessonWords.get(item.lexeme_id)?.level ?? item.level,
                            translation: lessonWords.get(item.lexeme_id)?.translation ?? item.translation,
                            example: lessonWords.get(item.lexeme_id)?.example ?? item.example,
                            examples: lessonWords.get(item.lexeme_id)?.example
                                ? [{ example: lessonWords.get(item.lexeme_id)!.example!, translation: lessonWords.get(item.lexeme_id)!.exampleTranslation, is_primary: true }]
                                : item.examples,
                            associations: item.associations,
                        },
                    })));
                }
                const seenLexemes = new Set<number>();
                cards = selectedCards.filter((card) => {
                    const identity = card.word.lexeme_id ?? card.contentLexemeId;
                    if (identity === undefined) return true;
                    if (seenLexemes.has(identity)) return false;
                    seenLexemes.add(identity);
                    return true;
                });
                scopeContentId.value = undefined;
                explicitScopeLabel.value = contentId.label ?? 'Selected sources';
            } else {
                const cid = contentId !== undefined && contentId !== '' ? Number(contentId) : undefined;
                scopeContentId.value = cid;
                explicitScopeLabel.value = '';

            if (lexemeIds && lexemeIds.length > 0 && cid === undefined) {
                const data = await trainingApi.getSelectedLexemes(lexemeIds);
                cards = selectedLexemeCards(data.items ?? []);
            } else if (cid !== undefined && lexemeIds && lexemeIds.length > 0) {
                cards = await learnCards(cid, new Set(), new Set(lexemeIds));
            } else {
                const reviewData = await trainingApi.getReviewQueue(cid);
                const reviewItems = reviewData.items ?? [];
                const dueLexemeIds = new Set(reviewItems.map((i) => i.content_lexeme_id).filter((id): id is number => id !== null));

                cards = reviewCards(reviewItems);

                if (cid !== undefined) {
                    const [learn, reinforce] = await Promise.all([learnCards(cid, dueLexemeIds), reinforceCards(cid, dueLexemeIds)]);
                    cards = [...cards, ...learn, ...reinforce];
                } else {
                    const unlearnedCount = (c: Content) => (c.total_lexemes ?? 0) - (c.learned_count ?? 0);
                    const candidate = [...catalogContents.value]
                        .filter((c) => unlearnedCount(c) > 0)
                        .sort((a, b) => unlearnedCount(b) - unlearnedCount(a))[0];
                    if (candidate) {
                        cards = [...cards, ...(await learnCards(candidate.id, dueLexemeIds))];
                    }
                }
            }
            }

            queue.value = applyFocusedMode(cards, mode);
            currentIndex.value = 0;
            reviewedCount.value = 0;
            learnedCount.value = 0;
            addedToReviewCount.value = 0;
            reinforceCorrect.value = 0;
            reinforceTotal.value = 0;
            pendingReinforceAnswers.value = [];
            submissionOperationIds.value = new Map();

            phase.value = queue.value.length > 0 ? 'session' : 'empty';
        } catch {
            error.value = 'Failed to load practice session.';
            phase.value = 'empty';
        } finally {
            loading.value = false;
        }
    }

    async function advance() {
        if (isLastCard.value) {
            await finishSession();
        } else {
            currentIndex.value += 1;
        }
    }

    async function finishSession() {
        if (pendingReinforceAnswers.value.length > 0) {
            const byContent = new Map<number, { content_lexeme_id: number; known: boolean; error_type: string; hint_used: boolean; retry_id?: number; exercise_type?: string }[]>();
            for (const answer of pendingReinforceAnswers.value) {
                const list = byContent.get(answer.content_id) ?? [];
                list.push({ content_lexeme_id: answer.content_lexeme_id, known: answer.known, error_type: answer.error_type, hint_used: answer.hint_used, retry_id: answer.retry_id, exercise_type: answer.exercise_type });
                byContent.set(answer.content_id, list);
            }
            try {
                await Promise.all(
                    Array.from(byContent.entries()).map(([content_id, answers]) => {
                        const operationId = submissionOperationIds.value.get(content_id) ?? `session-${Date.now()}-${Math.random().toString(36).slice(2)}`;
                        submissionOperationIds.value.set(content_id, operationId);
                        return selfCheckApi.submit({ content_id, operation_id: operationId, answers });
                    }),
                );
            } catch {
                error.value = 'Failed to save some practice results.';
            }
        }
        phase.value = 'summary';
    }

    async function submitReviewGrade(gradeOrPayload: number | { grade: number; hintUsed: boolean }, hintUsed = false) {
        const grade = typeof gradeOrPayload === 'number' ? gradeOrPayload : gradeOrPayload.grade;
        hintUsed = typeof gradeOrPayload === 'number' ? hintUsed : gradeOrPayload.hintUsed;
        const card = currentCard.value;
        if (!card || card.activity !== 'review' || card.cardId === undefined || busy.value) return;
        busy.value = true;
        try {
            await srsApi.review(card.cardId, grade, {
                content_lexeme_id: card.contentLexemeId,
                exercise_type: 'review',
                hint_used: hintUsed,
            });
            reviewedCount.value += 1;
            await advance();
        } catch {
            error.value = 'Failed to submit review.';
        } finally {
            busy.value = false;
        }
    }

    async function submitLearnMarkLearned() {
        const card = currentCard.value;
        if (!card || card.activity !== 'learn' || card.contentLexemeId === undefined || busy.value) return;
        busy.value = true;
        try {
            await contentApi.markLexemeLearned(card.contentLexemeId);
            learnedCount.value += 1;
            await advance();
        } catch {
            error.value = 'Failed to mark learned.';
        } finally {
            busy.value = false;
        }
    }

    async function submitLearnStartLearning() {
        const card = currentCard.value;
        if (!card || card.activity !== 'learn' || card.contentLexemeId === undefined || busy.value) return;
        busy.value = true;
        try {
            await contentApi.startLexemeLearning(card.contentLexemeId);
            addedToReviewCount.value += 1;
            await advance();
        } catch {
            error.value = 'Failed to add to reviews.';
        } finally {
            busy.value = false;
        }
    }

    async function submitReinforceResult(result: boolean | { correct: boolean; hintUsed: boolean }) {
        const correct = typeof result === 'boolean' ? result : result.correct;
        const hintUsed = typeof result === 'boolean' ? false : result.hintUsed;
        const card = currentCard.value;
        if (!card || card.contentLexemeId === undefined || card.contentId === undefined || busy.value) return;
        const errorType = card.activity === 'listening'
            ? 'could_not_hear'
            : card.activity === 'cloze'
                ? 'incorrect_production'
                : card.activity === 'quick-check'
                    ? 'unknown_meaning'
                    : 'recognition_miss';
        pendingReinforceAnswers.value.push({
            content_id: card.contentId,
            content_lexeme_id: card.contentLexemeId,
            known: correct,
            error_type: correct ? '' : errorType,
            hint_used: hintUsed,
            retry_id: card.retryId,
            exercise_type: card.activity,
        });
        if (!correct && (card.replayCount ?? 0) < 1) {
            const retryIndex = Math.min(queue.value.length, currentIndex.value + 4);
            queue.value.splice(retryIndex, 0, { ...card, replayCount: (card.replayCount ?? 0) + 1 });
        }
        reinforceTotal.value += 1;
        if (correct) reinforceCorrect.value += 1;
        await advance();
    }

    async function prepareClozeExamples(): Promise<{ prepared: number; total: number }> {
        const ids = [...new Set(
            queue.value
                .filter((card) => card.activity === 'cloze' && card.contentLexemeId !== undefined)
                .map((card) => card.contentLexemeId as number),
        )].slice(0, 10);
        if (ids.length === 0) return { prepared: 0, total: 0 };
        return contentApi.prepareClozeExamples(ids, 3);
    }

    function restart() {
        void startSession(scopeContentId.value);
    }

    return {
        phase,
        loading,
        error,
        busy,
        queue,
        currentIndex,
        currentCard,
        sessionTotal,
        scopeContentId,
        scopeLabel,
        catalogContents,
        reviewedCount,
        learnedCount,
        addedToReviewCount,
        reinforceCorrect,
        reinforceTotal,
        ensureCatalogLoaded,
        startSession,
        submitReviewGrade,
        submitLearnMarkLearned,
        submitLearnStartLearning,
        submitReinforceResult,
        prepareClozeExamples,
        restart,
    };
}
