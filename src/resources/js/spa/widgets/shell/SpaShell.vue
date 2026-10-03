<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { storeToRefs } from 'pinia';
import { useRoute, useRouter } from 'vue-router';
import {
    BookMarked,
    BookOpen,
    Dumbbell,
    GraduationCap,
    LayoutDashboard,
    MessageCircle,
    NotebookPen,
    NotebookText,
    Settings as SettingsIcon,
    TrendingUp,
} from 'lucide-vue-next';
import { useAuth } from '../../domains/user';
import { useProfileStore } from '../../domains/user';
import UiBadge from '../../shared/ui/UiBadge.vue';
import UiButton from '../../shared/ui/UiButton.vue';

const route = useRoute();
const router = useRouter();
const { isAuthenticated, user, canAccessTutorAgent, logout: handleLogout } = useAuth();
const profileStore = useProfileStore();
const { profile } = storeToRefs(profileStore);

const userName = computed(() => user.value?.name || 'Operator');
const pageTitle = computed(() => ((route.meta?.title as string | undefined) || 'Learning App'));
const section = computed(() => (route.meta?.section as string | undefined) || 'home');
const focusLayout = computed(() => Boolean(route.meta?.focusLayout));

const quickStats = computed(() => {
    const p = profile.value;
    if (!p) return [];
    return [
        { label: 'Today', value: p.today_learned_count },
        { label: 'Streak', value: p.streak_days },
        { label: 'Goal', value: p.user.daily_goal ?? '—' },
    ];
});

onMounted(async () => {
    if (isAuthenticated.value && !profile.value) {
        await profileStore.fetchProfile();
    }
});

async function logout() {
    await handleLogout();
    router.push({ name: 'login' });
}

const allNavItems = [
    { name: 'dashboard', label: 'Dashboard', section: 'home', icon: LayoutDashboard },
    { name: 'catalog', label: 'Catalog', section: 'catalog', icon: BookOpen },
    { name: 'grammar', label: 'Grammar', section: 'grammar', icon: GraduationCap },
    { name: 'repetitions', label: 'Practice', section: 'review', icon: Dumbbell },
    { name: 'my-words', label: 'My words', section: 'learn', icon: BookMarked },
    { name: 'my-grammar', label: 'My grammar', section: 'learn', icon: NotebookPen },
    { name: 'lessons', label: 'My lessons', section: 'lessons', icon: NotebookText },
    { name: 'my-progress', label: 'Progress', section: 'home', icon: TrendingUp },
    { name: 'chat', label: 'AI chat', section: 'chat', icon: MessageCircle },
    { name: 'settings', label: 'Settings', section: 'settings', icon: SettingsIcon },
];

// Task 6.5: the tutor chat is a student-only surface (access-tutor-agent
// gate, task 3.8) — admin/editor/moderator accounts would only ever hit a
// 403 behind it, so the nav item is hidden rather than left as a dead end.
// canAccessTutorAgent defaults to true (roles start empty) so a guest or a
// not-yet-loaded-roles student still sees the item; it only ever hides it,
// never wrongly shows it to staff, once /api/auth/me has answered.
// Lessons are available to every authenticated user, including staff.
const STAFF_ONLY_HIDDEN = ['chat'];
const navItems = computed(() =>
    allNavItems.filter((item) => !STAFF_ONLY_HIDDEN.includes(item.name) || canAccessTutorAgent.value)
);
</script>

