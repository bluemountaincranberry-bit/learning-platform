<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import PageState from '../components/ui/PageState.vue';
import { contentApi } from '../domains/content';
import UiBadge from '../shared/ui/UiBadge.vue';
import UiButton from '../shared/ui/UiButton.vue';
import UiCard from '../shared/ui/UiCard.vue';
import UiEmptyState from '../shared/ui/UiEmptyState.vue';
import UiInput from '../shared/ui/UiInput.vue';
import UiSectionHeader from '../shared/ui/UiSectionHeader.vue';
import SelectField from '../shared/ui/SelectField.vue';
import type { Content } from '../types';

const form = ref({
    source_url: '',
    language: 'en',
});
const submitting = ref(false);
const submitError = ref<string | null>(null);
const submitSuccess = ref(false);
const mySubmissions = ref<Content[]>([]);
const submissionsLoading = ref(false);
const submissionsError = ref<string | null>(null);
let submissionsPollTimer: ReturnType<typeof setTimeout> | null = null;

const languageOptions = [
    { value: 'en', label: 'English' },
    { value: 'es', label: 'Spanish' },
    { value: 'fr', label: 'French' },
    { value: 'de', label: 'German' },
    { value: 'it', label: 'Italian' },
    { value: 'pt', label: 'Portuguese' },
    { value: 'ru', label: 'Russian' },
];

async function submit() {
    submitError.value = null;
    submitSuccess.value = false;
    if (!form.value.source_url.trim()) {
        submitError.value = 'Video URL is required.';
        return;
    }
    submitting.value = true;
    try {
        // Title comes from the video itself (YouTube oEmbed lookup on the
        // backend) — the submitter shouldn't have to type it. Level is set
        // later by AI analysis / moderation, not at submission time.
        await contentApi.submitYoutube({
            source_url: form.value.source_url.trim(),
            language: form.value.language.trim() || 'en',
        });
        submitSuccess.value = true;
        form.value = { source_url: '', language: 'en' };
        await loadMySubmissions();
        scheduleSubmissionsPoll();
    } catch (e: unknown) {
        const err = e as {
            response?: {
                data?: { message?: string; errors?: Record<string, string[]> };
                status?: number;
            };
        };
        const data = err.response?.data;
        if (data?.errors && typeof data.errors === 'object') {
            const first = Object.values(data.errors).flat().find(Boolean);
            submitError.value = first ?? data.message ?? 'Validation failed.';
        } else {
            submitError.value = data?.message || (err.response?.status === 422 ? 'Invalid URL or duplicate submission.' : 'Submission failed.');
        }
    } finally {
        submitting.value = false;
    }
}

async function loadMySubmissions(showLoading = true) {
    if (showLoading) submissionsLoading.value = true;
    submissionsError.value = null;
    try {
        const res = await contentApi.getMySubmissions();
        mySubmissions.value = res.data ?? [];
    } catch {
        submissionsError.value = 'Failed to load submissions.';
    } finally {
        if (showLoading) submissionsLoading.value = false;
    }
}

function stopSubmissionsPolling() {
    if (submissionsPollTimer) {
        clearTimeout(submissionsPollTimer);
        submissionsPollTimer = null;
    }
}

function scheduleSubmissionsPoll() {
    stopSubmissionsPolling();
    if (!mySubmissions.value.some((item) => ['pending', 'processing'].includes(item.status))) return;

    submissionsPollTimer = setTimeout(async () => {
        await loadMySubmissions(false);
        scheduleSubmissionsPoll();
    }, 2000);
}

onMounted(async () => {
    await loadMySubmissions();
    scheduleSubmissionsPoll();
});

onUnmounted(stopSubmissionsPolling);
</script>

<template>
    <div class="space-y-6">
        <UiCard>
            <UiSectionHeader title="Add YouTube source" subtitle="Submit a video to the content pipeline" />
            <div class="mt-4 grid gap-3 xl:grid-cols-[1.2fr_0.8fr]">
                <form class="space-y-3" @submit.prevent="submit">
                    <label class="block space-y-2">
                        <span class="text-xs uppercase tracking-[0.18em] text-muted-foreground">Video URL</span>
                        <UiInput v-model="form.source_url" type="url" placeholder="https://www.youtube.com/watch?v=..." required />
                    </label>
                    <SelectField v-model="form.language" label="Language" placeholder="Choose language" :options="languageOptions" />
                    <p class="text-xs text-muted-foreground">The title is pulled from the video itself — no need to type it.</p>

                    <div class="flex flex-wrap items-center gap-3">
                        <UiButton variant="primary" type="submit" :disabled="submitting">
                            {{ submitting ? 'Submitting...' : 'Submit source' }}
                        </UiButton>
                        <p v-if="submitError" class="text-sm text-warning" role="alert">{{ submitError }}</p>
                        <p v-if="submitSuccess" class="text-sm text-success-fg">Submission accepted. It will be processed shortly.</p>
                    </div>
                </form>

                <div class="space-y-3 rounded-spa-lg border border-border bg-black/10 p-4">
                    <div class="text-xs uppercase tracking-[0.18em] text-muted-foreground">Pipeline note</div>
                    <p class="text-sm leading-6 text-muted-foreground">
                        The user only sees operational status. Transcript fetch, normalization and AI analysis stay behind the pipeline.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <UiBadge tone="warning">pending</UiBadge>
                        <UiBadge tone="primary">processing</UiBadge>
                        <UiBadge tone="success">ready</UiBadge>
                    </div>
                </div>
            </div>
        </UiCard>

        <UiCard>
            <UiSectionHeader title="My submissions" subtitle="Track what is already in the pipeline" />
            <PageState :loading="submissionsLoading" :error="submissionsError || ''">
                <div class="mt-4 space-y-3">
                    <div
                        v-for="item in mySubmissions"
                        :key="item.id"
                        class="flex flex-wrap items-center justify-between gap-3 rounded-spa-lg border border-border bg-black/10 p-4"
                    >
                        <div>
                            <div class="font-medium text-fg">{{ item.title }}</div>
                            <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                                <UiBadge
                                    :tone="['pending', 'processing'].includes(item.status) ? 'warning' : item.status === 'ready' ? 'success' : 'danger'"
                                >
                                    {{ item.status }}
                                </UiBadge>
                                <span>{{ item.language }}</span>
                                <span>·</span>
                                <span>{{ item.level || 'n/a' }}</span>
                            </div>
                        </div>
                        <RouterLink
                            v-if="item.status === 'ready'"
                            :to="{ name: 'catalog.details', params: { id: item.id } }"
                            class="text-sm text-primary underline"
                        >
                            View
                        </RouterLink>
                    </div>
                    <UiEmptyState v-if="mySubmissions.length === 0" title="No submissions yet" description="Add a source and it will appear here." />
                </div>
            </PageState>
        </UiCard>
    </div>
</template>
