<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { Plus, Filter, ChevronDown } from 'lucide-vue-next';
import PageState from '../components/ui/PageState.vue';
import { useAuthStore } from '../domains/user';
import { lessonApi, type LessonSummary } from '../domains/learning';
import UiBadge from '../shared/ui/UiBadge.vue';
import UiButton from '../shared/ui/UiButton.vue';
import UiCard from '../shared/ui/UiCard.vue';
import UiEmptyState from '../shared/ui/UiEmptyState.vue';
import UiSectionHeader from '../shared/ui/UiSectionHeader.vue';
import SelectField from '../shared/ui/SelectField.vue';

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
            <div class="mt-4 max-w-xs">
                <SelectField
                    v-model="statusFilter"
                    label="Status"
                    placeholder="Choose status"
                    :options="[
                        { value: 'all', label: 'All' },
                        { value: 'active', label: 'Active' },
                        { value: 'archived', label: 'Archived' },
                    ]"
                />
            </div>
        </UiCard>

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
                <UiCard class="space-y-2">
                    <RouterLink
                        v-for="lesson in lessons"
                        :key="lesson.id"
                        :to="{ name: 'lesson.details', params: { id: lesson.id } }"
                        class="flex flex-col gap-2 rounded-spa border border-border bg-black/10 p-3 transition-colors hover:border-primary sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0 space-y-1">
                            <div class="break-words font-medium text-fg">
                                {{ lesson.title || (lesson.lesson_date ? `Lesson on ${formatDate(lesson.lesson_date)}` : `Lesson updated ${formatDate(lesson.updated_at)}`) }}
                            </div>
                            <div class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                <span v-if="lesson.lesson_date">{{ formatDate(lesson.lesson_date) }}</span>
                                <UiBadge v-if="lesson.teacher" tone="neutral">{{ lesson.teacher }}</UiBadge>
                                <UiBadge v-if="lesson.topic" tone="primary">{{ lesson.topic }}</UiBadge>
                                <UiBadge v-if="lesson.status === 'archived'" tone="neutral">Archived</UiBadge>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <UiBadge v-if="lesson.lexeme_count > 0" tone="primary">{{ lesson.lexeme_count }} {{ lesson.lexeme_count === 1 ? 'word' : 'words' }}</UiBadge>
                            <UiBadge v-if="lesson.grammar_count > 0" tone="primary">{{ lesson.grammar_count }} grammar {{ lesson.grammar_count === 1 ? 'rule' : 'rules' }}</UiBadge>
                            <UiBadge v-if="lesson.correction_count > 0" tone="primary">{{ lesson.correction_count }} {{ lesson.correction_count === 1 ? 'correction' : 'corrections' }}</UiBadge>
                        </div>
                    </RouterLink>
                </UiCard>

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
