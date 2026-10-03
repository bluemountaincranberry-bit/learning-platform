import { ref, computed, watch } from 'vue';
import { useContentListQuery } from '../domains/content/model/contentQueries';
import type { Content, ContentListParams } from '../types';
import { parseApiError } from '../types';

/** CEFR levels for catalog filter (matches backend Content::CEFR_LEVELS). */
export const CEFR_LEVELS = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'] as const;

export interface UseCatalogFilters {
    type?: string;
    level?: string;
    q?: string;
    scope?: 'mine' | 'all';
}

const SEARCH_DEBOUNCE_MS = 350;

export function useCatalog(filters: UseCatalogFilters = {}) {
    const selectedType = ref(filters.type ?? 'all');
    const selectedLevel = ref(filters.level ?? 'all');
    const searchQuery = ref(filters.q ?? '');
    // 'mine' vs 'all' catalog scope — defaults to 'all' here; the host page
    // (CatalogPage.vue) sets it to 'mine' once on mount for authenticated
    // users, same one-shot-default idiom the level filter used to use.
    const selectedScope = ref<'mine' | 'all'>(filters.scope ?? 'all');
    const debouncedSearchQuery = ref(searchQuery.value);

    const query = computed<ContentListParams>(() => ({
        ...(selectedType.value !== 'all' ? { type: selectedType.value } : {}),
        ...(selectedLevel.value !== 'all' ? { level: selectedLevel.value } : {}),
        ...(searchQuery.value.trim() ? { q: searchQuery.value.trim() } : {}),
        ...(selectedScope.value === 'mine' ? { scope: 'mine' as const } : {}),
    }));

    let searchDebounceId: ReturnType<typeof setTimeout> | null = null;
    watch(searchQuery, () => {
        if (searchDebounceId != null) clearTimeout(searchDebounceId);
        searchDebounceId = setTimeout(() => {
            debouncedSearchQuery.value = searchQuery.value;
        }, SEARCH_DEBOUNCE_MS);
    });

    const queryParams = computed(() => ({ ...query.value, q: debouncedSearchQuery.value.trim() || undefined }));
    const catalogQuery = useContentListQuery(queryParams);
    const loading = computed(() => catalogQuery.isPending.value || catalogQuery.isFetching.value);
    const error = computed(() => catalogQuery.error.value ? parseApiError(catalogQuery.error.value, 'Failed to load catalog.') : '');
    const items = computed<Content[]>(() => catalogQuery.data.value?.data ?? []);

    return {
        loading,
        error,
        items,
        selectedType,
        selectedLevel,
        selectedScope,
        searchQuery,
        loadCatalog: catalogQuery.refetch,
    };
}
