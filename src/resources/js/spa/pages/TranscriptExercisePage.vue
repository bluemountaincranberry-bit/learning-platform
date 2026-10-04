<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PageState from '../components/ui/PageState.vue';
import UiButton from '../shared/ui/UiButton.vue';
import UiCard from '../shared/ui/UiCard.vue';
import UiBadge from '../shared/ui/UiBadge.vue';
import YoutubeEmbed from '../shared/ui/YoutubeEmbed.vue';
import DictationCard from '../widgets/trainer/DictationCard.vue';
import ShadowingCard from '../widgets/trainer/ShadowingCard.vue';
import { contentApi } from '../domains/content';
import { extractYoutubeVideoId } from '../shared/youtube';
import { formatDuration } from '../shared/time';
import type { Content, TranscriptSegment } from '../types';

const route = useRoute();
const router = useRouter();
const content = ref<Content | null>(null);
const segments = ref<TranscriptSegment[]>([]);
const loading = ref(true);
const error = ref('');
const mode = ref<'dictation' | 'shadowing'>((route.query.mode as 'dictation' | 'shadowing') || 'dictation');

const segmentId = computed(() => Number(route.query.segment_id));
const lexemeId = computed(() => Number(route.query.content_lexeme_id) || null);
const segment = computed(() => segments.value.find((item) => item.id === segmentId.value) ?? segments.value[0] ?? null);
const videoId = computed(() => extractYoutubeVideoId(content.value?.source_url));
const youtubeRef = ref<InstanceType<typeof YoutubeEmbed> | null>(null);

function replaySegment() {
    if (segment.value) youtubeRef.value?.replaySegment(segment.value.start_ms, segment.value.end_ms);
}

function selectSegment(next: TranscriptSegment) {
    router.replace({ query: { ...route.query, segment_id: String(next.id) } });
    youtubeRef.value?.replaySegment(next.start_ms, next.end_ms);
}

function backFromPractice() {
    if (route.query.return_to === 'repetitions') {
        router.push({ name: 'repetitions', query: { content_id: String(route.query.content_id ?? '') } });
        return;
    }
    router.push({ name: 'catalog.details', params: { id: String(route.query.content_id ?? '') } });
}

onMounted(async () => {
    try {
        const id = String(route.query.content_id || route.params.id);
        const [contentResponse, transcriptResponse] = await Promise.all([contentApi.getOne(id), contentApi.getTranscript(id)]);
        content.value = contentResponse.content;
        segments.value = transcriptResponse.segments ?? [];
    } catch {
        error.value = 'Unable to load this transcript exercise.';
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="min-h-screen bg-background">
        <header class="sticky top-0 z-20 border-b border-border bg-background/95 backdrop-blur">
            <div class="mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 py-3">
                <UiButton size="sm" variant="ghost" @click="backFromPractice">← Back</UiButton>
                <div class="min-w-0 text-center"><p class="truncate text-sm font-semibold">{{ content?.title || 'Transcript exercise' }}</p><p class="text-xs text-muted-foreground">Practice in context</p></div>
                <span class="w-14" />
            </div>
        </header>

        <main class="mx-auto max-w-5xl space-y-5 px-4 py-6 pb-24 lg:pb-8">
            <PageState v-if="loading" :loading="true" />
            <UiCard v-else-if="error" class="border-destructive/40"><p class="text-sm text-destructive">{{ error }}</p></UiCard>
            <template v-else-if="segment && content">
                <div class="grid gap-5 lg:grid-cols-[minmax(0,1.2fr)_minmax(320px,0.8fr)]">
                    <UiCard class="space-y-3 p-3">
                        <div v-if="videoId" class="overflow-hidden rounded-spa-lg"><YoutubeEmbed ref="youtubeRef" :video-id="videoId" :title="content.title" /></div>
                        <div class="flex items-center justify-between gap-3 px-1"><div><UiBadge tone="neutral">at {{ formatDuration(segment.start_ms) }}</UiBadge><p class="mt-2 text-lg font-semibold text-fg">{{ segment.text }}</p></div><UiButton size="sm" variant="secondary" @click="replaySegment">Replay context</UiButton></div>
                    </UiCard>
                    <UiCard class="space-y-3 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-primary">Choose exercise</p>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <button type="button" :aria-pressed="mode === 'dictation'" class="rounded-lg border p-3 text-left transition" :class="mode === 'dictation' ? 'border-primary bg-primary/10' : 'border-border hover:bg-accent'" @click="mode = 'dictation'"><span class="block text-sm font-semibold">Dictation</span><span class="mt-1 block text-xs text-muted-foreground">Listen and type the phrase</span></button>
                            <button type="button" :aria-pressed="mode === 'shadowing'" class="rounded-lg border p-3 text-left transition" :class="mode === 'shadowing' ? 'border-primary bg-primary/10' : 'border-border hover:bg-accent'" @click="mode = 'shadowing'"><span class="block text-sm font-semibold">Shadowing</span><span class="mt-1 block text-xs text-muted-foreground">Listen and repeat aloud</span></button>
                        </div>
                        <p class="text-xs leading-5 text-muted-foreground">This exercise is linked to transcript segment #{{ segment.id }}. Your result will update learning progress and SRS.</p>
                    </UiCard>
                </div>

                <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_280px]">
                    <DictationCard v-if="mode === 'dictation'" :content-id="content.id" :content-lexeme-id="lexemeId" :target-text="segment.text" :transcript-segment-id="segment.id" :replay="replaySegment" @submitted="mode = 'dictation'" />
                    <ShadowingCard v-else :content-id="content.id" :content-lexeme-id="lexemeId" :target-text="segment.text" :transcript-segment-id="segment.id" :play="replaySegment" :show-target="false" @submitted="mode = 'shadowing'" />
                    <UiCard class="h-fit space-y-3 p-4">
                        <p class="text-sm font-semibold">More segments</p>
                        <button v-for="item in segments" :key="item.id" type="button" class="block w-full rounded-md p-2 text-left text-xs transition hover:bg-accent" :class="item.id === segment.id ? 'bg-primary/10 text-primary' : 'text-muted-foreground'" @click="selectSegment(item)">{{ item.text }}</button>
                    </UiCard>
                </div>
            </template>
        </main>
    </div>
</template>
