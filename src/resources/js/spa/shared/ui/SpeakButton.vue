<script setup lang="ts">
import { Volume2 } from 'lucide-vue-next';
import UiButton from './UiButton.vue';
import { isSpeechSupported, speak } from '../lib/speech';

const props = defineProps<{
    text: string;
    language?: string | null;
}>();
</script>

<template>
    <UiButton
        v-if="isSpeechSupported()"
        variant="ghost"
        size="icon"
        class="h-8 w-8 shrink-0"
        title="Pronounce"
        aria-label="Pronounce"
        @click.stop.prevent="speak(props.text, props.language)"
    >
        <Volume2 :size="16" />
    </UiButton>
</template>