<template>
    <main v-if="focusLayout" class="min-h-screen bg-background text-fg">
        <RouterView v-slot="{ Component, route: viewRoute }">
            <Transition name="page" mode="out-in">
                <component :is="Component" :key="viewRoute.fullPath" />
            </Transition>
        </RouterView>
    </main>

    <div v-else class="min-h-screen text-fg">
        <div class="mx-auto flex min-h-screen max-w-[1600px] flex-col lg:flex-row">
            <aside class="hidden w-72 shrink-0 border-r border-border bg-card px-4 py-5 lg:flex lg:flex-col">
                <div class="space-y-5">
                    <div class="space-y-2">
                        <div class="text-[11px] uppercase tracking-[0.3em] text-muted-foreground">Underground learning stack</div>
                        <div class="text-2xl font-semibold">Learning App</div>
                        <div class="text-sm text-muted-foreground">Content, words, grammar, review, tutor.</div>
                    </div>

                    <nav class="space-y-1">
                        <RouterLink
                            v-for="item in navItems"
                            :key="item.name"
                            :to="{ name: item.name }"
                            class="flex items-center justify-between rounded-spa px-3 py-2 text-sm transition-colors"
                            :class="route.name === item.name ? 'bg-primary text-white' : 'text-fg-secondary hover:bg-surface-alt hover:text-fg'"
                        >
                            <span>{{ item.label }}</span>
                            <UiBadge v-if="section === item.section" tone="primary">live</UiBadge>
                        </RouterLink>
                    </nav>
                </div>

                <div class="mt-auto space-y-3 pt-6">
                    <div v-if="isAuthenticated" class="space-y-3 rounded-xl border border-border bg-muted/30 p-4">
                        <div class="text-sm font-medium text-fg">{{ userName }}</div>
                        <div class="grid grid-cols-3 gap-2">
                            <div v-for="stat in quickStats" :key="stat.label" class="rounded-lg border border-border bg-background px-2 py-2 text-center">
                                <div class="text-[10px] uppercase tracking-[0.18em] text-muted-foreground">{{ stat.label }}</div>
                                <div class="mt-1 text-base font-semibold">{{ stat.value }}</div>
                            </div>
                        </div>
                        <UiButton variant="secondary" class="w-full" @click="logout">Logout</UiButton>
                    </div>

                    <div v-else class="space-y-2 rounded-xl border border-border bg-muted/30 p-4">
                        <div class="text-sm text-muted-foreground">Guest access</div>
                        <UiButton variant="primary" class="w-full" @click="router.push({ name: 'register' })">Create account</UiButton>
                        <UiButton variant="ghost" class="w-full" @click="router.push({ name: 'login' })">Login</UiButton>
                    </div>
                </div>
            </aside>

            <div class="flex min-w-0 flex-1 flex-col pb-20 lg:pb-0">
                <header class="sticky top-0 z-20 border-b border-border bg-background/90 backdrop-blur supports-[backdrop-filter]:bg-background/75">
                    <div class="flex items-center justify-between gap-4 px-4 py-4 lg:px-8">
                        <div class="min-w-0">
                            <div class="text-[11px] uppercase tracking-[0.28em] text-muted-foreground">Current surface</div>
                            <h1 class="truncate text-xl font-semibold text-fg">{{ pageTitle }}</h1>
                        </div>
                        <div class="flex items-center gap-3 text-sm">
                            <span v-if="isAuthenticated" class="hidden rounded-sm border border-border bg-muted px-2 py-1 text-[11px] uppercase tracking-[0.16em] text-muted-foreground sm:inline">
                                synced
                            </span>
                            <span class="hidden text-muted-light sm:inline">{{ route.name }}</span>
                            <UiButton v-if="isAuthenticated" variant="ghost" size="sm" @click="logout">Logout</UiButton>
                            <UiButton v-else variant="ghost" size="sm" @click="router.push({ name: 'login' })">Login</UiButton>
                        </div>
                    </div>
                </header>

                <main class="flex-1 px-4 py-6 lg:px-8">
                    <RouterView v-slot="{ Component, route: viewRoute }">
                        <Transition name="page" mode="out-in">
                            <component :is="Component" :key="viewRoute.fullPath" />
                        </Transition>
                    </RouterView>
                </main>
            </div>
        </div>

        <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-border bg-background/95 shadow-[0_-8px_28px_rgba(15,23,42,0.06)] backdrop-blur lg:hidden">
            <div class="relative">
                <div class="flex gap-1 overflow-x-auto px-2 py-1.5">
                    <RouterLink
                        v-for="item in navItems"
                        :key="item.name"
                        :to="{ name: item.name }"
                        class="flex w-16 shrink-0 flex-col items-center gap-0.5 rounded-sm px-1 py-1.5 text-center transition-colors"
                        :class="route.name === item.name ? 'bg-primary/10 text-primary' : 'text-fg-secondary'"
                    >
                        <component :is="item.icon" :size="20" />
                        <span class="w-full truncate text-[10px] leading-tight">{{ item.label }}</span>
                    </RouterLink>
                </div>
                <!-- Hints there's more to scroll to when not every destination fits on screen at once. -->
                <div
                    v-if="navItems.length > 5"
                    class="pointer-events-none absolute inset-y-0 right-0 w-8 bg-gradient-to-l from-background to-transparent"
                    aria-hidden="true"
                ></div>
            </div>
        </nav>
    </div>
</template>

<style scoped>
.page-enter-active,
.page-leave-active {
    transition: opacity 0.18s ease-out, transform 0.18s ease-out;
}
.page-enter-from {
    opacity: 0;
    transform: translateY(12px);
}
.page-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}
</style>
