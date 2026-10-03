import { computed, getCurrentInstance, onBeforeUnmount, ref } from 'vue';
import { grammarPracticeApi } from '../domains/learning/api/grammarPracticeApi';
import type {
    GrammarPracticeCheckResponse,
    GrammarPracticeExercise,
    GrammarPracticeLevel,
    GrammarPracticeResult,
    GrammarPracticeResultItem,
} from '../types';

export type GrammarRoundPhase = 'loading' | 'preparing' | 'unavailable' | 'answering' | 'settled' | 'saving' | 'result' | 'error';

export interface GrammarRoundOptions {
    ruleId: number;
    level: GrammarPracticeLevel;
    count: number;
    /** "Practice mistakes": replay exactly these exercises. */
    exerciseIds?: number[];
    contentId?: number | null;
    /** How often "Preparing exercises…" asks again, and for how long. */
    pollMs?: number;
    pollLimit?: number;
}

type Api = typeof grammarPracticeApi;

/**
 * One grammar practice round (VIK-31): asks the server for a round (waiting
 * while AI prepares exercises), then per exercise follows the VIK-32
 * contract — first wrong answer → hint and a retry, second wrong (or
 * "Show answer") → the answer. Every exercise ends with exactly one outcome;
 * the round is saved only when it is finished.
 */
export function useGrammarPracticeRound(options: GrammarRoundOptions, api: Api = grammarPracticeApi) {
    const phase = ref<GrammarRoundPhase>('loading');
    const unavailableReason = ref<'limited' | 'unavailable' | null>(null);
    const error = ref('');
    const busy = ref(false);
    let failedOn: 'start' | 'save' = 'start';

    const exercises = ref<GrammarPracticeExercise[]>([]);
    const index = ref(0);
    const attempt = ref<1 | 2>(1);
    const hint = ref<string | null>(null);
    const struckOptions = ref<number[]>([]);
    const settled = ref<GrammarPracticeCheckResponse | null>(null);
    const lastGiven = ref<string | null>(null);
    const items = ref<GrammarPracticeResultItem[]>([]);
    const result = ref<GrammarPracticeResult | null>(null);

    let shownAt = Date.now();
    let pollTimer: ReturnType<typeof setTimeout> | null = null;
    let polls = 0;

    const current = computed(() => exercises.value[index.value] ?? null);
    const total = computed(() => exercises.value.length);
    const answeredCount = computed(() => items.value.length);

    function resetExercise(): void {
        attempt.value = 1;
        hint.value = null;
        struckOptions.value = [];
        settled.value = null;
        lastGiven.value = null;
        shownAt = Date.now();
    }

    async function start(): Promise<void> {
        phase.value = polls === 0 ? 'loading' : 'preparing';
        error.value = '';
        try {
            const response = await api.startRound(options.ruleId, {
                level: options.level,
                count: options.count,
                ...(options.exerciseIds ? { exercise_ids: options.exerciseIds } : {}),
            });

            if (response.status === 'ready' && response.exercises.length > 0) {
                exercises.value = response.exercises;
                index.value = 0;
                items.value = [];
                resetExercise();
                phase.value = 'answering';
                return;
            }

            if (response.status === 'preparing' && polls < (options.pollLimit ?? 30)) {
                phase.value = 'preparing';
                polls++;
                pollTimer = setTimeout(() => void start(), options.pollMs ?? 2500);
                return;
            }

            unavailableReason.value = response.reason ?? 'unavailable';
            phase.value = 'unavailable';
        } catch {
            error.value = 'Could not start the round.';
            failedOn = 'start';
            phase.value = 'error';
        }
    }

    /** "Try again" on an error or unavailable screen: repeats whatever failed. */
    function retry(): Promise<void> {
        if (phase.value === 'error' && failedOn === 'save') return finish();
        polls = 0;
        unavailableReason.value = null;
        return start();
    }

    function record(outcome: GrammarPracticeResultItem['outcome'], given: string | null): void {
        if (!current.value) return;
        items.value.push({
            exercise_id: current.value.id,
            outcome,
            attempts: outcome === 'reported' ? attempt.value - 1 : attempt.value,
            given,
            ms: Date.now() - shownAt,
        });
    }

    async function check(given: string | null, showAnswer = false): Promise<void> {
        if (!current.value || phase.value !== 'answering' || busy.value) return;
        busy.value = true;
        try {
            const response = await api.check(current.value.id, {
                given,
                attempt: attempt.value,
                ...(showAnswer ? { show_answer: true } : {}),
            });
            lastGiven.value = given;

            if (response.outcome) {
                settled.value = response;
                record(response.outcome, given ?? null);
                phase.value = 'settled';
                return;
            }

            hint.value = response.hint ?? null;
            if (response.struck_option_index != null) struckOptions.value = [...struckOptions.value, response.struck_option_index];
            attempt.value = 2;
        } catch {
            error.value = 'Could not check the answer. Try again.';
        } finally {
            busy.value = false;
        }
    }

    function submit(given: string): Promise<void> {
        return check(given.trim() === '' ? null : given);
    }

    function showAnswer(): Promise<void> {
        return check(lastGiven.value, true);
    }

    async function next(): Promise<void> {
        if (index.value < exercises.value.length - 1) {
            index.value++;
            resetExercise();
            phase.value = 'answering';
            return;
        }
        await finish();
    }

    async function report(reason: string | null = null): Promise<void> {
        const exercise = current.value;
        if (!exercise || busy.value) return;
        busy.value = true;
        try {
            const { replacement } = await api.report(exercise.id, {
                level: options.level,
                round_exercise_ids: exercises.value.map((e) => e.id),
                reason,
            });
            record('reported', null);
            if (replacement) {
                exercises.value.splice(index.value, 1, replacement);
                resetExercise();
                phase.value = 'answering';
            } else {
                exercises.value.splice(index.value, 1);
                if (index.value < exercises.value.length) {
                    resetExercise();
                    phase.value = 'answering';
                } else {
                    busy.value = false;
                    await finish();
                }
            }
        } catch {
            error.value = 'Could not report the exercise.';
        } finally {
            busy.value = false;
        }
    }

    async function finish(): Promise<void> {
        if (!items.value.some((item) => item.outcome !== 'reported')) {
            // Nothing answered (every exercise was reported): nothing to save.
            result.value = null;
            phase.value = 'result';
            return;
        }
        phase.value = 'saving';
        try {
            result.value = await api.complete(options.ruleId, {
                level: options.level,
                items: items.value,
                content_id: options.contentId ?? null,
            });
            phase.value = 'result';
        } catch {
            error.value = 'Could not save the result.';
            failedOn = 'save';
            phase.value = 'error';
        }
    }

    /** "Practice mistakes": a new round over exactly these exercises. */
    function restart(exerciseIds: number[]): Promise<void> {
        stop();
        options.exerciseIds = exerciseIds;
        polls = 0;
        result.value = null;
        items.value = [];
        exercises.value = [];
        unavailableReason.value = null;
        return start();
    }

    function stop(): void {
        if (pollTimer) clearTimeout(pollTimer);
        pollTimer = null;
    }

    if (getCurrentInstance()) onBeforeUnmount(stop);

    return {
        phase,
        unavailableReason,
        error,
        busy,
        exercises,
        index,
        current,
        total,
        answeredCount,
        attempt,
        hint,
        struckOptions,
        settled,
        lastGiven,
        items,
        result,
        start,
        retry,
        submit,
        showAnswer,
        next,
        report,
        finish,
        restart,
        stop,
    };
}
