import { ref } from 'vue';
import { contentApi } from '../domains/content/api/contentApi';

export function useCategories() {
    const loading = ref(true);
    const error = ref('');
    const categories = ref<string[]>([]);

    async function loadCategories(): Promise<void> {
        loading.value = true;
        error.value = '';
        try {
            const data = await contentApi.getCategories();
            categories.value = data.categories ?? [];
        } catch {
            error.value = 'Failed to load categories.';
        } finally {
            loading.value = false;
        }
    }

    return {
        loading,
        error,
        categories,
        loadCategories,
    };
}
