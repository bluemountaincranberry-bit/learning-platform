<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { LoaderCircle, Mic, Square, ArrowLeftRight, Upload, X } from 'lucide-vue-next';
import UiButton from './UiButton.vue';
import SelectField from './SelectField.vue';
import { useAudioRecorder } from '../../composables/useAudioRecorder';
import { speechApi, learningFlowApi, type SpeechLanguage, type SpeechProvider } from '../../domains/learning';

const props = defineProps<{ settingsTarget?: string }>();

const emit = defineEmits<{
    ready: [text: string, audio: Blob, provider: SpeechProvider, language: SpeechLanguage, keepForever: boolean];
    cleared: [];
    retention: [keepForever: boolean];
}>();

const { isRecording, elapsedSeconds, audioBlob, error: recorderError, start, stop, reset } = useAudioRecorder();
const audioFileInput = ref<HTMLInputElement | null>(null);
const currentAudio = ref<Blob | null>(null);
const uploadedAudioName = ref('');
const providers = ref<Array<{ id: SpeechProvider; label: string }>>([]);
const providersLoading = ref(true);
const provider = ref<SpeechProvider>('local_whisper');
const language = ref<SpeechLanguage>('en');
const keepForever = ref(false);
const transcript = ref('');
const comparedText = ref('');
const busy = ref(false);
const comparing = ref(false);
const message = ref('');
const settingsOpen = ref(false);

const providerOptions = computed(() => providers.value.map((item) => ({ value: item.id, label: item.label })));
const otherProvider = computed(() => providers.value.find((item) => item.id !== provider.value));

onMounted(async () => {
    const [availableResult, flowResult] = await Promise.allSettled([speechApi.providers(), learningFlowApi.get()]);
    if (availableResult.status === 'fulfilled') {
        providers.value = availableResult.value.providers;
        if (providers.value.length === 0) message.value = 'No transcription provider is configured.';
    } else {
        message.value = 'Voice input could not load its providers.';
    }
    if (flowResult.status === 'fulfilled') {
        const flow = flowResult.value;
        const savedProvider = flow.preferences?.speech_transcription_provider;
        if (savedProvider && providers.value.some((item) => item.id === savedProvider)) provider.value = savedProvider;
        else if (providers.value[0]) provider.value = providers.value[0].id;
        keepForever.value = flow.preferences?.speech_audio_retention === 'forever';
    } else if (availableResult.status === 'fulfilled' && providers.value[0]) {
        provider.value = providers.value[0].id;
        message.value = 'Voice preferences could not load; using the default provider.';
    }
    providersLoading.value = false;
});

watch(audioBlob, async (audio) => {
    if (!audio) return;
    currentAudio.value = audio;
    uploadedAudioName.value = '';
    await transcribeAudio(audio);
});

async function transcribeAudio(audio: Blob) {
    transcript.value = '';
    comparedText.value = '';
    message.value = '';
    busy.value = true;
    try {
        const result = await speechApi.transcribe(audio, provider.value, language.value);
        transcript.value = result.text;
        emit('ready', transcript.value, audio, provider.value, language.value, keepForever.value);
    } catch (error: unknown) {
        const response = error as { response?: { data?: { message?: string } } };
        message.value = response.response?.data?.message ?? 'Could not transcribe this recording. Try the other provider.';
    } finally {
        busy.value = false;
    }
}

async function onAudioFileChange(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';
    if (!file) return;
    clearRecording();
    if (file.size > 10 * 1024 * 1024) {
        message.value = 'Audio files must be 10 MB or smaller.';
        return;
    }

    currentAudio.value = file;
    uploadedAudioName.value = file.name;
    await transcribeAudio(file);
}

