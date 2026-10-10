import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createMemoryHistory, createRouter } from 'vue-router';
import LessonsPage from '../LessonsPage.vue';

const api = vi.hoisted(() => ({ list: vi.fn(), create: vi.fn() }));
vi.mock('../../domains/learning', () => ({ lessonApi: api }));
vi.mock('../../domains/user', () => ({ useAuthStore: () => ({ isAuthenticated: true }) }));

async function renderLessons() {
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [
            { path: '/lessons', name: 'lessons', component: LessonsPage },
            { path: '/lessons/:id', name: 'lesson.details', component: { template: '<div />' } },
            { path: '/login', name: 'login', component: { template: '<div />' } },
        ],
    });
    await router.push('/lessons');
    return mount(LessonsPage, { global: { plugins: [router] } });
}

describe('LessonsPage', () => {
    beforeEach(() => vi.resetAllMocks());

    it('shows clean lesson cards with dates and aligned item counts', async () => {
        api.list.mockResolvedValue({
            data: [{
                id: 8, title: 'At the market', lesson_date: '2026-10-05', teacher: 'Marie', topic: 'Food', status: 'active', updated_at: '2026-10-05T12:00:00Z',
                lexeme_count: 5, grammar_count: 2, correction_count: 1,
            }],
            meta: { current_page: 1, per_page: 15, total: 1, last_page: 1 },
        });

        const wrapper = await renderLessons();
        await flushPromises();

        expect(wrapper.text()).toContain('At the market');
        expect(wrapper.text()).toContain('5 words');
        expect(wrapper.text()).toContain('2 rules');
        expect(wrapper.text()).toContain('1 correction');
        expect(wrapper.get('a[href="/lessons/8"]').find('section').classes()).toContain('bg-card');
        expect(wrapper.find('[class~="bg-black/10"]').exists()).toBe(false);
    });

    it('uses the shared status segments to switch between active, archived and all lessons', async () => {
        api.list.mockResolvedValue({ data: [], meta: { current_page: 1, per_page: 15, total: 0, last_page: 1 } });
        const wrapper = await renderLessons();
        await flushPromises();

        const status = wrapper.get('[role="group"][aria-label="Lesson status"]');
        expect(status.find('button[aria-pressed="true"]').text()).toContain('Active');
        await status.findAll('button')[1].trigger('click');
        await flushPromises();
        expect(api.list).toHaveBeenLastCalledWith(1, 'archived');
        await wrapper.get('[role="group"][aria-label="Lesson status"]').findAll('button')[2].trigger('click');
        await flushPromises();
        expect(api.list).toHaveBeenLastCalledWith(1, 'all');
    });
});
