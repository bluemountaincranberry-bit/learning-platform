/* Build placeholders are replaced by scripts/pwa/vite-pwa.mjs. */
const VERSION = __PWA_VERSION__;
const ASSETS = __PWA_ASSETS__;
const PREFIX = 'learning-pwa-';
const ASSET_CACHE = `${PREFIX}assets-${VERSION}`;
const PRIVATE_CACHE = `${PREFIX}private-${VERSION}`;
const SESSION_CACHE = `${PREFIX}session`;
const SESSION_URL = `${self.location.origin}/__pwa_session`;
const APP_ROUTES = new Set(['', 'dashboard', 'login', 'register', 'onboarding', 'forgot-password', 'reset-password', 'categories', 'catalog', 'grammar', 'add-youtube', 'repetitions', 'practice', 'my-words', 'my-grammar', 'lessons', 'my-progress', 'check-yourself', 'chat', 'settings', 'word']);
let operations = Promise.resolve();

// Serialize cache changes, including logout, without holding up network requests.
function exclusive(operation) {
    const pending = operations.then(operation);
    operations = pending.catch(() => {});
    return pending;
}
async function currentSession() {
    const cache = await caches.open(SESSION_CACHE);
    const response = await cache.match(SESSION_URL);
    return response ? response.json() : { session: null, generation: null };
}
async function setSession(session) {
    const current = await currentSession();
    if (current.session === session) return;
    for (const name of await caches.keys()) {
        if (name.startsWith(`${PREFIX}private-`)) await caches.delete(name);
    }
    const cache = await caches.open(SESSION_CACHE);
    await cache.put(SESSION_URL, new Response(JSON.stringify({ session, generation: crypto.randomUUID() })));
}
async function credentialSession(request) {
    const authorization = request.headers.get('Authorization');
    if (!authorization?.startsWith('Bearer ')) return null;
    const digest = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(authorization.slice(7)));
    return Array.from(new Uint8Array(digest), (byte) => byte.toString(16).padStart(2, '0')).join('');
}
function isPrivateRead(path) {
    return path === '/api/auth/me' || path === '/api/lessons' || /^\/api\/lessons\/\d+(\/messages)?$/.test(path);
}
async function evictLesson(cache, path) {
    for (const entry of await cache.keys()) {
        const cachedPath = new URL(entry.url).pathname;
        if (cachedPath === path || cachedPath.startsWith(path + '/')) await cache.delete(entry);
    }
}
async function pruneLessons(cache) {
    const entries = await cache.keys();
    const recentIds = [...new Set(entries.slice().reverse().map((entry) => new URL(entry.url).pathname.match(/^\/api\/lessons\/(\d+)/)?.[1]).filter(Boolean))];
    const expired = new Set(recentIds.slice(10));
    for (const entry of entries) {
        const id = new URL(entry.url).pathname.match(/^\/api\/lessons\/(\d+)/)?.[1];
        if (expired.has(id)) await cache.delete(entry);
    }
}
async function privateRead(request) {
    const session = await credentialSession(request);
    const initial = await exclusive(currentSession).catch(() => ({ session: null, generation: null }));
    if (!session || initial.session !== session) return fetch(request);
    const key = request.url;
    try {
        const response = await fetch(request);
        await exclusive(async () => {
            const current = await currentSession();
            if (current.generation !== initial.generation) return;
            if (response.status === 401 || response.status === 403) {
                await setSession(null);
                return;
            }
            if (response.status === 404) {
                const cache = await caches.open(PRIVATE_CACHE);
                const lessonPath = new URL(key).pathname.replace(/\/messages$/, '');
                await evictLesson(cache, lessonPath);
            }
            if (response.ok && response.headers.get('Content-Type')?.includes('application/json')) {
                const cache = await caches.open(PRIVATE_CACHE);
                await cache.delete(key);
                await cache.put(key, response.clone());
                await pruneLessons(cache);
            }
        }).catch(() => {});
        return response;
    } catch (error) {
        const cached = await exclusive(async () => {
            const current = await currentSession();
            if (current.generation !== initial.generation) return undefined;
            return (await caches.open(PRIVATE_CACHE)).match(key);
        }).catch(() => undefined);
        if (cached) return cached;
        throw error;
    }
}
self.addEventListener('message', (event) => {
    if (event.data?.type !== 'PWA_SESSION' || !event.source?.id) return;
    event.waitUntil(exclusive(async () => {
        const client = await self.clients.get(event.source.id);
        if (!client || new URL(client.url).origin !== self.location.origin) return;
        const session = event.data.session;
        if (session !== null && !/^[a-f0-9]{64}$/.test(session)) return;
        await setSession(session);
        event.ports[0]?.postMessage({ ok: true });
    }));
});
self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;
    if (request.method === 'GET' && isPrivateRead(url.pathname)) {
        event.respondWith(privateRead(request));
    } else if (request.method === 'GET' && ASSETS.includes(url.pathname)) {
        event.respondWith((async () => (await (await caches.open(ASSET_CACHE)).match(request)) || fetch(request))());
    } else if (request.mode === 'navigate' && APP_ROUTES.has(url.pathname.split('/')[1])) {
        // Keep HTML and lazy assets on the active release, even while a new worker waits.
        event.respondWith((async () => (await (await caches.open(ASSET_CACHE)).match(`${self.location.origin}/build/offline.html`)) || fetch(request))());
    } else if (request.method !== 'GET' && /^\/api\/lessons(\/|$)/.test(url.pathname)) {
        event.respondWith((async () => {
            const session = await credentialSession(request);
            const initial = await exclusive(currentSession).catch(() => ({ session: null }));
            const response = await fetch(request);
            if (response.ok && session && initial.session === session) await exclusive(async () => {
                if ((await currentSession()).generation !== initial.generation) return;
                const cache = await caches.open(PRIVATE_CACHE);
                const lessonPath = url.pathname.match(/^\/api\/lessons\/\d+/)?.[0];
                if (lessonPath) {
                    // Sending a note changes messages; the existing UI does not refetch detail.
                    const invalidated = request.method === 'POST' && url.pathname === lessonPath + '/messages' ? url.pathname : lessonPath;
                    await evictLesson(cache, invalidated);
                }
            }).catch(() => {});
            return response;
        })());
    }
});

self.addEventListener('install', (event) => {
    // Keep existing tabs on their matching release until they close: no skipWaiting.
    event.waitUntil(caches.open(ASSET_CACHE).then((cache) => cache.addAll(ASSETS)));
});
self.addEventListener('activate', (event) => {
    event.waitUntil(exclusive(async () => {
        for (const name of await caches.keys()) {
            if (name.startsWith(PREFIX) && ![ASSET_CACHE, PRIVATE_CACHE, SESSION_CACHE].includes(name)) await caches.delete(name);
        }
        await self.clients.claim();
    }));
});
