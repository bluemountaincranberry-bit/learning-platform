// A real production SPA, SW, CacheStorage and network disconnect; fixture API, no DB writes.
import assert from 'node:assert/strict';
import http from 'node:http';
import { readFile, stat } from 'node:fs/promises';
import { resolve, extname } from 'node:path';
import { chromium, webkit } from 'playwright';

const publicRoot = resolve(process.env.PWA_PUBLIC_ROOT || '/workspace/src/public');
const artifacts = process.env.PWA_ARTIFACT_DIR;
let note = 'Offline lesson note from Alice';
let nextNote = note;
let release = 1;
let disconnected = false;
const user = { id: 101, name: 'Offline learner', email: 'offline@example.test', daily_goal: 10 };
const mime = { '.js': 'text/javascript', '.css': 'text/css', '.html': 'text/html', '.png': 'image/png', '.webmanifest': 'application/manifest+json' };
const builtShell = await readFile(resolve(publicRoot, 'build/offline.html'), 'utf8');
const mainPath = builtShell.match(/src="([^"]+\.js)"/)[1];
const server = http.createServer(async (request, response) => {
    if (disconnected) return request.socket.destroy();
    const path = new URL(request.url, 'http://localhost').pathname;
    response.setHeader('Cache-Control', 'no-store');
    if (path.startsWith('/api/')) {
        response.setHeader('Content-Type', 'application/json');
        if (path === '/api/lessons/1/messages' && request.method === 'POST') note = nextNote;
        const data = path === '/api/auth/me' ? { user, roles: ['student'] }
            : path === '/api/profile' ? { user, today_learned_count: 0, streak_days: 0 }
            : path === '/api/lessons/1' ? { id: 1, title: 'Offline group lesson', tutor: 'Teacher', status: 'active', lexemes: [{ id: 1, text: 'remember', translation: 'recall', status: 'new' }], grammar: [], analysis_status: null }
            : path === '/api/lessons/1/messages' ? { messages: [{ id: 1, role: 'user', content: note, attachment_name: null }], is_waiting: false }
            : path === '/api/lessons' ? { data: [{ id: 1, title: 'Offline group lesson', tutor: 'Teacher', status: 'active', lexeme_count: 1, grammar_count: 0, updated_at: '2026-10-04T10:00:00Z' }], meta: { current_page: 1, per_page: 15, total: 1, last_page: 1 } }
            : {};
        return response.end(JSON.stringify(data));
    }
    const releaseMain = `/build/assets/fixture-main-release-${release}.js`;
    let file = resolve(publicRoot, '.' + (path === releaseMain ? mainPath : path));
    if (!file.startsWith(publicRoot + '/')) { response.writeHead(404); return response.end(); }
    try { if (!(await stat(file)).isFile()) throw new Error(); }
    catch { file = resolve(publicRoot, 'build/offline.html'); }
    response.setHeader('Content-Type', mime[extname(file)] || 'application/octet-stream');
    let body = await readFile(file);
    if (extname(file) === '.html') body = Buffer.from(body.toString().replace('<head>', `<head><meta name="fixture-release" content="${release}">`).replace(mainPath, release > 1 ? releaseMain : mainPath));
    if (path === '/sw.js' && release > 1) body = Buffer.from(body.toString().replace(/const VERSION = "[a-f0-9]+";/, `const VERSION = "browser-release-${release}";`).replaceAll(mainPath, releaseMain));
    response.end(body);
});
await new Promise((done) => server.listen(0, '127.0.0.1', done));
const baseURL = `http://localhost:${server.address().port}`;
try {
    for (const [engine, name] of [[chromium, 'chromium'], [webkit, 'webkit']]) {
        disconnected = false;
        note = 'Offline lesson note from Alice';
        const browser = await engine.launch({ headless: true, args: name === 'chromium' ? ['--no-sandbox', '--disable-dev-shm-usage'] : [] });
        const context = await browser.newContext({ baseURL, viewport: { width: 360, height: 800 }, serviceWorkers: 'allow' });
        context.on('page', (current) => current.on('pageerror', (error) => console.error(`${name} runtime: ${error.message}`)));
        const setOffline = async (value) => {
            // Browser emulation alone may leave worker fetches online; sever transport.
            // WebKit's offline emulation also aborts SW navigations internally.
            disconnected = value;
            if (name === 'chromium') await context.setOffline(value);
        };
        let page = await context.newPage();
        page.setDefaultTimeout(20000);
        page.on('pageerror', (error) => console.error(`${name} page error: ${error.message}`));
        await page.goto('/login');
        await page.locator('input[type=email]').waitFor();
        // Persist synthetic credentials once; do not reinsert them on every reload.
        await page.evaluate(() => { localStorage.setItem('auth_token', 'alice'); localStorage.setItem('auth_user', JSON.stringify({ id: 101, name: 'Offline learner', email: 'offline@example.test' })); });
        await page.goto('/lessons');
        await page.getByText('Offline group lesson', { exact: true }).waitFor();
        await page.goto('/lessons/1');
        await page.getByText(note, { exact: true }).waitFor();
        assert.ok(await page.evaluate(() => !!navigator.serviceWorker.controller));
        const manifest = await (await context.request.get('/manifest.webmanifest')).json();
        assert.equal(manifest.display, 'standalone');
        assert.equal(manifest.start_url, '/lessons');
        for (const icon of manifest.icons) assert.equal((await context.request.get(icon.src)).status(), 200);
        for (const width of [360, 390]) {
            await page.setViewportSize({ width, height: 800 });
            await setOffline(true);
            await page.reload();
            await page.getByText(note, { exact: true }).waitFor();
            await page.getByText('remember', { exact: true }).waitFor();
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
            if (artifacts) await page.screenshot({ path: `${artifacts}/${name}-offline-${width}.png`, fullPage: true });
            // Cold tab restart must restore auth and lazy lesson JS from SW CacheStorage.
            await page.close();
            page = await context.newPage();
            await page.goto('/lessons/1');
            await page.getByText(note, { exact: true }).waitFor();
            await setOffline(false);
        }
        console.log(`PASS ${name}: manifest/icons, 360/390px offline reload and cold tab, lesson/messages/words, no horizontal scroll`);
        await setOffline(true);
        await page.evaluate(() => { localStorage.setItem('auth_token', 'bob'); localStorage.setItem('auth_user', JSON.stringify({ id: 202, name: 'Other learner' })); });
        await page.reload();
        await page.waitForURL(/\/login(?:\?|$)/, { waitUntil: 'commit' });
        assert.equal(await page.getByText(note, { exact: true }).count(), 0);
        console.log(`PASS ${name}: account change cannot reuse Alice's cached auth or lesson`);
        await setOffline(false);
        await page.evaluate(() => { localStorage.setItem('auth_token', 'alice'); localStorage.setItem('auth_user', JSON.stringify({ id: 101, name: 'Offline learner' })); });
        await page.goto('/lessons/1');
        await page.getByText(note, { exact: true }).waitFor();
        nextNote = `New note read offline ${name}`;
        await page.getByPlaceholder('What did you learn today?').fill(nextNote);
        await page.getByRole('button', { name: 'Send', exact: true }).click();
        await page.getByText(nextNote, { exact: true }).waitFor();
        await setOffline(true);
        await page.reload();
        await page.getByText(nextNote, { exact: true }).waitFor();
        await setOffline(false);
        console.log(`PASS ${name}: sending a note preserves offline lesson detail and refreshed messages`);
        // Update policy: keep the old release while an existing tab is open.
        const previousRelease = release;
        release++;
        await page.evaluate(async () => { await (await navigator.serviceWorker.getRegistration()).update(); });
        await page.waitForFunction(async () => !!(await navigator.serviceWorker.getRegistration()).waiting);
        await page.reload();
        await page.getByText(note, { exact: true }).waitFor();
        assert.equal(await page.locator('meta[name=fixture-release]').getAttribute('content'), String(previousRelease));
        assert.ok(!(await page.locator('script[type=module]').getAttribute('src')).includes(`release-${release}`));
        const previousCaches = await page.evaluate(() => caches.keys());
        await page.close();
        // Allow the browser to release the last controlled client before opening another.
        await new Promise((done) => setTimeout(done, 500));
        page = await context.newPage();
        await page.goto('/lessons/1');
        await page.getByText(note, { exact: true }).waitFor();
        for (let attempt = 0; attempt < 100; attempt++) {
            const oldAssetsRemain = await page.evaluate(async (release) => (await caches.keys()).some((name) => name.startsWith('learning-pwa-assets-') && !name.endsWith(`browser-release-${release}`)), release);
            if (!oldAssetsRemain) break;
            await new Promise((done) => setTimeout(done, 50));
        }
        // Worker reset is tested through observable offline behavior and old cache removal below.
        assert.equal(await page.locator('meta[name=fixture-release]').getAttribute('content'), String(release));
        assert.match(await page.locator('script[type=module]').getAttribute('src'), new RegExp(`release-${release}`));
        const names = await page.evaluate(() => caches.keys());
        assert.ok(names.some((name) => name.endsWith(`browser-release-${release}`)));
        for (const name of previousCaches.filter((name) => name.startsWith('learning-pwa-assets-') && !name.endsWith(`browser-release-${release}`))) assert.ok(!names.includes(name), JSON.stringify({ previousCaches, names, release }));
        console.log(`PASS ${name}: new worker waits for tabs, activates after close, deletes old release caches`);
        // Logout invokes the real Pinia action through the existing shell button.
        await page.getByRole('button', { name: /logout|log out|sign out/i }).first().click();
        await page.waitForURL(/\/login(?:\?|$)/, { waitUntil: 'commit' });
        await setOffline(true);
        await page.goto('/lessons/1');
        await page.waitForURL(/\/login(?:\?|$)/, { waitUntil: 'commit' });
        assert.equal(await page.getByText(note, { exact: true }).count(), 0);
        console.log(`PASS ${name}: offline logout cannot reopen private lesson`);
        await browser.close();
    }
} finally { server.close(); }
