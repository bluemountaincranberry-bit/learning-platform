<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { ChevronDown, Play } from 'lucide-vue-next';
import type { TranscriptSegment } from '../../types';
import { formatDuration } from '../time';
import UiBadge from './UiBadge.vue';

const props = defineProps<{
    segments: TranscriptSegment[];
    activeSegmentId?: number | null;
    nativeTranslations?: Record<number, string>;
}>();

const emit = defineEmits<{
    (event: 'seek', segment: TranscriptSegment): void;
    // lexemeId is null for a word the AI pipeline hasn't matched yet — every
    // word is clickable, the consumer looks it up on demand (see
    // ContentDetailsPage's transcriptWord handler).
    (event: 'word-click', payload: { lexemeId: number | null; text: string; startOffset: number; endOffset: number; segment: TranscriptSegment }): void;
    (event: 'mode-change', mode: 'target' | 'native' | 'both'): void;
}>();

const mode = ref<'target' | 'native' | 'both'>('target');
const isExpanded = ref(false);
const container = ref<HTMLElement | null>(null);
// Task 10.7: a drag-selection's mouseup is followed by a click event on the
// word span it ends on in most browsers — this suppresses that click's own
// single-word emit once, right after a multi-word selection already emitted
// its own combined-range event.
const suppressNextWordClick = ref(false);

const visibleSegments = computed(() => props.segments.filter((segment) => segment.text.trim() !== ''));

