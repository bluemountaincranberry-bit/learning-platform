// Exercises the real SPA/router with API failures and auth fixtures. No DB writes.
import assert from 'node:assert/strict';
import { chromium } from 'playwright';

const baseURL = process.env.E2E_VITE_ORIGIN || 'http://vik19-vite:5199';
const browser = await chromium.launch({ headless: true, args: ['--no-sandbox', '--disable-dev-shm-usage'] });
const user = { id: 1, name: 'Navigation tester', email: 'navigation@example.test', daily_goal: 10 };
const context = await browser.newContext({ baseURL, viewport: { width: 360, height: 800 } });
await context.addInitScript(() => localStorage.setItem('auth_token', 'navigation-fixture'));
await context.route('**/*', async (route) => {
    const request = route.request();
    const path = new URL(request.url()).pathname;
    if (request.isNavigationRequest()) {
        return route.fulfill({ contentType: 'text/html', body: '<div id="app"></div><script type="module" src="/resources/js/spa/main.ts"></script><link rel="stylesheet" href="/resources/css/app.css">' });
    }
    if (path === '/api/auth/me') return route.fulfill({ json: { user, roles: ['student'] } });
    if (path === '/api/profile') return route.fulfill({ json: { user, today_learned_count: 0, streak_days: 0 } });
    // The navigation must still work when page data is unavailable.
    if (path.startsWith('/api/')) return route.fulfill({ status: 503, json: { message: 'Navigation fixture: service unavailable' } });
    return route.continue();
});
const page = await context.newPage();
page.setDefaultTimeout(10000);
const artifacts = process.env.E2E_ARTIFACT_DIR;
async function assertCurrent(item, value) {
    await item.and(page.locator(`[aria-current="${value}"]`)).waitFor();
    assert.equal(await item.getAttribute('aria-current'), value);
}
try {
    const primary = [['Today', '/dashboard'], ['Lessons', '/lessons'], ['Words', '/my-words'], ['Practice', '/repetitions']];
    const secondary = [['Catalog', '/catalog'], ['Grammar', '/grammar'], ['My grammar', '/my-grammar'], ['Progress', '/my-progress'], ['AI chat', '/chat'], ['Settings', '/settings']];
    for (const width of [360, 390]) {
        await page.setViewportSize({ width, height: 800 });
        await page.goto('/dashboard');
        await page.getByRole('navigation', { name: 'Mobile navigation' }).locator('a[href="/dashboard"][aria-current="page"]').waitFor();
        const nav = page.getByRole('navigation', { name: 'Mobile navigation' });
        await nav.waitFor();
        assert.deepEqual(await nav.locator('a, button').allTextContents(), ['Today', 'Lessons', 'Words', 'Practice', 'More']);
        const geometry = await nav.locator('a, button').evaluateAll((items) => items.map((item) => {
            const r = item.getBoundingClientRect();
            return { left: r.left, right: r.right, width: r.width, height: r.height };
        }));
        for (const rect of geometry) assert.ok(rect.left >= 0 && rect.right <= width && rect.height >= 44 && rect.width >= 44, JSON.stringify(rect));
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
        for (const [label, path] of primary) {
            await page.goto('/dashboard');
        await page.getByRole('navigation', { name: 'Mobile navigation' }).locator('a[href="/dashboard"][aria-current="page"]').waitFor();
            await nav.getByRole('link', { name: label, exact: true }).click();
            await page.waitForURL(`**${path}`);
            if (label !== 'Practice') await assertCurrent(nav.getByRole('link', { name: label, exact: true }), 'page');
            // Focused training already has its own full-screen layout.
            else { await nav.waitFor({ state: 'hidden' }); assert.equal(await nav.count(), 0); }
        }
        for (const [label, path] of secondary) {
            await page.goto('/dashboard');
        await page.getByRole('navigation', { name: 'Mobile navigation' }).locator('a[href="/dashboard"][aria-current="page"]').waitFor();
            await nav.getByRole('button', { name: 'More', exact: true }).click();
            const dialog = page.getByRole('dialog', { name: 'More', exact: true });
            await dialog.getByRole('link', { name: label, exact: true }).click();
            await page.waitForURL(`**${path}`);
            await dialog.waitFor({ state: 'hidden' });
            await assertCurrent(nav.getByRole('button', { name: 'More', exact: true }), 'true');
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
            await nav.getByRole('button', { name: 'More', exact: true }).click();
            await assertCurrent(dialog.getByRole('link', { name: label, exact: true }), 'page');
            if (artifacts && label === 'Catalog') await page.screenshot({ path: `${artifacts}/more-${width}.png` });
            await page.keyboard.press('Escape');
            await dialog.waitFor({ state: 'hidden' });
            assert.ok(await nav.getByRole('button', { name: 'More', exact: true }).evaluate((el) => el === document.activeElement));
        }
        for (const [path, label] of [['/lessons/123', 'Lessons'], ['/word/123', 'Words'], ['/catalog/123', 'More'], ['/catalog/123/study', 'More'], ['/grammar/123', 'More'], ['/categories', 'More'], ['/add-youtube', 'More']]) {
            await page.goto(path);
            const item = nav.getByRole(label === 'More' ? 'button' : 'link', { name: label, exact: true });
            await item.waitFor();
            await assertCurrent(item, label === 'More' ? 'true' : 'page');
        }
        await page.goto('/dashboard');
        await page.getByRole('navigation', { name: 'Mobile navigation' }).locator('a[href="/dashboard"][aria-current="page"]').waitFor();
        if (artifacts) await page.screenshot({ path: `${artifacts}/nav-${width}.png` });
        await nav.getByRole('button', { name: 'More', exact: true }).click();
        const dialog = page.getByRole('dialog', { name: 'More', exact: true });
        await dialog.getByRole('button', { name: 'Close dialog' }).click();
        await dialog.waitFor({ state: 'hidden' });
        await nav.getByRole('button', { name: 'More', exact: true }).click();
        await dialog.waitFor();
        // Initial Shift+Tab must stay in the dialog too.
        await page.waitForFunction(() => document.activeElement?.getAttribute('href') === '/catalog');
        await page.keyboard.press('Shift+Tab');
        assert.ok(await dialog.getByRole('button', { name: 'Close dialog' }).evaluate((el) => el === document.activeElement));
        // Tab wraps from the last link to the close button and back.
        await dialog.getByRole('link', { name: 'Settings', exact: true }).focus();
        await page.keyboard.press('Tab');
        assert.ok(await dialog.getByRole('button', { name: 'Close dialog' }).evaluate((el) => el === document.activeElement));
        await page.keyboard.press('Shift+Tab');
        assert.ok(await dialog.getByRole('link', { name: 'Settings', exact: true }).evaluate((el) => el === document.activeElement));
        await page.mouse.click(2, 2);
        await dialog.waitFor({ state: 'hidden' });
        console.log(`PASS ${width}px: five visible targets, ten destinations in 1–2 taps, active detail pages, dialog dismissal/focus, no horizontal scroll (API unavailable)`);
    }
    await context.route('**/api/auth/me', (route) => route.fulfill({ json: { user, roles: ['admin'] } }));
    await page.goto('/dashboard');
        await page.getByRole('navigation', { name: 'Mobile navigation' }).locator('a[href="/dashboard"][aria-current="page"]').waitFor();
    await page.getByRole('navigation', { name: 'Mobile navigation' }).getByRole('button', { name: 'More' }).click();
    assert.equal(await page.getByRole('dialog').getByRole('link', { name: 'AI chat' }).count(), 0);
    await page.keyboard.press('Escape');
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.goto('/dashboard');
    await page.locator('aside a[href="/dashboard"][aria-current="page"]').waitFor();
    const sidebar = page.locator('aside');
    await sidebar.waitFor();
    assert.deepEqual(await sidebar.locator('nav a').allTextContents().then((labels) => labels.map((label) => label.replace('live', '').trim())), ['Dashboard', 'Catalog', 'Grammar', 'Practice', 'My words', 'My grammar', 'My lessons', 'Progress', 'Settings']);
    assert.equal(await page.getByRole('navigation', { name: 'Mobile navigation' }).isVisible(), false);
    if (artifacts) await page.screenshot({ path: `${artifacts}/desktop.png` });
    console.log('PASS staff chat restriction and unchanged desktop destinations');
} finally {
    await context.close();
    await browser.close();
}
