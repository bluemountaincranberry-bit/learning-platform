import { ref, computed } from 'vue';
import type { LexemeWithLearned, BulkLexemeActionResponse } from '../types';
import { contentApi } from '../domains/content/api/contentApi';
import { dictionaryApi } from '../domains/content/api/dictionaryApi';

export function useLexemes() {
    const loading = ref(false);
    const error = ref('');
    const lexemes = ref<LexemeWithLearned[]>([]);
    const markingId = ref<number | null>(null);
    const startingReviewId = ref<number | null>(null);
    const explainingId = ref<number | null>(null);
    const explainError = ref('');
    const aiUnavailable = ref(false);
    const bulkActionPending = ref(false);
    const fetchingExamplesId = ref<number | null>(null);

    const toLearn = computed(() => lexemes.value.filter((l) => !l.learned && !l.skipped));
    const learned = computed(() => lexemes.value.filter((l) => l.learned));

    async function loadLexemes(contentId: string | number): Promise<void> {
        if (contentId == null) return;
        loading.value = true;
        error.value = '';
        try {
            const data = await contentApi.getLexemes(contentId);
            lexemes.value = data.lexemes ?? [];
        } catch (e: unknown) {
            const err = e as { response?: { status?: number; data?: { message?: string } } };
            if (err.response?.status === 401) throw e;
            const status = err.response?.status;
            error.value = err.response?.data?.message ?? (status ? `Failed to load words (HTTP ${status}).` : 'Failed to load words.');
        } finally {
            loading.value = false;
        }
    }

    async function markLearned(lexeme: LexemeWithLearned): Promise<void> {
        if (lexeme.learned) return;
        markingId.value = lexeme.id;
        try {
            await contentApi.markLexemeLearned(lexeme.id);
            lexeme.learned = true;
        } catch {
            error.value = 'Failed to mark as learned.';
        } finally {
            markingId.value = null;
        }
    }

    async function unmarkLearned(lexeme: LexemeWithLearned): Promise<void> {
        if (!lexeme.learned) return;
        markingId.value = lexeme.id;
        try {
            await contentApi.unmarkLexemeLearned(lexeme.id);
            lexeme.learned = false;
        } catch {
            error.value = 'Failed to remove from learned.';
        } finally {
            markingId.value = null;
        }
    }

    async function startLearning(lexeme: LexemeWithLearned): Promise<void> {
        if (lexeme.in_review) return;
        startingReviewId.value = lexeme.id;
        try {
            await contentApi.startLexemeLearning(lexeme.id);
            lexeme.in_review = true;
        } catch {
            error.value = 'Failed to add to reviews.';
        } finally {
            startingReviewId.value = null;
        }
    }

    async function stopLearning(lexeme: LexemeWithLearned): Promise<void> {
        if (!lexeme.in_review) return;
        startingReviewId.value = lexeme.id;
        try {
            await contentApi.stopLexemeLearning(lexeme.id);
            lexeme.in_review = false;
        } catch {
            error.value = 'Failed to remove from reviews.';
        } finally {
            startingReviewId.value = null;
        }
    }

    async function skip(lexeme: LexemeWithLearned): Promise<void> {
        if (lexeme.skipped) return;
        markingId.value = lexeme.id;
        try {
            await contentApi.skipLexeme(lexeme.id);
            lexeme.skipped = true;
        } catch {
            error.value = 'Failed to hide this word.';
        } finally {
            markingId.value = null;
        }
    }

    async function unskip(lexeme: LexemeWithLearned): Promise<void> {
        if (!lexeme.skipped) return;
        markingId.value = lexeme.id;
        try {
            await contentApi.unskipLexeme(lexeme.id);
            lexeme.skipped = false;
        } catch {
            error.value = 'Failed to restore this word.';
        } finally {
            markingId.value = null;
        }
    }

    /**
     * Bulk counterpart of markLearned() (task 7.5): marks each given
     * ContentLexeme.id as learned, applies the per-id result to the local
     * `lexemes` list for whichever ids succeeded (partial success is
     * expected, not an error — see BulkLexemeActionResponse), and returns
     * the raw response so the caller can show "N of M marked" feedback.
     */
    async function bulkMarkLearned(ids: number[]): Promise<BulkLexemeActionResponse | null> {
        if (ids.length === 0) return null;
        bulkActionPending.value = true;
        try {
            const data = await contentApi.bulkMarkLexemesLearned(ids);
            const okIds = new Set(data.results.filter((r) => r.ok).map((r) => r.id));
            for (const l of lexemes.value) {
                if (okIds.has(l.id)) l.learned = true;
            }
            return data;
        } catch {
            error.value = 'Failed to mark selected words as learned.';
            return null;
        } finally {
            bulkActionPending.value = false;
        }
    }

    /**
     * Bulk counterpart of startLearning() (task 7.5).
     */
    async function bulkStartLearning(ids: number[]): Promise<BulkLexemeActionResponse | null> {
        if (ids.length === 0) return null;
        bulkActionPending.value = true;
        try {
            const data = await contentApi.bulkStartLexemesLearning(ids);
            const okIds = new Set(data.results.filter((r) => r.ok).map((r) => r.id));
            for (const l of lexemes.value) {
                if (okIds.has(l.id)) l.in_review = true;
            }
            return data;
        } catch {
            error.value = 'Failed to add selected words to learning.';
            return null;
        } finally {
            bulkActionPending.value = false;
        }
    }

    async function bulkStopLearning(ids: number[]): Promise<BulkLexemeActionResponse | null> {
        if (ids.length === 0) return null;
        bulkActionPending.value = true;
        const results = await Promise.all(ids.map(async (id) => {
            try {
                await contentApi.stopLexemeLearning(id);
                const lexeme = lexemes.value.find((item) => item.id === id);
                if (lexeme) lexeme.in_review = false;
                return { id, ok: true };
            } catch {
                return { id, ok: false };
            }
        }));
        bulkActionPending.value = false;
        return {
            results,
            succeeded: results.filter((result) => result.ok).length,
            failed: results.filter((result) => !result.ok).length,
        };
    }

    /**
     * Request AI explanation for a lexeme. Returns payload for modal or null on error.
     * Sets explainError and aiUnavailable on 503/403.
     */
    async function explainLexeme(lexeme: LexemeWithLearned, refresh = false): Promise<{ lexemeText: string; explanation: string } | null> {
        explainingId.value = lexeme.id;
        explainError.value = '';
        try {
            const data = await contentApi.explainLexeme(lexeme.id, refresh);
            return { lexemeText: lexeme.text, explanation: data.explanation };
        } catch (e: unknown) {
            const err = e as { response?: { status?: number; data?: { message?: string } } };
            if (err.response?.status === 503 || err.response?.status === 403) {
                aiUnavailable.value = true;
                explainError.value = 'AI temporarily unavailable.';
            } else {
                explainError.value = err.response?.data?.message ?? 'Failed to get explanation.';
            }
            return null;
        } finally {
            explainingId.value = null;
        }
    }

    /**
     * On-demand extra example sentences for a word (task: "more examples"
     * button), read-only on the backend — appends straight into the
     * lexeme's own `examples` array so WordExamples.vue picks them up
     * immediately, same as any AI-applied example.
     */
    async function fetchMoreExamples(lexeme: LexemeWithLearned): Promise<void> {
        if (!lexeme.lexeme_id) return;
        fetchingExamplesId.value = lexeme.id;
        explainError.value = '';
        try {
            const data = await dictionaryApi.moreExamples(lexeme.lexeme_id);
            const fresh = data.examples.map((e) => ({ example: e.example, translation: e.translation, is_primary: false }));
            lexeme.examples = [...(lexeme.examples ?? []), ...fresh];
        } catch (e: unknown) {
            const err = e as { response?: { status?: number } };
            if (err.response?.status === 503 || err.response?.status === 403) {
                aiUnavailable.value = true;
            }
            explainError.value = 'Failed to get more examples.';
        } finally {
            fetchingExamplesId.value = null;
        }
    }

    return {
        loading,
        error,
        lexemes,
        markingId,
        startingReviewId,
        explainingId,
        explainError,
        aiUnavailable,
        bulkActionPending,
        fetchingExamplesId,
        toLearn,
        learned,
        loadLexemes,
        markLearned,
        unmarkLearned,
        startLearning,
        stopLearning,
        skip,
        unskip,
        bulkMarkLearned,
        bulkStartLearning,
        bulkStopLearning,
        explainLexeme,
        fetchMoreExamples,
    };
}
