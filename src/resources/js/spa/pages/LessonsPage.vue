<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { Plus } from 'lucide-vue-next';
import PageState from '../components/ui/PageState.vue';
import { useAuthStore } from '../domains/user';
import { lessonApi, type LessonSummary } from '../domains/learning';
import UiBadge from '../shared/ui/UiBadge.vue';
import UiButton from '../shared/ui/UiButton.vue';
import UiCard from '../shared/ui/UiCard.vue';
import UiEmptyState from '../shared/ui/UiEmptyState.vue';
import UiSectionHeader from '../shared/ui/UiSectionHeader.vue';

const router = useRouter();
const authStore = useAuthStore();

const lessons = ref<LessonSummary[]>([]);
const loading = ref(true);
const creating = ref(false);
const error = ref('');

async function fetchLessons() {
    loading.value = true;
    error.value = '';
    try {
        const data = await lessonApi.list();
        lessons.value = data.data ?? [];
    } catch (e: unknown) {
        const err = e as { response?: { status?: number; data?: { message?: string } } };
        if (err.response?.status === 401) {
            router.push({ name: 'login', query: { redirect: '/lessons' } });
            return;
        }
        error.value = err.response?.data?.message ?? 'Failed to load your lessons.';
    } finally {
        loading.value = false;
    }
}

async function startNewLesson() {
    creating.value = true;
    error.value = '';
    try {
        const { lesson_id } = await lessonApi.create();
        router.push({ name: 'lesson.details', params: { id: lesson_id } });
    } catch (e: unknown) {
        const err = e as { response?: { status?: number; data?: { message?: string } } };
        error.value = err.response?.data?.message ?? 'Failed to start a new lesson.';
    } finally {
        creating.value = false;
    }
}

function formatDate(iso: string) {
    try {
        return new Date(iso).toLocaleString();
    } catch {
        return iso;
    }
}

onMounted(async () => {
    if (!authStore.isAuthenticated) {
        router.push({ name: 'login', query: { redirect: '/lessons' } });
        return;
    }
    await fetchLessons();
});
</script>

<template>
    <div class="space-y-6">
        <UiCard>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <UiSectionHeader
                    title="My lessons"
                    subtitle="Notes from your tutor lessons — the words and grammar pulled out of them"
                />
                <UiButton variant="primary" :disabled="creating" @click="startNewLesson">
                    <Plus :size="16" /> New lesson
                </UiButton>
            </div>
        </UiCard>

        <PageState :loading="loading" :error="error">
            <template v-if="lessons.length === 0 && !loading">
                <UiEmptyState
                    title="No lessons yet"
                    description="Start a new lesson to write down what you covered with your tutor."
                >
                    <UiButton variant="primary" :disabled="creating" @click="startNewLesson">New lesson</UiButton>
                </UiEmptyState>
            </template>

            <template v-else>
                <UiCard class="space-y-2">
                    <RouterLink
                        v-for="lesson in lessons"
                        :key="lesson.id"
                        :to="{ name: 'lesson.details', params: { id: lesson.id } }"
                        class="flex flex-col gap-2 rounded-spa border border-border bg-black/10 p-3 transition-colors hover:border-primary sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0 space-y-1">
                            <div class="break-words font-medium text-fg">{{ lesson.title || `Lesson of ${formatDate(lesson.updated_at)}` }}</div>
                            <div class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                <span>{{ formatDate(lesson.updated_at) }}</span>
                                <UiBadge v-if="lesson.tutor" tone="neutral">{{ lesson.tutor }}</UiBadge>
                                <UiBadge v-if="lesson.status === 'archived'" tone="neutral">Archived</UiBadge>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <UiBadge v-if="lesson.lexeme_count > 0" tone="primary">{{ lesson.lexeme_count }} {{ lesson.lexeme_count === 1 ? 'word' : 'words' }}</UiBadge>
                            <UiBadge v-if="lesson.grammar_count > 0" tone="primary">{{ lesson.grammar_count }} grammar {{ lesson.grammar_count === 1 ? 'rule' : 'rules' }}</UiBadge>
                        </div>
                    </RouterLink>
                </UiCard>
            </template>
        </PageState>
    </div>
</template>
