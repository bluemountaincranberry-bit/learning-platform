import { ref, onMounted } from 'vue';
import type { Content } from '../types';
import { recommendedApi } from '../domains/ai/api/recommendedApi';

export function useRecommendedContents(limit = 6) {
    const items = ref<Content[]>([]);
    const loading = ref(true);
    const error = ref('');

    async function load(): Promise<void> {
        loading.value = true;
        error.value = '';
        try {
            const data = await recommendedApi.getContents(limit);
            items.value = data.data ?? [];
        } catch (e: unknown) {
            const status = (e as { response?: { status: number } })?.response?.status;
            if (status === 401) {
                items.value = [];
            } else {
                error.value = 'Failed to load recommendations.';
            }
        } finally {
            loading.value = false;
        }
    }

    onMounted(load);

    return { items, loading, error, load };
}

/**
 * Task 6.6: same shape/error-handling as useRecommendedContents() above —
 * recommendedApi.getLexemes() already existed (EPIC 4.14's
 * RecommendationService) with zero frontend callers until this task.
 */
export function useRecommendedLexemes(limit = 10) {
    const items = ref<{ id: number; text: string; content_id: number }[]>([]);
    const loading = ref(true);
    const error = ref('');

    async function load(): Promise<void> {
        loading.value = true;
        error.value = '';
        try {
            const data = await recommendedApi.getLexemes(limit);
            items.value = data.data ?? [];
        } catch (e: unknown) {
            const status = (e as { response?: { status: number } })?.response?.status;
            if (status === 401) {
                items.value = [];
            } else {
                error.value = 'Failed to load word recommendations.';
            }
        } finally {
            loading.value = false;
        }
    }

    onMounted(load);

    return { items, loading, error, load };
}
