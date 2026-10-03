<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';

defineProps<{
    videoId: string;
    title?: string;
}>();

const emit = defineEmits<{
    (event: 'time-update', timeMs: number): void;
    (event: 'ready'): void;
}>();

const iframe = ref<HTMLIFrameElement | null>(null);
type YoutubePlayer = {
    destroy: () => void;
    seekTo: (seconds: number, allowSeekAhead: boolean) => void;
    getCurrentTime: () => number;
    playVideo: () => void;
    pauseVideo: () => void;
};
type YoutubeWindow = Window & { YT?: { Player: new (element: HTMLIFrameElement, options: Record<string, unknown>) => YoutubePlayer } };
let player: YoutubePlayer | null = null;
let timer: number | null = null;
let segmentTimer: number | null = null;

function loadPlayer(): void {
    const YT = (window as YoutubeWindow).YT;
    if (!YT || !iframe.value) return;
    player = new YT.Player(iframe.value as unknown as HTMLIFrameElement, {
        events: {
            onReady: () => emit('ready'),
        },
    });
    timer = window.setInterval(() => {
        if (player) emit('time-update', Math.round(player.getCurrentTime() * 1000));
    }, 300);
}

function seekTo(timeMs: number): void {
    player?.seekTo(Math.max(0, timeMs / 1000), true);
}

function replaySegment(startMs: number, endMs: number | null): void {
    if (segmentTimer !== null) window.clearTimeout(segmentTimer);
    seekTo(Math.max(0, startMs - 300));
    player?.playVideo();

    const duration = Math.max(1000, (endMs ?? startMs + 5000) - startMs + 600);
    segmentTimer = window.setTimeout(() => {
        player?.pauseVideo();
        segmentTimer = null;
    }, duration);
}

defineExpose({ seekTo, replaySegment });

onMounted(() => {
    const existing = document.querySelector('script[data-youtube-iframe-api]');
    if (existing) {
        if ((window as YoutubeWindow).YT) loadPlayer();
        else existing.addEventListener('load', loadPlayer, { once: true });
        return;
    }

    const script = document.createElement('script');
    script.src = 'https://www.youtube.com/iframe_api';
    script.async = true;
    script.dataset.youtubeIframeApi = 'true';
    script.addEventListener('load', loadPlayer, { once: true });
    document.head.appendChild(script);
});

onBeforeUnmount(() => {
    if (timer !== null) window.clearInterval(timer);
    if (segmentTimer !== null) window.clearTimeout(segmentTimer);
    player?.destroy();
});
</script>

<template>
    <div class="aspect-video w-full overflow-hidden rounded-spa-lg border border-border bg-black">
        <iframe
            ref="iframe"
            class="h-full w-full"
            :src="`https://www.youtube.com/embed/${videoId}?enablejsapi=1`"
            :title="title || 'YouTube video player'"
            frameborder="0"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
            allowfullscreen
        />
    </div>
</template>
