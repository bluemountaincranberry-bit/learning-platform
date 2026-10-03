import assert from 'node:assert/strict';
import { chromium } from 'playwright';

const baseUrl = process.env.E2E_BASE_URL || 'http://web';
const viteOrigin = process.env.E2E_VITE_ORIGIN || 'http://node:5177';
const browser = await chromium.launch({ headless: true, args: ['--no-sandbox', '--disable-dev-shm-usage'] });
const context = await browser.newContext({ baseURL: baseUrl, viewport: { width: 360, height: 800 } });
const page = await context.newPage();
await page.route('**/*', async (route) => {
  const url = new URL(route.request().url());
  if (url.port === '5177') {
    await route.fulfill({ response: await route.fetch({ url: `${viteOrigin}${url.pathname}${url.search}` }) });
  } else {
    await route.continue();
  }
});

try {
  await page.goto('/login');
  await page.getByLabel('Email').fill(process.env.E2E_EMAIL || 'admin@example.com');
  await page.getByLabel('Password').fill(process.env.E2E_PASSWORD || 'password');
  await page.locator('form').getByRole('button', { name: 'Login', exact: true }).click();
  await page.waitForURL('**/catalog');
  await page.getByRole('button', { name: 'All catalog', exact: true }).click();
  const card = page.locator('[role="link"][aria-label^="Open "]').first();
  await card.waitFor({ state: 'visible' });
  await card.click();
  await page.waitForURL('**/catalog/*');
  const contentPath = new URL(page.url()).pathname;
  const failures = [];
  const longTitle = `A very long title with several words ${'LongUnbrokenTitle'.repeat(12)}`;
  async function checkPageWidth(path, width, withLongTitle = false) {
    await page.goto(path);
    await page.waitForLoadState('networkidle');
    if (path === '/catalog') {
      await page.getByRole('button', { name: 'All catalog', exact: true }).click();
      await page.locator('[role="link"][aria-label^="Open "]').first().waitFor({ state: 'visible' });
      await page.waitForLoadState('networkidle');
    }
    assert.equal(new URL(page.url()).pathname, path, 'page must not redirect to login');
    if (withLongTitle) {
      const target = path === '/dashboard'
        ? page.locator('section').filter({ has: page.getByRole('heading', { name: 'Recommended content', exact: true }) })
        : page;
      assert.ok(await target.getByText(longTitle, { exact: true }).count(), `${path} must render the long-title fixture`);
    }
    const size = await page.evaluate(() => ({ viewport: innerWidth, document: document.documentElement.scrollWidth }));
    const label = `${withLongTitle ? 'long titles ' : ''}${path} ${width}px`;
    console.log(`${size.document <= size.viewport ? 'PASS' : 'FAIL'} ${label}: document=${size.document}`);
    if (size.document > size.viewport) failures.push(`${label} (${size.document}px)`);
  }
  for (const width of [360, 390]) {
    await page.setViewportSize({ width, height: 800 });
    for (const path of ['/dashboard', '/grammar', '/catalog', contentPath, '/my-words', '/my-grammar', '/lessons', '/repetitions', '/my-progress']) {
      await checkPageWidth(path, width);
    }
  }
  // Change only API response text in the browser; never edit the learner's data.
  await page.route('**/api/**', async (route) => {
    const path = new URL(route.request().url()).pathname;
    if (route.request().method() !== 'GET') return route.continue();
    if (path === '/api/lessons') {
      return route.fulfill({ json: { data: [{ id: 1, title: longTitle, updated_at: '2026-10-03T10:00:00Z', tutor: null, status: 'active', lexeme_count: 12, grammar_count: 3 }] } });
    }
    if (path === '/api/ai/recommended/contents') {
      return route.fulfill({ json: { data: [{ id: 1, title: longTitle, type: 'youtube', language: 'en', level: 'B1', total_lexemes: 20, learned_count: 3, in_learning_count: 5 }] } });
    }
    const response = await route.fetch();
    if (!response.ok() || !response.headers()['content-type']?.includes('application/json')) {
      return route.fulfill({ response });
    }
    const body = await response.json();
    function replaceTitles(value) {
      if (!value || typeof value !== 'object') return;
      for (const [key, child] of Object.entries(value)) {
        if (key === 'title' && typeof child === 'string') value[key] = longTitle;
        else replaceTitles(child);
      }
    }
    replaceTitles(body);
    await route.fulfill({ response, json: body });
  });
  for (const width of [360, 390]) {
    await page.setViewportSize({ width, height: 800 });
    for (const path of ['/dashboard', '/grammar', '/catalog', contentPath, '/lessons']) {
      await checkPageWidth(path, width, true);
    }
  }
  assert.deepEqual(failures, [], 'learner pages must fit the mobile viewport');
} finally {
  await context.close();
  await browser.close();
}
