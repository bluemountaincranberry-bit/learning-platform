import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';


// SPA component and composable tests. Kept apart from vite.config.js so the
// Laravel plugin (dev-server/manifest concerns) stays out of the test run.
export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: { '@': fileURLToPath(new URL('./resources/js', import.meta.url)) },
    },
    test: {
        environment: 'jsdom',
        include: ['resources/js/spa/**/*.spec.ts'],
    },
});
