<script setup lang="ts">
import { useRouter } from 'vue-router';
import UiButton from './UiButton.vue';

/**
 * Single reusable entry point into the TutorAgent chat from anywhere in the
 * app (task 6.1, roadmap "Дизайн-решение 6.1"). Deliberately dumb: it does
 * not create a conversation or talk to the API itself — it only carries
 * `context` into `{ name: 'chat' }`'s query params, where `ChatPage.vue`
 * picks it up. No per-page conversation, no context-builder service — just
 * query params + the chat page's own existing `ensureConversation()` flow.
 */
const props = withDefaults(
    defineProps<{
        context: {
            type: 'content' | 'grammar' | 'lexeme';
            id: number | string;
            title: string;
        };
        label?: string;
        variant?: 'primary' | 'secondary' | 'ghost' | 'danger';
        size?: 'default' | 'sm' | 'lg' | 'icon';
    }>(),
    {
        label: 'Ask AI',
        variant: 'ghost',
        size: 'default',
    },
);

const router = useRouter();

function go(): void {
    router.push({
        name: 'chat',
        query: {
            context_type: props.context.type,
            context_id: String(props.context.id),
            context_title: props.context.title,
        },
    });
}
</script>

<template>
    <UiButton :variant="variant" :size="size" @click="go">{{ label }}</UiButton>
</template>
