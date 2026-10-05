import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import ChatMessage from '../ChatMessage.vue';

describe('ChatMessage', () => {
    it('renders safe markdown and makes canonical word/rule links tappable chips', () => {
        const wrapper = mount(ChatMessage, { props: { role: 'assistant', content: '**Try** [run](/word/42) and [Present simple](/grammar/7). <script>alert(1)</script><img src=x onerror="alert(1)"> [bad](javascript:alert(1)) [external](https://example.com/word/42)' } });
        expect(wrapper.get('strong').text()).toBe('Try');
        expect(wrapper.findAll('a.learning-mention').map((a) => a.attributes('href'))).toEqual(['/word/42', '/grammar/7']);
        expect(wrapper.find('script').exists()).toBe(false);
        expect(wrapper.find('[onerror]').exists()).toBe(false);
        expect(wrapper.find('a[href^="javascript:"]').exists()).toBe(false);
        expect(wrapper.get('a[href="https://example.com/word/42"]').classes()).not.toContain('learning-mention');
    });
    it('shows attachment status, progress, and an error with retry', async () => {
        const wrapper = mount(ChatMessage, { props: { role: 'user', content: '', attachments: [{ name: 'Lesson notes.pdf', status: 'Sending' }], loading: true } });
        expect(wrapper.text()).toContain('Lesson notes.pdf');
        expect(wrapper.text()).toContain('Sending');
        expect(wrapper.get('[role="status"]').text()).toContain('Thinking');
        await wrapper.setProps({ loading: false, error: 'Upload failed', retryable: true });
        expect(wrapper.get('[role="alert"]').text()).toContain('Upload failed');
        await wrapper.get('button').trigger('click');
        expect(wrapper.emitted('retry')).toHaveLength(1);
    });
});
