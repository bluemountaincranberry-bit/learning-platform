import { keepPreviousData, useQuery } from '@tanstack/vue-query';
import { computed, toValue, type MaybeRefOrGetter } from 'vue';
import { contentApi } from '../api/contentApi';
import type { ContentListParams } from '../../../types';
import { contentQueryKeys } from './contentQueryKeys';

export function useContentListQuery(params: MaybeRefOrGetter<ContentListParams>) {
    return useQuery({
        queryKey: computed(() => contentQueryKeys.list(toValue(params))),
        queryFn: () => contentApi.getList(toValue(params)),
        placeholderData: keepPreviousData,
    });
}

export function useContentQuery(id: MaybeRefOrGetter<string | number | null | undefined>) {
    return useQuery({
        queryKey: computed(() => contentQueryKeys.detail(toValue(id) ?? 'unknown')),
        queryFn: () => contentApi.getOne(toValue(id) as string | number),
        enabled: computed(() => toValue(id) != null),
    });
}

export function useContentCategoriesQuery() {
    return useQuery({
        queryKey: contentQueryKeys.categories(),
        queryFn: () => contentApi.getCategories(),
    });
}
