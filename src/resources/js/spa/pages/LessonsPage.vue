<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { Plus, CalendarDays, ChevronRight } from 'lucide-vue-next';
import PageState from '../components/ui/PageState.vue';
import { useAuthStore } from '../domains/user';
import { lessonApi, type LessonSummary } from '../domains/learning';
import UiBadge from '../shared/ui/UiBadge.vue';
import UiButton from '../shared/ui/UiButton.vue';
import UiCard from '../shared/ui/UiCard.vue';
import UiEmptyState from '../shared/ui/UiEmptyState.vue';
import UiSectionHeader from '../shared/ui/UiSectionHeader.vue';
import UiSegmentedControl from '../shared/ui/UiSegmentedControl.vue';

const router = useRouter();
const authStore = useAuthStore();

const lessons = ref<LessonSummary[]>([]);
const meta = ref<{ current_page: number; per_page: number; total: number; last_page?: number }>({
    current_page: 1,
    per_page: 15,
    total: 0,
});
const loading = ref(true);
const creating = ref(false);
const error = ref('');

const statusFilter = ref<'all' | 'active' | 'archived'>('active');

const totalPages = computed(() => meta.value.last_page ?? Math.max(1, Math.ceil(meta.value.total / meta.value.per_page)));
const canPrev = computed(() => meta.value.current_page > 1);
const canNext = computed(() => meta.value.current_page < totalPages.value);

function formatDate(iso: string | null) {
    if (!iso) return '';
    try {
        return new Date(iso).toLocaleDateString();
    } catch {
        return iso;
    }
}

async function fetchLessons() {
    loading.value = true;
    error.value = '';
    try {
        const data = await lessonApi.list(meta.value.current_page, statusFilter.value);
        lessons.value = data.data ?? [];
        meta.value = data.meta ?? meta.value;
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

function goToPage(page: number) {
    if (page < 1 || page > totalPages.value) return;
    meta.value = { ...meta.value, current_page: page };
    fetchLessons();
}

const lessonStatusSegments = [
    { value: 'active', label: 'Active' },
    { value: 'archived', label: 'Archived' },
    { value: 'all', label: 'All' },
];

watch(statusFilter, () => {
    meta.value = { ...meta.value, current_page: 1 };
    fetchLessons();
});

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
        <section class="space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <UiSectionHeader
                    title="My lessons"
                    subtitle="Your lesson notes, words and grammar in one place"
                />
                <UiButton variant="primary" size="touch" :disabled="creating" @click="startNewLesson">
                    <Plus :size="16" /> {{ creating ? 'Starting...' : 'New lesson' }}
                </UiButton>
            </div>
            <UiSegmentedControl v-model="statusFilter" :segments="lessonStatusSegments" :ariaLabel="'Lesson status'" />
        </section>

        <PageState :loading="loading" :error="error">
            <template #retry>
                <UiButton variant="secondary" size="sm" @click="fetchLessons">Try again</UiButton>
            </template>
            <template v-if="lessons.length === 0 && !loading">
                <UiEmptyState
                    title="No lessons yet"
                    description="Start a new lesson to write down what you covered with your tutor."
                >
                    <UiButton variant="primary" :disabled="creating" @click="startNewLesson">New lesson</UiButton>
                </UiEmptyState>
            </template>

            <template v-else>
                <div class="space-y-3" aria-label="Lessons">
                    <RouterLink
                        v-for="lesson in lessons"
                        :key="lesson.id"
                        :to="{ name: 'lesson.details', params: { id: lesson.id } }"
                        class="group block rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        <UiCard class="flex min-w-0 flex-col gap-4 transition-all group-hover:-translate-y-0.5 group-hover:border-primary group-hover:shadow-md sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0 space-y-2">
                                <div class="flex min-w-0 items-start gap-2">
                                    <h2 class="min-w-0 break-words text-base font-semibold text-fg group-hover:text-primary">
                                        {{ lesson.title || 'Untitled lesson' }}
                                    </h2>
                                    <UiBadge v-if="lesson.status === 'archived'" tone="neutral" class="shrink-0">Archived</UiBadge>
                                </div>
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground">
                                    <span class="inline-flex items-center gap-1.5"><CalendarDays :size="14" aria-hidden="true" />{{ formatDate(lesson.lesson_date ?? lesson.updated_at) }}</span>
                                    <span v-if="lesson.teacher">{{ lesson.teacher }}</span>
                                    <span v-if="lesson.topic" class="text-fg-secondary">{{ lesson.topic }}</span>
                                </div>
                            </div>
                            <div class="flex min-w-0 items-center justify-between gap-3 sm:shrink-0 sm:justify-end">
                                <div class="flex min-w-0 flex-wrap gap-1.5">
                                    <UiBadge tone="neutral">{{ lesson.lexeme_count }} {{ lesson.lexeme_count === 1 ? 'word' : 'words' }}</UiBadge>
                                    <UiBadge tone="neutral">{{ lesson.grammar_count }} {{ lesson.grammar_count === 1 ? 'rule' : 'rules' }}</UiBadge>
                                    <UiBadge tone="neutral">{{ lesson.correction_count }} {{ lesson.correction_count === 1 ? 'correction' : 'corrections' }}</UiBadge>
                                </div>
                                <ChevronRight :size="18" class="shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:text-primary" aria-hidden="true" />
                            </div>
                        </UiCard>
                    </RouterLink>
                </div>

                <div class="flex flex-wrap items-center gap-4">
                    <span class="text-sm text-muted-foreground">Page {{ meta.current_page }} of {{ totalPages }} ({{ meta.total }} total)</span>
                    <div class="flex gap-2">
                        <UiButton variant="secondary" :disabled="!canPrev" @click="goToPage(meta.current_page - 1)">Prev</UiButton>
                        <UiButton variant="secondary" :disabled="!canNext" @click="goToPage(meta.current_page + 1)">Next</UiButton>
                    </div>
                </div>
            </template>
        </PageState>
    </div>
</template>
