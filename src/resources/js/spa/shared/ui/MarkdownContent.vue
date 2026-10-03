<script setup lang="ts">
import { computed } from 'vue';
import { marked } from 'marked';
import DOMPurify from 'dompurify';

const props = defineProps<{
    content: string | null | undefined;
}>();

// `body` can originate from AI or an admin — never trust it as-is. `marked`
// turns markdown into HTML but does not guarantee the result is safe (raw
// HTML embedded in the source passes through); DOMPurify is what actually
// makes injecting via v-html safe.
const html = computed(() => {
    if (!props.content) return '';

    return DOMPurify.sanitize(marked.parse(props.content, { async: false }) as string);
});
</script>

<template>
    <div class="markdown-content text-sm leading-6 text-fg-secondary" v-html="html" />
</template>

<style scoped>
.markdown-content :deep(h1),
.markdown-content :deep(h2),
.markdown-content :deep(h3) {
    margin-top: 1.25em;
    margin-bottom: 0.5em;
    font-weight: 600;
    color: var(--color-fg, inherit);
}
.markdown-content :deep(h1:first-child),
.markdown-content :deep(h2:first-child),
.markdown-content :deep(h3:first-child) {
    margin-top: 0;
}
.markdown-content :deep(p) {
    margin-bottom: 0.75em;
}
.markdown-content :deep(ul),
.markdown-content :deep(ol) {
    margin-bottom: 0.75em;
    padding-left: 1.5em;
    list-style-position: outside;
}
.markdown-content :deep(ul) {
    list-style-type: disc;
}
.markdown-content :deep(ol) {
    list-style-type: decimal;
}
.markdown-content :deep(strong) {
    font-weight: 600;
    color: var(--color-fg, inherit);
}
.markdown-content :deep(code) {
    font-family: monospace;
    font-size: 0.9em;
}
</style>
