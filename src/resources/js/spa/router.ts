import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router';
import { getActivePinia } from 'pinia';
import { useAuthStore } from './stores/authStore';
import type { RouteMeta } from './types';

const routes: RouteRecordRaw[] = [
    {
        path: '/',
        redirect: { name: 'dashboard' },
    },
    {
        path: '/dashboard',
        name: 'dashboard',
        component: () => import('./pages/DashboardPage.vue'),
        meta: { title: 'Dashboard', section: 'home' } as RouteMeta,
    },
    {
        path: '/login',
        name: 'login',
        component: () => import('./pages/LoginPage.vue'),
        meta: { requiresGuest: true } as RouteMeta,
    },
    {
        path: '/register',
        name: 'register',
        component: () => import('./pages/RegisterPage.vue'),
        meta: { requiresGuest: true, title: 'Create account' } as RouteMeta,
    },
    {
        path: '/onboarding',
        name: 'onboarding',
        component: () => import('./pages/OnboardingPage.vue'),
        meta: { requiresAuth: true, title: 'Welcome', section: 'onboarding' } as RouteMeta,
    },
    {
        path: '/forgot-password',
        name: 'forgot-password',
        component: () => import('./pages/ForgotPasswordPage.vue'),
        meta: { requiresGuest: true, title: 'Forgot password' } as RouteMeta,
    },
    {
        path: '/reset-password',
        name: 'reset-password',
        component: () => import('./pages/ResetPasswordPage.vue'),
        meta: { requiresGuest: true, title: 'Reset password', pageKeyQuery: ['token', 'email'] } as RouteMeta,
    },
    {
        path: '/categories',
        name: 'categories',
        component: () => import('./pages/CategoriesPage.vue'),
        meta: { title: 'Categories', section: 'catalog' } as RouteMeta,
    },
    {
        path: '/catalog',
        name: 'catalog',
        component: () => import('./pages/CatalogPage.vue'),
        meta: { title: 'Catalog', section: 'catalog' } as RouteMeta,
    },
    {
        path: '/grammar',
        name: 'grammar',
        component: () => import('./pages/GrammarPage.vue'),
        meta: { title: 'Grammar', section: 'grammar' } as RouteMeta,
    },
    {
        path: '/grammar/:id',
        name: 'grammar.details',
        component: () => import('./pages/GrammarRuleDetailPage.vue'),
        meta: { title: 'Grammar rule', section: 'grammar' } as RouteMeta,
    },
    {
        path: '/grammar/:id/editor',
        name: 'grammar.editor',
        component: () => import('./pages/GrammarRuleAiEditorPage.vue'),
        meta: { requiresAuth: true, title: 'AI grammar editor', section: 'grammar', focusLayout: true },
    },
    {
        path: '/grammar/:id/practice',
        name: 'grammar.practice',
        component: () => import('./pages/GrammarPracticePage.vue'),
        meta: { requiresAuth: true, title: 'Grammar practice', section: 'grammar', focusLayout: true, pageKeyQuery: ['level', 'count', 'ids', 'content', 'from'] } as RouteMeta,
    },
    {
        path: '/add-youtube',
        name: 'add-youtube',
        component: () => import('./pages/AddYoutubePage.vue'),
        meta: { requiresAuth: true, title: 'Add source', section: 'catalog' } as RouteMeta,
    },
    {
        path: '/repetitions',
        name: 'repetitions',
        component: () => import('./pages/RepetitionsPage.vue'),
        meta: { requiresAuth: true, title: 'Practice', section: 'review', focusLayout: true } as RouteMeta,
    },
    {
        path: '/practice/context',
        name: 'context-practice',
        component: () => import('./pages/ContextPracticePage.vue'),
        meta: { requiresAuth: true, title: 'Context practice', section: 'review', focusLayout: true, pageKeyQuery: ['content_id', 'lexeme_ids', 'return_to'] } as RouteMeta,
    },
    {
        path: '/practice/speaking',
        name: 'speaking-practice',
        component: () => import('./pages/SpeakingPracticePage.vue'),
        meta: { requiresAuth: true, title: 'Sentence practice', section: 'review', focusLayout: true, pageKeyQuery: ['content_id', 'lexeme_ids', 'sentence_mode', 'return_to'] } as RouteMeta,
    },
    {
        path: '/practice/speaking/mistakes',
        name: 'speaking-mistakes',
        component: () => import('./pages/SpeakingMistakesPage.vue'),
        meta: { requiresAuth: true, title: 'My speaking mistakes', section: 'review', focusLayout: true } as RouteMeta,
    },
    {
        path: '/practice/transcript',
        name: 'transcript-exercise',
        component: () => import('./pages/TranscriptExercisePage.vue'),
        meta: { requiresAuth: true, title: 'Transcript exercise', section: 'review', focusLayout: true, pageKeyQuery: ['content_id', 'mode', 'return_to'] } as RouteMeta,
    },
    {
        path: '/my-words',
        name: 'my-words',
        component: () => import('./pages/MyWordsPage.vue'),
        meta: { requiresAuth: true, title: 'My words', section: 'learn' } as RouteMeta,
    },
    {
        path: '/my-grammar',
        name: 'my-grammar',
        component: () => import('./pages/MyGrammarPage.vue'),
        meta: { requiresAuth: true, title: 'My grammar', section: 'learn' } as RouteMeta,
    },
    {
        path: '/lessons',
        name: 'lessons',
        component: () => import('./pages/LessonsPage.vue'),
        meta: { requiresAuth: true, title: 'My lessons', section: 'lessons' } as RouteMeta,
    },
    {
        path: '/interview',
        name: 'interview',
        component: () => import('./pages/InterviewPage.vue'),
        meta: { requiresAuth: true, title: 'Interview preparation', section: 'interview' } as RouteMeta,
    },
    {
        path: '/lessons/:id',
        name: 'lesson.details',
        component: () => import('./pages/LessonDetailPage.vue'),
        meta: { requiresAuth: true, title: 'Lesson', section: 'lessons', pageKeyQuery: ['tab'] } as RouteMeta,
    },
    {
        path: '/lessons/:id/chat',
        name: 'lesson.chat',
        component: () => import('./pages/LessonDetailPage.vue'),
        meta: { requiresAuth: true, title: 'Chat', section: 'lessons', focusLayout: true } as RouteMeta,
    },
    {
        path: '/my-progress',
        name: 'my-progress',
        component: () => import('./pages/MyProgressPage.vue'),
        meta: { requiresAuth: true, title: 'My progress', section: 'home' } as RouteMeta,
    },
    {
        // /repetitions builds one adaptive session on its own now (review +
        // learn + reinforcement in a single queue) — there's no separate
        // "quick check" mode to redirect into anymore. Kept as a named
        // redirect (not a plain path redirect) so old bookmarks/links still
        // land somewhere useful, forwarding content_id along.
        path: '/check-yourself',
        name: 'check-yourself',
        redirect: (to) => ({ name: 'repetitions', query: { content_id: to.query.content_id } }),
    },
    {
        path: '/chat',
        name: 'chat',
        component: () => import('./pages/ChatPage.vue'),
        meta: { requiresAuth: true, title: 'AI chat', section: 'chat', pageKeyQuery: ['context_type', 'context_id', 'context_title'] } as RouteMeta,
    },
    {
        path: '/settings',
        name: 'settings',
        component: () => import('./pages/SettingsPage.vue'),
        meta: { requiresAuth: true, title: 'Settings', section: 'settings' } as RouteMeta,
    },
    {
        path: '/catalog/:id',
        name: 'catalog.details',
        component: () => import('./pages/ContentDetailsPage.vue'),
        meta: { title: 'Content', section: 'catalog' } as RouteMeta,
    },
    {
        path: '/catalog/:id/study',
        name: 'catalog.study',
        component: () => import('./pages/StudyPage.vue'),
        meta: { requiresAuth: true, title: 'Study', section: 'learn', pageKeyQuery: ['lexeme_ids'] } as RouteMeta,
    },
    {
        path: '/catalog/:id/exam',
        name: 'catalog.exam',
        component: () => import('./pages/ContentExamPage.vue'),
        meta: { requiresAuth: true, title: 'Ready to watch', section: 'catalog', focusLayout: true } as RouteMeta,
    },
    {
        path: '/catalog/:id/grammar-warmup',
        name: 'catalog.grammarWarmup',
        component: () => import('./pages/GrammarPreExamPage.vue'),
        meta: { requiresAuth: true, title: 'Grammar warm-up', section: 'catalog' } as RouteMeta,
    },
    {
        path: '/word/:id',
        name: 'word.details',
        component: () => import('./pages/LexemeDetailPage.vue'),
        meta: { title: 'Word', section: 'learn' } as RouteMeta,
    },
    // AI builder (graph-builder groundwork): admin-only, lazy-loaded so
    // Vue Flow and this whole section never ship in the learner-facing
    // bundle unless an admin actually navigates here — see
    // domains/ai-builder/ and the requiresAdmin guard below.
    {
        path: '/admin/ai-builder',
        name: 'admin.aiBuilder.catalog',
        component: () => import('./pages/admin/PromptCatalogPage.vue'),
        meta: { requiresAuth: true, requiresAdmin: true, title: 'AI prompt catalog', section: 'admin', focusLayout: true } as RouteMeta,
    },
    {
        path: '/admin/ai-builder/graphs/:key',
        name: 'admin.aiBuilder.graph',
        component: () => import('./pages/admin/GraphCanvasPage.vue'),
        meta: { requiresAuth: true, requiresAdmin: true, title: 'Graph builder', section: 'admin', focusLayout: true } as RouteMeta,
    },
    {
        path: '/admin/ai-builder/prompts/:key',
        name: 'admin.aiBuilder.prompt',
        component: () => import('./pages/admin/PromptEditorPage.vue'),
        meta: { requiresAuth: true, requiresAdmin: true, title: 'Prompt builder', section: 'admin', focusLayout: true } as RouteMeta,
    },
];

const router = createRouter({
    history: createWebHistory('/'),
    routes,
    scrollBehavior(to, _from, savedPosition) {
        if (savedPosition) return savedPosition;
        if (to.hash) return { el: to.hash, behavior: 'smooth' };
        return { top: 0 };
    },
});

router.beforeEach(async (to, _from) => {
    const pinia = getActivePinia();
    const auth = pinia ? useAuthStore(pinia) : null;
    if (auth && !auth.initialized) {
        await auth.checkAuth();
    }
    const isAuthenticated = auth?.isAuthenticated ?? false;
    const meta = to.meta as RouteMeta;

    if (meta.requiresGuest && isAuthenticated) {
        return { name: 'catalog' };
    }
    if (meta.requiresAuth && !isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }
    if (meta.requiresAdmin) {
        const roles = auth?.roles ?? [];
        if (!roles.includes('admin')) {
            return { name: 'catalog' };
        }
    }
});

export default router;
