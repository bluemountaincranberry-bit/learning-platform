import assert from 'node:assert/strict';
import { test } from 'node:test';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';
import { webcrypto } from 'node:crypto';

const origin = 'https://learning.test';
class MemoryCache {
    entries = new Map();
    async match(request) { return this.entries.get(typeof request === 'string' ? request : request.url)?.clone(); }
    async put(request, response) { this.entries.set(typeof request === 'string' ? request : request.url, response.clone()); }
    async delete(request) { return this.entries.delete(typeof request === 'string' ? request : request.url); }
    async keys() { return [...this.entries.keys()].map((url) => new Request(url)); }
    async addAll(urls) { for (const url of urls) await this.put(origin + url, new Response('asset')); }
}
async function worker(version = 'release-1', storage = new Map()) {
    const listeners = {};
    let network = async (request) => new Response(JSON.stringify({ path: new URL(request.url).pathname, note: 'My lesson notes' }), { headers: { 'Content-Type': 'application/json' } });
    const caches = {
        open: async (name) => { if (!storage.has(name)) storage.set(name, new MemoryCache()); return storage.get(name); },
        keys: async () => [...storage.keys()],
        delete: async (name) => storage.delete(name),
    };
    const source = (await readFile(new URL('../../resources/js/spa/infrastructure/pwa/service-worker.js', import.meta.url), 'utf8'))
        .replace('__PWA_VERSION__', JSON.stringify(version)).replace('__PWA_ASSETS__', JSON.stringify(['/build/offline.html', '/build/assets/main-test.js']));
    vm.runInNewContext(source, { self: { location: { origin }, addEventListener: (type, fn) => { listeners[type] = fn; }, clients: { claim: async () => {}, get: async () => ({ url: origin + '/lessons/1' }) } }, caches, crypto: webcrypto, Request, Response, URL, TextEncoder, Uint8Array, fetch: (request) => network(typeof request === 'string' ? new Request(origin + request) : request), console });
    async function event(type, properties = {}) {
        const pending = [];
        let response;
        listeners[type]({ ...properties, waitUntil: (promise) => pending.push(promise), respondWith: (promise) => { response = promise; } });
        const result = await response;
        await Promise.all(pending);
        return result;
    }
    return {
        storage, caches, event,
        session: async (token) => event('message', { source: { id: 'client' }, data: { type: 'PWA_SESSION', session: token ? Buffer.from(await webcrypto.subtle.digest('SHA-256', new TextEncoder().encode(token))).toString('hex') : null }, ports: [{ postMessage() {} }] }),
        request: (path, token = 'alice', method = 'GET') => event('fetch', { request: new Request(origin + path, { method, headers: token ? { Authorization: `Bearer ${token}` } : {} }) }),
        network: (handler) => { network = handler; },
        offline: () => { network = async () => { throw new TypeError('offline'); }; },
    };
}

test('opened lesson and auth can be read after cold offline restart with the same session', async () => {
    const sw = await worker();
    await sw.session('alice');
    await sw.request('/api/auth/me');
    await sw.request('/api/lessons/1');
    await sw.request('/api/lessons/1/messages');
    const restarted = await worker('release-1', sw.storage);
    restarted.offline();
    assert.match(await (await restarted.request('/api/lessons/1')).text(), /My lesson notes/);
    assert.equal((await restarted.request('/api/auth/me')).status, 200);
    assert.equal((await restarted.request('/api/lessons/1/messages')).status, 200);
});

test('logout/account change purges private data and rejects old in-flight responses', async () => {
    const sw = await worker();
    await sw.session('alice');
    await sw.request('/api/lessons/1');
    let resolve;
    sw.network(() => new Promise((done) => { resolve = done; }));
    const pending = sw.request('/api/lessons/2');
    while (!resolve) await new Promise((done) => setTimeout(done, 0));
    await sw.session(null);
    await sw.session('bob');
    resolve(new Response('{"note":"Alice private note"}', { headers: { 'Content-Type': 'application/json' } }));
    await pending;
    sw.offline();
    await assert.rejects(sw.request('/api/lessons/1', 'bob'));
    await assert.rejects(sw.request('/api/lessons/2', 'bob'));
    await assert.rejects(sw.request('/api/lessons/1', 'alice'));
});

test('server denial is authoritative and removes offline access rather than serving stale notes', async () => {
    const sw = await worker();
    await sw.session('alice');
    await sw.request('/api/lessons/1');
    sw.network(async () => new Response('Forbidden', { status: 403 }));
    assert.equal((await sw.request('/api/lessons/1')).status, 403);
    sw.offline();
    await assert.rejects(sw.request('/api/lessons/1'));
});

test('only the ten most recently read lessons remain offline', async () => {
    const sw = await worker();
    await sw.session('alice');
    for (let id = 1; id <= 11; id++) {
        await sw.request(`/api/lessons/${id}`);
        await sw.request(`/api/lessons/${id}/messages`);
    }
    sw.offline();
    await assert.rejects(sw.request('/api/lessons/1'));
    await assert.rejects(sw.request('/api/lessons/1/messages'));
    assert.equal((await sw.request('/api/lessons/2')).status, 200);
    assert.equal((await sw.request('/api/lessons/11')).status, 200);
});

test('release activation removes old assets/private snapshots and offline navigation uses matching shell', async () => {
    const old = await worker('release-1');
    await old.event('install');
    await old.session('alice');
    await old.request('/api/lessons/1');
    const next = await worker('release-2', old.storage);
    await next.event('install');
    await next.event('activate');
    assert.ok(![...next.storage.keys()].some((name) => name.includes('release-1')));
    next.offline();
    const response = await next.event('fetch', { request: { url: origin + '/lessons/1', method: 'GET', mode: 'navigate' } });
    assert.equal(await response.text(), 'asset');
    await assert.rejects(next.request('/api/lessons/1'));
});

test('sending notes preserves lesson detail, refreshes messages, and never replays offline writes', async () => {
    const sw = await worker();
    await sw.session('alice');
    await sw.request('/api/lessons/1');
    await sw.request('/api/lessons/1/messages');
    await sw.request('/api/lessons/1/messages', 'alice', 'POST');
    sw.offline();
    assert.equal((await sw.request('/api/lessons/1')).status, 200);
    await assert.rejects(sw.request('/api/lessons/1/messages'));
    await assert.rejects(sw.request('/api/lessons/1/messages', 'alice', 'POST'));
});

test('storage quota errors never turn a fresh network response into stale data', async () => {
    const sw = await worker();
    await sw.session('alice');
    await sw.request('/api/lessons/1');
    for (const [name, cache] of sw.storage) {
        if (name.includes('private-')) cache.put = async () => { throw new Error('QuotaExceededError'); };
    }
    sw.network(async () => new Response('{"note":"Fresh edited notes"}', { headers: { 'Content-Type': 'application/json' } }));
    assert.match(await (await sw.request('/api/lessons/1')).text(), /Fresh edited notes/);
});

test('online navigation while an update waits keeps the active release shell', async () => {
    const sw = await worker();
    await sw.event('install');
    sw.network(async () => new Response('new release HTML'));
    const response = await sw.event('fetch', { request: { url: origin + '/lessons/1', method: 'GET', mode: 'navigate' } });
    assert.equal(await response.text(), 'asset');
});
