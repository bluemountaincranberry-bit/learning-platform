<script setup lang="ts">
import MarkdownContent from './MarkdownContent.vue';
import UiButton from './UiButton.vue';

defineProps<{ role: 'user' | 'assistant'; content?: string | null; attachments?: { name: string; status: string }[]; loading?: boolean; loadingLabel?: string | null; error?: string | null; retryable?: boolean }>();
const emit = defineEmits<{ retry: [] }>();
</script>

<template>
    <div class="flex min-w-0" :class="role === 'user' ? 'justify-end' : 'justify-start'">
        <div class="min-w-0 max-w-[90%] space-y-2 rounded-spa-lg border px-3 py-2 text-sm leading-6" :class="role === 'user' ? 'border-primary bg-primary/10 text-fg' : 'border-border bg-surface text-fg-secondary'">
            <div v-for="(attachment, index) in attachments" :key="index" class="min-w-0 break-words text-xs">
                <span class="font-medium">{{ attachment.name }}</span> · {{ attachment.status }}
            </div>
            <MarkdownContent v-if="content" :content="content" learning-links />
            <div v-if="loading" role="status" class="text-muted-foreground">{{ loadingLabel || 'Thinking...' }}</div>
            <div v-if="error" role="alert" class="space-y-1 break-words text-warning-fg">
                <p>{{ error }}</p>
                <UiButton v-if="retryable" variant="secondary" size="touch" :disabled="loading" @click="emit('retry')">Try again</UiButton>
            </div>
        </div>
    </div>
</template>
