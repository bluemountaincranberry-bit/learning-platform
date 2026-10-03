<script setup lang="ts">
import UiBadge from '../../shared/ui/UiBadge.vue';
import UiButton from '../../shared/ui/UiButton.vue';

withDefaults(defineProps<{
    saving: boolean;
    dirty: boolean;
    savedMessage?: string | null;
    errorMessage?: string | null;
}>(), { savedMessage: null, errorMessage: null });

const emit = defineEmits<{ save: []; }>();
</script>

<template>
    <div class="flex items-center gap-3">
        <UiButton variant="secondary" :disabled="saving || !dirty" @click="emit('save')">
            {{ saving ? 'Saving…' : 'Save draft' }}
        </UiButton>
        <UiBadge v-if="dirty" tone="warning">Unsaved changes</UiBadge>
        <span v-if="savedMessage" class="text-xs text-success-fg">{{ savedMessage }}</span>
        <span v-if="errorMessage" class="text-xs text-danger">{{ errorMessage }}</span>
    </div>
</template>
