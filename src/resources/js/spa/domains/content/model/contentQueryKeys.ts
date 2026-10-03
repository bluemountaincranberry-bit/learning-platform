import type { ContentListParams } from '../../../types';

const contentRoot = ['content'] as const;

export const contentQueryKeys = {
    all: contentRoot,
    lists: () => [...contentRoot, 'list'] as const,
    list: (params: ContentListParams = {}) => [...contentQueryKeys.lists(), params] as const,
    details: () => [...contentRoot, 'detail'] as const,
    detail: (id: string | number) => [...contentQueryKeys.details(), String(id)] as const,
    categories: () => [...contentRoot, 'categories'] as const,
    transcript: (id: string | number, range: { from_ms?: number; to_ms?: number } = {}) => [
        ...contentRoot,
        'transcript',
        String(id),
        range,
    ] as const,
} as const;
