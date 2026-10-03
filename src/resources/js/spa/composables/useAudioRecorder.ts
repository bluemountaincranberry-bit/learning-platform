import { onUnmounted, ref } from 'vue';

const MAX_DURATION_SECONDS = 30;

export function useAudioRecorder() {
    const isRecording = ref(false);
    const elapsedSeconds = ref(0);
    const audioBlob = ref<Blob | null>(null);
    const error = ref<string | null>(null);
    let recorder: MediaRecorder | null = null;
    let stream: MediaStream | null = null;
    let timer: ReturnType<typeof setInterval> | null = null;
    let chunks: Blob[] = [];

    function clearTimer() {
        if (timer !== null) clearInterval(timer);
        timer = null;
    }

    function stopTracks() {
        stream?.getTracks().forEach((track) => track.stop());
        stream = null;
    }

    async function start() {
        if (isRecording.value) return;
        error.value = null;
        audioBlob.value = null;
        elapsedSeconds.value = 0;

        if (!navigator.mediaDevices?.getUserMedia || typeof MediaRecorder === 'undefined') {
            error.value = 'Audio recording is not supported in this browser.';
            return;
        }

        try {
            stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            const mimeType = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg']
                .find((candidate) => MediaRecorder.isTypeSupported(candidate));
            recorder = new MediaRecorder(stream, mimeType ? { mimeType } : undefined);
            chunks = [];
            recorder.ondataavailable = (event) => { if (event.data.size > 0) chunks.push(event.data); };
            recorder.onstop = () => {
                audioBlob.value = new Blob(chunks, { type: recorder?.mimeType || 'audio/webm' });
                chunks = [];
                stopTracks();
            };
            recorder.start();
            isRecording.value = true;
            timer = setInterval(() => {
                elapsedSeconds.value += 1;
                if (elapsedSeconds.value >= MAX_DURATION_SECONDS) stop();
            }, 1000);
        } catch {
            error.value = 'Microphone permission was not granted.';
            stopTracks();
        }
    }

    function stop() {
        if (!recorder || recorder.state === 'inactive') return;
        recorder.stop();
        isRecording.value = false;
        clearTimer();
    }

    function reset() {
        if (isRecording.value) stop();
        audioBlob.value = null;
        elapsedSeconds.value = 0;
        error.value = null;
    }

    onUnmounted(() => {
        if (isRecording.value) stop();
        clearTimer();
        stopTracks();
    });

    return { isRecording, elapsedSeconds, audioBlob, error, start, stop, reset };
}
