import { ref } from 'vue';
import { contentReadinessApi } from '../domains/content/api/contentReadinessApi';
import type { ContentReadiness } from '../domains/content/api/contentReadinessApi';

export function useContentReadiness(contentId: number) {
    const loading = ref(true);
    const readiness = ref<ContentReadiness | null>(null);

    async function load() {
        loading.value = true;
        try {
            readiness.value = await contentReadinessApi.show(contentId);
        } catch {
            readiness.value = null;
        } finally {
            loading.value = false;
        }
    }

    return { loading, readiness, load };
}
