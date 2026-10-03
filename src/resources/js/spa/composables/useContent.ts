import { ref } from 'vue';
import type { Content } from '../types';
import { contentApi } from '../domains/content/api/contentApi';

export function useContent() {
    const loading = ref(true);
    const error = ref('');
    const content = ref<Content | null>(null);

    async function loadContent(id: string | number): Promise<void> {
        if (id == null) return;
        loading.value = true;
        error.value = '';
        try {
            const data = await contentApi.getOne(id);
            content.value = data.content;
        } catch {
            error.value = 'Content not found or unavailable.';
        } finally {
            loading.value = false;
        }
    }

    return {
        loading,
        error,
        content,
        loadContent,
    };
}
