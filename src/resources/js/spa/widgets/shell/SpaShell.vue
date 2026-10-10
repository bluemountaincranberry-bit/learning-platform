<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { storeToRefs } from 'pinia';
import { useRoute, useRouter } from 'vue-router';
import {
    BookMarked,
    BookOpen,
    Dumbbell,
    GraduationCap,
    LayoutDashboard,
    MessageCircle,
    MoreHorizontal,
    NotebookPen,
    NotebookText,
    Settings as SettingsIcon,
    TrendingUp,
    BriefcaseBusiness,
} from 'lucide-vue-next';
import { useAuth } from '../../domains/user';
import { useProfileStore } from '../../domains/user';
import UiBadge from '../../shared/ui/UiBadge.vue';
import UiButton from '../../shared/ui/UiButton.vue';
import UiDialog from '../../shared/ui/UiDialog.vue';
import { routeViewKey } from './routeViewKey';

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
    { name: 'interview', label: 'Interview prep', section: 'interview', icon: BriefcaseBusiness },
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

const moreOpen = ref(false);
const moreMenu = ref<HTMLElement | null>(null);
const mobileNavItems = [
    { name: 'catalog', label: 'Catalog', icon: BookOpen },
    { name: 'lessons', label: 'Lessons', icon: NotebookText },
    { name: 'my-words', label: 'Words', icon: BookMarked },
    { name: 'repetitions', label: 'Practice', icon: Dumbbell },
];
const moreDestinations = ['dashboard', 'grammar', 'my-grammar', 'my-progress', 'interview', 'chat', 'settings'];
const moreNavItems = computed(() => navItems.value
    .filter((item) => moreDestinations.includes(item.name))
    .map((item) => item.name === 'dashboard' ? { ...item, label: 'Today' } : item));

function isMobileDestinationActive(name: string): boolean {
    if (route.name === name) return true;
    if (name === 'dashboard') return route.name === 'dashboard';
    if (name === 'lessons') return section.value === 'lessons';
    if (name === 'my-words') return route.name === 'word.details';
    if (name === 'repetitions') return section.value === 'review';
    if (name === 'grammar') return section.value === 'grammar';
    if (name === 'catalog') return section.value === 'catalog' || String(route.name).startsWith('catalog.');
    return false;
}

const moreActive = computed(() => moreNavItems.value.some((item) => isMobileDestinationActive(item.name)));
watch(() => route.fullPath, () => { moreOpen.value = false; });
// Start on a menu link so the shared dialog traps Tab in both directions.
watch(moreOpen, async (open) => {
    if (!open) return;
    await nextTick();
    moreMenu.value?.querySelector<HTMLAnchorElement>('a')?.focus();
}, { flush: 'post' });
</script>

<template>
    <main v-if="focusLayout" class="min-h-screen bg-background text-fg">
        <RouterView v-slot="{ Component, route: viewRoute }">
            <Transition name="page" mode="out-in">
                <component :is="Component" :key="routeViewKey(viewRoute)" />
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

            <div class="flex min-w-0 flex-1 flex-col pb-[calc(5rem+env(safe-area-inset-bottom))] lg:pb-0">
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
                            <component :is="Component" :key="routeViewKey(viewRoute)" />
                        </Transition>
                    </RouterView>
                </main>
            </div>
        </div>

        <nav aria-label="Mobile navigation" class="fixed inset-x-0 bottom-0 z-30 border-t border-border bg-background/95 pb-[env(safe-area-inset-bottom)] shadow-[0_-8px_28px_rgba(15,23,42,0.06)] backdrop-blur lg:hidden">
            <div class="grid grid-cols-5 gap-1 px-2 py-1.5">
                <RouterLink
                    v-for="item in mobileNavItems"
                    :key="item.name"
                    :to="{ name: item.name }"
                    :aria-current="isMobileDestinationActive(item.name) ? 'page' : undefined"
                    class="flex min-h-11 min-w-0 flex-col items-center justify-center gap-0.5 rounded-sm px-1 py-1.5 text-center transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    :class="isMobileDestinationActive(item.name) ? 'bg-primary/10 text-primary' : 'text-fg-secondary hover:bg-surface-alt'"
                >
                    <component :is="item.icon" :size="20" aria-hidden="true" />
                    <span class="text-[11px] leading-tight">{{ item.label }}</span>
                </RouterLink>
                <button
                    type="button"
                    aria-haspopup="dialog"
                    :aria-expanded="moreOpen"
                    :aria-current="moreActive ? 'true' : undefined"
                    class="flex min-h-11 min-w-0 flex-col items-center justify-center gap-0.5 rounded-sm px-1 py-1.5 text-center transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    :class="moreActive || moreOpen ? 'bg-primary/10 text-primary' : 'text-fg-secondary hover:bg-surface-alt'"
                    @click="moreOpen = true"
                >
                    <MoreHorizontal :size="20" aria-hidden="true" />
                    <span class="text-[11px] leading-tight">More</span>
                </button>
            </div>
        </nav>

        <UiDialog :open="moreOpen" title="More" sheet @close="moreOpen = false">
            <nav ref="moreMenu" aria-label="More destinations" class="space-y-1">
                <RouterLink
                    v-for="item in moreNavItems"
                    :key="item.name"
                    :to="{ name: item.name }"
                    :aria-current="isMobileDestinationActive(item.name) ? 'page' : undefined"
                    class="flex min-h-11 items-center gap-3 rounded-spa px-3 py-3 text-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    :class="isMobileDestinationActive(item.name) ? 'bg-primary/10 text-primary' : 'text-fg-secondary hover:bg-surface-alt'"
                    @click="moreOpen = false"
                >
                    <component :is="item.icon" :size="20" aria-hidden="true" />
                    <span>{{ item.label }}</span>
                </RouterLink>
            </nav>
        </UiDialog>
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
