import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createMemoryHistory, createRouter } from 'vue-router';
import ChatPage from '../ChatPage.vue';

const api = vi.hoisted(() => ({
    createConversation: vi.fn(),
    streamMessage: vi.fn(),
}));

vi.mock('../../domains/ai', () => ({ tutorApi: api }));
vi.mock('../../domains/user', () => ({ useAuthStore: () => ({ isAuthenticated: true, canAccessTutorAgent: true }) }));

async function renderChat() {
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [{ path: '/chat', component: ChatPage }],
    });
    await router.push('/chat');
    return mount(ChatPage, { global: { plugins: [router], stubs: { QuizCard: true } } });
}

describe('ChatPage focused conversation', () => {
    beforeEach(() => {
        vi.resetAllMocks();
        Element.prototype.scrollIntoView = vi.fn();
        document.body.style.overflow = '';
    });

    it('toggles the conversation over the app shell without losing the draft or message history', async () => {
        api.createConversation.mockResolvedValue({ conversation_id: 19 });
        api.streamMessage.mockImplementation(async (_id, _text, onChunk) => {
            onChunk('Here is a useful explanation.');
            return { quiz: [] };
        });
        const wrapper = await renderChat();
        const composer = wrapper.get('input[aria-label="Message the AI tutor"]');
        await composer.setValue('Explain the present perfect');

        await wrapper.get('button[aria-label="Full screen"]').trigger('click');
        expect(wrapper.classes()).toContain('fixed');
        expect(document.body.style.overflow).toBe('hidden');
        expect(wrapper.get('input[aria-label="Message the AI tutor"]').element).toBe(composer.element);
        expect((composer.element as HTMLInputElement).value).toBe('Explain the present perfect');

        await wrapper.get('button[aria-label="Exit full screen"]').trigger('click');
        expect(wrapper.classes()).not.toContain('fixed');
        expect(document.body.style.overflow).toBe('');
        expect((composer.element as HTMLInputElement).value).toBe('Explain the present perfect');

        await wrapper.get('button[aria-label="Full screen"]').trigger('click');
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(wrapper.text()).toContain('Explain the present perfect');
        expect(wrapper.text()).toContain('Here is a useful explanation.');
        await wrapper.get('button[aria-label="Exit full screen"]').trigger('click');
        expect(wrapper.text()).toContain('Here is a useful explanation.');
        expect(api.streamMessage).toHaveBeenCalledTimes(1);
    });

    it('leaves full-screen mode with Escape and keeps the composer available in the regular layout', async () => {
        const wrapper = await renderChat();
        await wrapper.get('button[aria-label="Full screen"]').trigger('click');
        expect(wrapper.get('[aria-label="Conversation"]').classes()).toContain('rounded-none');
        expect(wrapper.get('input[aria-label="Message the AI tutor"]').exists()).toBe(true);

        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await flushPromises();
        expect(wrapper.classes()).not.toContain('fixed');
        expect(wrapper.get('[aria-label="Conversation"]').classes()).toContain('min-h-[440px]');
    });
});
