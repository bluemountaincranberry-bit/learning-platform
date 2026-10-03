import assert from 'node:assert/strict';
import { chromium } from 'playwright';

const baseUrl = process.env.E2E_BASE_URL || 'http://web';
const email = process.env.E2E_EMAIL || 'admin@example.com';
const password = process.env.E2E_PASSWORD || 'password';

const browser = await chromium.launch({
  headless: true,
  args: ['--no-sandbox', '--disable-dev-shm-usage'],
});
const context = await browser.newContext({ baseURL: baseUrl });
const page = await context.newPage();
page.on('console', (message) => {
  if (message.type() === 'error') console.error(`browser console: ${message.text()}`);
});
page.on('pageerror', (error) => console.error(`browser page error: ${error.message}`));
page.on('requestfailed', (request) => console.error(`request failed: ${request.url()} (${request.failure()?.errorText})`));

// The local compose setup serves the Laravel shell through nginx and Vite
// assets through the node service. Rewrite the configured LAN/host Vite origin
// to the compose DNS name so this test works inside the browser container.
await page.route('**/*', async (route) => {
  const requestUrl = new URL(route.request().url());
  if (requestUrl.port === '5177') {
    const viteUrl = `http://node:5177${requestUrl.pathname}${requestUrl.search}`;
    // Chromium in the base image may block direct cross-origin requests to a
    // compose DNS hostname. Fetch through Playwright's Node side and fulfill
    // the original browser request, preserving the host-independent test.
    const response = await route.fetch({ url: viteUrl });
    await route.fulfill({ response });
    return;
  }
  await route.continue();
});

async function assertVisible(text) {
  await page.getByText(text, { exact: true }).first().waitFor({ state: 'visible' });
}

try {
  await page.goto('/catalog');
  await assertVisible('Catalog');
  const guestContentCard = page.locator('[role="link"][aria-label^="Open "]').first();
  await guestContentCard.waitFor({ state: 'visible' });

  await page.goto('/repetitions');
  await page.waitForURL('**/login?redirect=/repetitions');
  await assertVisible('Login');

  await page.getByLabel('Email').fill(email);
  await page.getByLabel('Password').fill(password);
  await page.locator('form').getByRole('button', { name: 'Login', exact: true }).click();
  await page.waitForURL('**/catalog');
  await assertVisible('Catalog');

  // Admin users start in their own empty scope; switch to the shared catalog,
  // then exercise the catalog -> detail -> study boundary.
  await page.getByRole('button', { name: 'All catalog', exact: true }).click();
  const contentCard = page.locator('[role="link"][aria-label^="Open "]').first();
  await contentCard.waitFor({ state: 'visible' });
  const cardLabel = await contentCard.getAttribute('aria-label');
  const contentTitle = cardLabel?.replace(/^Open /, '');
  assert.ok(contentTitle, 'catalog card should expose its content title');
  await contentCard.click();
  await page.waitForURL('**/catalog/*');
  const contentId = new URL(page.url()).pathname.split('/').filter(Boolean).at(-1);
  assert.ok(contentId, 'catalog card should navigate to a content detail route');
  await assertVisible(contentTitle);

  await page.goto(`/catalog/${contentId}/study`);
  await page.waitForURL(`**/catalog/${contentId}/study`);
  await assertVisible(contentTitle);

  await page.goto('/repetitions');
  await page.waitForURL('**/repetitions');
  await assertVisible('Practice words your way');

  // Check AI surfaces without sending a provider request: the admin builder
  // loads for an admin and tutor access exposes its role-aware state.
  await page.goto('/admin/ai-builder');
  await page.waitForURL('**/admin/ai-builder');
  await assertVisible('Каталог промптов');

  // Prompt editor contract: editing a template exposes the unsaved state and
  // saving clears it. The key comes from the live catalog, so this remains
  // stable as prompt inventory grows.
  const promptLink = page.getByRole('link', { name: 'Открыть редактор промпта →' }).first();
  await promptLink.waitFor({ state: 'visible' });
  await promptLink.click();
  await page.waitForURL('**/admin/ai-builder/prompts/*');
  const systemTemplate = page.locator('textarea').first();
  await systemTemplate.waitFor({ state: 'visible' });
  await systemTemplate.fill(`${await systemTemplate.inputValue()}\nTest edit`);
  await assertVisible('Unsaved changes');
  page.on('response', async (response) => {
    if (response.url().includes('/api/admin/ai-builder/prompt-templates/') && response.request().method() !== 'GET' && response.status() >= 400) {
      console.error(`prompt API ${response.status()}: ${await response.text()}`);
    }
  });
  await page.getByRole('button', { name: 'Save draft' }).click();
  await page.getByText(/^Saved as draft v/).waitFor({ state: 'visible' });
  assert.equal(await page.getByText('Unsaved changes').count(), 0, 'saved prompt should clear unsaved state');

  await page.goto('/admin/ai-builder');
  const graphLink = page.getByRole('link', { name: 'Открыть в графе →' }).first();
  if (await graphLink.count()) {
    await graphLink.click();
    await page.waitForURL('**/admin/ai-builder/graphs/*');
    const graphName = page.locator('input').first();
    await graphName.fill(`${await graphName.inputValue()} test`);
    await assertVisible('Unsaved changes');
    await page.getByRole('button', { name: 'Save draft' }).click();
    await page.getByText(/^Saved as draft v/).waitFor({ state: 'visible' });
    assert.equal(await page.getByText('Unsaved changes').count(), 0, 'saved graph should clear unsaved state');
  }

  await page.goto('/chat');
  await page.waitForURL('**/chat');
  await assertVisible('Not available for your account');

  console.log('rewrite E2E smoke: 8 flows passed');
} finally {
  await context.close();
  await browser.close();
}