function words(segment: TranscriptSegment): Array<{ text: string; leading: string; start: number; end: number; lexemeId?: number }> {
    const refs = segment.lexemes ?? [];
    const result: Array<{ text: string; leading: string; start: number; end: number; lexemeId?: number }> = [];
    const pattern = /[\p{L}\p{N}]+(?:['’][\p{L}\p{N}]+)*/gu;
    let match: RegExpExecArray | null;
    while ((match = pattern.exec(segment.text)) !== null) {
        const start = match.index;
        const end = start + match[0].length;
        const reference = refs.find((item) => item.start_offset === start && item.end_offset === end)
            ?? refs.find((item) => item.start_offset <= start && item.end_offset >= end);
        const previousEnd = result[result.length - 1]?.end ?? 0;
        result.push({ text: match[0], leading: segment.text.slice(previousEnd, start), start, end, lexemeId: reference?.content_lexeme_id });
    }
    return result;
}

function onWordClick(word: { text: string; start: number; end: number; lexemeId?: number }, segment: TranscriptSegment): void {
    if (suppressNextWordClick.value) {
        suppressNextWordClick.value = false;
        return;
    }
    emit('word-click', { lexemeId: word.lexemeId ?? null, text: word.text, startOffset: word.start, endOffset: word.end, segment });
}

/** Walks up from a selection endpoint node to the nearest word span carrying data-start/data-end, without escaping the segment's own word container. */
function closestOffsetSpan(node: Node | null, container: HTMLElement): HTMLElement | null {
    let el: HTMLElement | null = node instanceof HTMLElement ? node : (node?.parentElement ?? null);
    while (el && el !== container) {
        if (el.dataset.start !== undefined && el.dataset.end !== undefined) return el;
        el = el.parentElement;
    }
    return null;
}

/**
 * Task 10.7: lets a learner select a phrase (not just click one word) — a
 * genuine, non-collapsed text selection spanning one or more word spans
 * resolves to the exact substring of segment.text between the first and
 * last selected word's own offsets (so whitespace between them round-trips
 * correctly), and reuses the same word-click event single-word clicks
 * already emit.
 */
function onSegmentMouseUp(event: MouseEvent, segment: TranscriptSegment): void {
    const selection = window.getSelection();
    if (!selection || selection.isCollapsed || selection.toString().trim() === '') return;

    const wordContainer = event.currentTarget as HTMLElement;
    const anchorSpan = closestOffsetSpan(selection.anchorNode, wordContainer);
    const focusSpan = closestOffsetSpan(selection.focusNode, wordContainer);
    if (!anchorSpan || !focusSpan) return;

    const startOffset = Math.min(Number(anchorSpan.dataset.start), Number(focusSpan.dataset.start));
    const endOffset = Math.max(Number(anchorSpan.dataset.end), Number(focusSpan.dataset.end));
    if (endOffset <= startOffset) return;

    const text = segment.text.slice(startOffset, endOffset);
    selection.removeAllRanges();
    suppressNextWordClick.value = true;

    emit('word-click', { lexemeId: null, text, startOffset, endOffset, segment });
}

function segmentTimeRange(segment: TranscriptSegment): string {
    const start = formatDuration(segment.start_ms);
    if (segment.end_ms == null) return start;
    return `${start}–${formatDuration(segment.end_ms)}`;
}

watch(
    [() => props.activeSegmentId, isExpanded],
    ([id]) => {
        if (id === null || id === undefined || !isExpanded.value || !container.value) return;
        const element = container.value.querySelector(`[data-segment-id="${id}"]`);
        element?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    },
);
</script>

<template>
    <div class="rounded-spa-lg border border-border bg-surface p-3">
        <button
            type="button"
            class="flex w-full items-center justify-between gap-3 text-left"
            :aria-expanded="isExpanded"
            @click="isExpanded = !isExpanded"
        >
            <div class="flex min-w-0 items-center gap-2">
                <UiBadge tone="neutral">Transcript</UiBadge>
                <span class="truncate text-xs text-muted-foreground">{{ visibleSegments.length }} phrases · Click word to translate, click ▶ to jump</span>
            </div>
            <ChevronDown :size="16" class="shrink-0 text-muted-foreground transition-transform" :class="isExpanded ? 'rotate-180' : ''" />
        </button>

        <div v-if="isExpanded" class="mt-3 space-y-3">
            <div class="flex gap-1 rounded-md border border-border p-1 text-xs">
                <button v-for="value in ['target', 'native', 'both'] as const" :key="value" type="button" class="rounded px-2 py-1 capitalize" :class="mode === value ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'" @click="mode = value; emit('mode-change', value)">
                    {{ value }}
                </button>
            </div>

            <div ref="container" class="max-h-96 space-y-1 overflow-y-auto pr-1">
                <div
                    v-for="segment in visibleSegments"
                    :key="segment.id"
                    class="rounded-md p-2 transition-colors"
                    :class="activeSegmentId === segment.id ? 'bg-primary/10 ring-1 ring-primary/30' : 'hover:bg-accent'"
                    :data-segment-id="segment.id"
                >
                    <!-- Timestamp as separate play button -->
                    <div class="flex items-start gap-2">
                        <button
                            type="button"
                            class="flex items-center gap-1 shrink-0 min-w-[2.5rem] text-[11px] tabular-nums text-muted-foreground hover:text-primary transition-colors p-1 rounded"
                            :title="segmentTimeRange(segment)"
                            @click="emit('seek', segment)"
                            aria-label="Jump to {{ formatDuration(segment.start_ms) }}"
                        >
                            <Play :size="12" class="shrink-0" />
                            <span class="hidden sm:inline">{{ formatDuration(segment.start_ms) }}</span>
                        </button>

                        <!-- Words area -->
                        <div v-if="mode !== 'native'" class="flex-1 min-w-0 text-sm leading-6 text-fg" @mouseup.stop="onSegmentMouseUp($event, segment)">
                            <template v-for="word in words(segment)" :key="`${segment.id}-${word.start}`">
                                <span class="whitespace-pre">{{ word.leading }}</span>
                                <span
                                    class="cursor-pointer rounded px-0.5 underline decoration-dotted underline-offset-2"
                                    :class="word.lexemeId ? 'text-primary' : 'text-fg/80 hover:text-primary'"
                                    :data-start="word.start"
                                    :data-end="word.end"
                                    @click.stop="onWordClick(word, segment)"
                                >{{ word.text }}</span>
                            </template>
                        </div>

                        <!-- Native translation -->
                        <div v-if="mode === 'native' || mode === 'both'" class="flex-1 min-w-0">
                            <span v-if="nativeTranslations?.[segment.id]" class="mt-1 block pl-10 text-xs text-muted-foreground">
                                {{ nativeTranslations[segment.id] }}
                            </span>
                            <span v-else class="mt-1 block pl-10 text-xs text-muted-foreground">Translation unavailable</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>