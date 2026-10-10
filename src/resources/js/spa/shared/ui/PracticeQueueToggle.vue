<script setup lang="ts">
import { Minus, Plus } from 'lucide-vue-next';
import UiButton from './UiButton.vue';

withDefaults(defineProps<{ queued?: boolean; disabled?: boolean; word?: string }>(), { queued: false, disabled: false, word: '' });
const emit = defineEmits<{ toggle: [] }>();
</script>

<template>
    <UiButton
        variant="ghost"
        size="icon-touch"
        class="shrink-0"
        :disabled="disabled"
        :aria-label="queued ? `Remove ${word || 'word'} from practice` : `Add ${word || 'word'} to practice`"
        :aria-pressed="queued"
        :title="queued ? `Remove ${word || 'word'} from practice while keeping its history` : `Add ${word || 'word'} to practice`"
        @click="emit('toggle')"
    >
        <span
            class="flex h-8 w-8 items-center justify-center rounded-full"
            :class="queued ? 'border border-border bg-secondary text-secondary-foreground' : 'bg-primary text-primary-foreground'"
        >
            <Minus v-if="queued" :size="16" aria-hidden="true" />
            <Plus v-else :size="16" aria-hidden="true" />
        </span>
    </UiButton>
</template>
