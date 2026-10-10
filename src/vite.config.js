import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { lessonPwa } from './scripts/pwa/vite-pwa.mjs';

export default defineConfig({
    plugins: [
        vue(),
        lessonPwa(),
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/spa/main.ts'],
            refresh: true,
        }),
    ],
    server: {
        host: '0.0.0.0',
        // Laravel's @vite() bakes this origin into every script/link tag it
        // renders. Without it, a phone loading the page via the LAN IP still
        // gets tags pointing at "localhost" — which on the phone resolves to
        // the phone itself, not this machine — so all JS/CSS 404s silently.
        // Set VITE_DEV_SERVER_ORIGIN (see docker-compose.yml) to fix this.
        origin: process.env.VITE_DEV_SERVER_ORIGIN || undefined,
        // Without this, Vite's CORS middleware echoes back `origin` above as
        // the Access-Control-Allow-Origin value instead of reflecting the
        // actual request Origin — the browser then blocks the cross-port
        // module script fetch (8088 page fetching JS from 5177) even though
        // the request itself succeeds (200). `cors: true` forces true
        // per-request reflection instead of falling back to a fixed value.
        cors: true,
        // Containerized Playwright reaches Vite through the compose DNS name.
        // Keep the allow-list explicit instead of enabling all hosts.
        allowedHosts: [
            'localhost',
            'node',
            ...(process.env.VITE_DEV_SERVER_ORIGIN ? [new URL(process.env.VITE_DEV_SERVER_ORIGIN).hostname] : []),
        ],
    },
});
