<script setup lang="ts">
import { computed, ref } from 'vue';
import { Languages, LoaderCircle } from 'lucide-vue-next';
import UiButton from './UiButton.vue';
import { translateApi } from '../../domains/ai';
import { useProfileStore } from '../../domains/user';

/**
 * A text with an inline "translate into my language" toggle (ExplainDialog,
 * word-page explanation variants). Target defaults to the learner's own
 * `translation_language`. The translation is fetched on demand and cached
 * server-side; hiding it keeps it for the session.
 */
const props = defineProps<{
    text: string;
}>();

const profileStore = useProfileStore();
const targetLanguage = computed(() => profileStore.profile?.user.translation_language ?? 'ru');

const shown = ref(false);
const loading = ref(false);
const translation = ref('');
const error = ref('');

async function toggle(): Promise<void> {
    shown.value = !shown.value;
    if (!shown.value || translation.value !== '' || loading.value) return;
    loading.value = true;
    error.value = '';
    try {
        const data = await translateApi.translate(props.text, targetLanguage.value);
        translation.value = data.translation;
    } catch {
        error.value = 'Translation failed — try again.';
        shown.value = false;
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="space-y-2">
        <p class="whitespace-pre-wrap text-sm leading-6 text-fg-secondary">{{ text }}</p>
        <div>
            <UiButton variant="ghost" size="sm" :disabled="loading" @click="toggle">
                <LoaderCircle v-if="loading" :size="14" class="animate-spin text-primary" aria-hidden="true" />
                <Languages v-else :size="14" aria-hidden="true" />
                {{ loading ? 'Translating…' : shown ? 'Hide translation' : 'Translate' }}
            </UiButton>
        </div>
        <p v-if="error" class="text-xs text-warning" role="alert">{{ error }}</p>
        <p v-if="shown && translation" class="whitespace-pre-wrap border-l-2 border-primary/30 pl-2 text-sm leading-6 text-fg">{{ translation }}</p>
    </div>
</template>
