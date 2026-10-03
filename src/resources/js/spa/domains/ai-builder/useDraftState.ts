import { computed, ref } from 'vue';

export function useDraftState(serialize: () => unknown) {
    const savedSnapshot = ref<string | null>(null);
    const isDirty = computed(() => savedSnapshot.value === null || JSON.stringify(serialize()) !== savedSnapshot.value);

    function markSaved(): void {
        savedSnapshot.value = JSON.stringify(serialize());
    }

    return { isDirty, markSaved };
}