async function compareWithOtherProvider() {
    if (!currentAudio.value || !otherProvider.value || comparing.value) return;
    comparing.value = true;
    message.value = '';
    try {
        const result = await speechApi.transcribe(currentAudio.value, otherProvider.value.id, language.value);
        comparedText.value = `${otherProvider.value.label}: ${result.text}`;
    } catch {
        message.value = `The ${otherProvider.value.label} service could not transcribe this recording.`;
    } finally {
        comparing.value = false;
    }
}

function clearRecording() {
    reset();
    currentAudio.value = null;
    uploadedAudioName.value = '';
    transcript.value = '';
    comparedText.value = '';
    message.value = '';
    emit('cleared');
}

function toggleSettings() {
    settingsOpen.value = !settingsOpen.value;
}

defineExpose({ clearRecording, toggleSettings, settingsOpen });
</script>

<template>
    <Teleport v-if="props.settingsTarget && settingsOpen" :to="props.settingsTarget">
        <div class="absolute right-0 top-full z-50 mt-2 w-80 max-w-[calc(100vw-1.5rem)] overflow-hidden rounded-spa-lg border border-border bg-card shadow-xl" role="dialog" aria-labelledby="voice-settings-title" @keydown.esc="settingsOpen = false">
            <div class="flex items-start justify-between gap-3 border-b border-border bg-surface-alt px-4 py-3">
                <div>
                    <h2 id="voice-settings-title" class="text-sm font-semibold text-fg">Voice settings</h2>
                    <p class="mt-0.5 text-xs leading-5 text-muted-foreground">Choose how recordings are transcribed.</p>
                </div>
                <UiButton variant="ghost" size="icon" aria-label="Close voice settings" @click="settingsOpen = false"><X :size="16" aria-hidden="true" /></UiButton>
            </div>
            <div class="space-y-3 p-4">
                <SelectField v-model="provider" label="Transcription provider" :options="providerOptions" />
                <SelectField v-model="language" label="Spoken language" :options="[{ value: 'en', label: 'English' }, { value: 'ru', label: 'Russian' }]" />
                <label v-if="transcript" class="flex items-center gap-2 border-t border-border pt-3 text-xs text-muted-foreground">
                    <input v-model="keepForever" type="checkbox" class="rounded border-input" @change="emit('retention', keepForever)" /> Keep this recording forever
                </label>
                <p v-if="transcript" class="text-xs leading-5 text-muted-foreground">
                    <span v-if="uploadedAudioName" class="block truncate font-medium text-fg-secondary">{{ uploadedAudioName }}</span>
                    Transcript added to the message. Review or edit it before sending.
                </p>
                <div v-if="transcript && (otherProvider || currentAudio)" class="flex flex-wrap items-center justify-between gap-2 border-t border-border pt-2">
                    <UiButton v-if="otherProvider" variant="ghost" size="sm" :disabled="comparing || busy" @click="compareWithOtherProvider">
                        <ArrowLeftRight :size="14" /> {{ comparing ? 'Comparing…' : 'Compare both' }}
                    </UiButton>
                    <UiButton variant="ghost" size="sm" @click="clearRecording"><X :size="15" /> Discard recording</UiButton>
                </div>
                <p v-if="comparedText" class="text-xs leading-5 text-muted-foreground">{{ comparedText }}</p>
            </div>
        </div>
    </Teleport>

    <div v-if="props.settingsTarget" class="relative flex shrink-0 items-center gap-1">
        <input ref="audioFileInput" type="file" accept="audio/webm,audio/mp4,audio/x-m4a,audio/ogg,audio/wav,audio/mpeg,audio/mpga" class="hidden" @change="onAudioFileChange" />
        <UiButton
            variant="secondary"
            size="icon-touch"
            :disabled="busy || providersLoading || providers.length === 0 || isRecording"
            :aria-label="busy ? 'Transcribing uploaded audio' : 'Upload an audio recording'"
            :title="busy ? 'Transcribing audio…' : 'Upload an audio recording'"
            @click="audioFileInput?.click()"
        >
            <LoaderCircle v-if="busy" :size="17" class="animate-spin" aria-hidden="true" />
            <Upload v-else :size="17" aria-hidden="true" />
        </UiButton>
        <UiButton
            v-if="!isRecording"
            variant="secondary"
            size="icon-touch"
            :disabled="busy || providersLoading || providers.length === 0"
            :title="providersLoading ? 'Loading voice providers…' : message || 'Record voice message'"
            aria-label="Record voice message"
            @click="start"
        >
            <LoaderCircle v-if="providersLoading" :size="17" class="animate-spin" aria-hidden="true" />
            <Mic v-else :size="17" aria-hidden="true" />
        </UiButton>
        <UiButton v-else variant="danger" size="icon-touch" aria-label="Stop voice recording" title="Stop voice recording" @click="stop">
            <Square :size="15" aria-hidden="true" />
        </UiButton>
        <span v-if="isRecording" class="absolute -top-2 left-1/2 -translate-x-1/2 rounded bg-destructive px-1 text-[10px] leading-4 text-destructive-foreground">{{ elapsedSeconds }}s</span>
        <span v-if="busy" class="sr-only" role="status">Transcribing audio…</span>
        <div v-if="recorderError || message" class="absolute bottom-full right-0 z-30 mb-2 w-72 max-w-[calc(100vw-1.5rem)] rounded-md border border-warning-border bg-warning-bg px-3 py-2 text-left text-xs leading-5 text-warning-fg shadow-lg" role="alert">
            {{ recorderError || message }}
        </div>
        <UiButton v-if="transcript" variant="ghost" size="icon" aria-label="Discard recording" title="Discard recording" @click="clearRecording">
            <X :size="15" aria-hidden="true" />
        </UiButton>
    </div>

    <div v-else class="space-y-2 rounded-lg border border-border bg-background p-2.5">
        <div class="flex flex-wrap items-end gap-2">
            <SelectField v-model="provider" class="min-w-36 flex-1" label="Transcription" :options="providerOptions" />
            <SelectField v-model="language" class="min-w-28 flex-1" label="Spoken language" :options="[{ value: 'en', label: 'English' }, { value: 'ru', label: 'Russian' }]" />
            <UiButton v-if="!isRecording" variant="secondary" size="touch" :disabled="busy || providersLoading || providers.length === 0" aria-label="Record voice message" @click="start">
                <Mic :size="17" /> Record
            </UiButton>
            <UiButton v-else variant="danger" size="touch" aria-label="Stop voice recording" @click="stop">
                <Square :size="15" /> {{ elapsedSeconds }}s
            </UiButton>
        </div>
        <p v-if="recorderError" class="text-xs text-warning" role="alert">{{ recorderError }}</p>
        <p v-if="busy" class="text-xs text-muted-foreground" role="status">Transcribing audio…</p>
        <p v-if="transcript" class="text-xs text-muted-foreground">Transcript added to the message. Review or edit it before sending.</p>
        <div v-if="transcript" class="flex flex-wrap items-center justify-between gap-2">
            <label class="flex items-center gap-2 text-xs text-muted-foreground">
                <input v-model="keepForever" type="checkbox" class="rounded border-input" @change="emit('retention', keepForever)" /> Keep this recording forever
            </label>
            <div class="flex gap-1">
                <UiButton v-if="otherProvider" variant="ghost" size="sm" :disabled="comparing || busy" @click="compareWithOtherProvider">
                    <ArrowLeftRight :size="14" /> {{ comparing ? 'Comparing…' : 'Compare both' }}
                </UiButton>
                <UiButton variant="ghost" size="icon" aria-label="Discard recording" title="Discard recording" @click="clearRecording"><X :size="15" /></UiButton>
            </div>
        </div>
        <p v-if="comparedText" class="text-xs text-muted-foreground">{{ comparedText }}</p>
        <p v-if="message" class="text-xs text-warning" role="alert">{{ message }}</p>
    </div>
</template>
