<script setup lang="ts">
import { computed } from 'vue';
import { marked } from 'marked';
import DOMPurify from 'dompurify';

const props = defineProps<{
    content: string | null | undefined;
    learningLinks?: boolean;
}>();

// `body` can originate from AI or an admin — never trust it as-is. `marked`
// turns markdown into HTML but does not guarantee the result is safe (raw
// HTML embedded in the source passes through); DOMPurify is what actually
// makes injecting via v-html safe.
const html = computed(() => {
    if (!props.content) return '';

    const sanitized = DOMPurify.sanitize(marked.parse(props.content, { async: false }) as string);
    if (!props.learningLinks) return sanitized;

    // Only explicit, canonical app paths become mentions. Display text never supplies an ID.
    const container = document.createElement('div');
    container.innerHTML = sanitized;
    for (const link of container.querySelectorAll('a')) {
        if (/^\/(word|grammar)\/[1-9]\d*$/.test(link.getAttribute('href') ?? '')) {
            link.classList.add('learning-mention');
        }
    }
    return container.innerHTML;
});
</script>

<template>
    <div class="markdown-content text-sm leading-6 text-fg-secondary" v-html="html" />
</template>

<style scoped>
.markdown-content { min-width: 0; overflow-wrap: anywhere; }
.markdown-content :deep(img) { max-width: 100%; height: auto; }
.markdown-content :deep(pre),
.markdown-content :deep(table) { display: block; max-width: 100%; overflow-x: auto; }
.markdown-content :deep(a) { color: var(--color-primary); text-decoration: underline; }
.markdown-content :deep(a.learning-mention) {
    display: inline-flex;
    align-items: center;
    min-height: 44px;
    max-width: 100%;
    padding: 0 0.5rem;
    border-radius: 0.5rem;
    background: var(--color-primary-soft, rgb(99 102 241 / 0.12));
    white-space: normal;
}
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
