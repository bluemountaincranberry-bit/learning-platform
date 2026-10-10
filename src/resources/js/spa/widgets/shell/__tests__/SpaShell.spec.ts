import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { defineComponent, h, ref } from 'vue';
import { createMemoryHistory, createRouter, useRouter } from 'vue-router';
import SpaShell from '../SpaShell.vue';

const auth = vi.hoisted(() => ({
    isAuthenticated: false,
    user: null as { name: string } | null,
    canAccessTutorAgent: true,
    logout: vi.fn(),
    fetchProfile: vi.fn(),
}));

vi.mock('../../../domains/user', () => ({
    useAuth: () => ({
        isAuthenticated: ref(auth.isAuthenticated),
        user: ref(auth.user),
        canAccessTutorAgent: ref(auth.canAccessTutorAgent),
        logout: auth.logout,
    }),
    useProfileStore: () => ({ profile: ref(null), fetchProfile: auth.fetchProfile }),
}));

vi.mock('pinia', () => ({
    storeToRefs: (store: { profile: ReturnType<typeof ref> }) => ({ profile: store.profile }),
}));

describe('SpaShell page identity', () => {
    beforeEach(() => {
        auth.isAuthenticated = false;
        auth.user = null;
        auth.canAccessTutorAgent = true;
        auth.logout.mockReset();
        auth.fetchProfile.mockReset();
    });

    it('keeps the active practice session when its query is updated', async () => {
        let practiceMounts = 0;
        const PracticePage = defineComponent({
            setup() {
                practiceMounts += 1;
                const router = useRouter();
                const sessionStarted = ref(false);
                return () => sessionStarted.value
                    ? h('div', 'Practice session started')
                    : h('button', {
                        onClick: async () => {
                            sessionStarted.value = true;
                            await router.replace('/repetitions?lesson_id=13&mode=adaptive&source_ids=lesson%3A13');
                        },
                    }, 'Start practice');
            },
        });
        const router = createRouter({
            history: createMemoryHistory(),
            routes: [
                { path: '/repetitions', name: 'repetitions', component: PracticePage, meta: { focusLayout: true } },
            ],
        });
        await router.push('/repetitions?lesson_id=13');
        await router.isReady();

        const wrapper = mount(SpaShell, {
            global: {
                plugins: [router],
                stubs: { UiBadge: true, UiButton: true, UiDialog: true, RouterLink: true },
            },
        });
        await flushPromises();
        expect(practiceMounts).toBe(1);

        await wrapper.get('button').trigger('click');
        await flushPromises();

        expect(practiceMounts).toBe(1);
        expect(wrapper.text()).toContain('Practice session started');
        expect(wrapper.text()).not.toContain('Start practice');
        wrapper.unmount();
    });

    it('remounts only when a route declares query fields as page identity', async () => {
        let pageMounts = 0;
        const ConfiguredPage = defineComponent({
            setup() {
                pageMounts += 1;
                return () => h('div', 'Configured page');
            },
        });
        const router = createRouter({
            history: createMemoryHistory(),
            routes: [
                { path: '/grammar/:id/practice', name: 'grammar.practice', component: ConfiguredPage, meta: { focusLayout: true, pageKeyQuery: ['count'] } },
            ],
        });
        await router.push('/grammar/4/practice?count=5');
        await router.isReady();

        const wrapper = mount(SpaShell, {
            global: {
                plugins: [router],
                stubs: { UiBadge: true, UiButton: true, UiDialog: true, RouterLink: true },
            },
        });
        await flushPromises();
        await router.replace('/grammar/4/practice?count=10');
        await flushPromises();

        expect(pageMounts).toBe(2);
        wrapper.unmount();
    });
});
