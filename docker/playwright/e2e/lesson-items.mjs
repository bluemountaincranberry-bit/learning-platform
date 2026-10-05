import assert from 'node:assert/strict';
import { chromium } from 'playwright';

const baseUrl = process.env.E2E_BASE_URL || 'http://web';
const browser = await chromium.launch({ headless: true, args: ['--no-sandbox', '--disable-dev-shm-usage'] });
const context = await browser.newContext({ baseURL: baseUrl, viewport: { width: 360, height: 800 } });
const user = {
  id: 1, name: 'Test learner', email: 'learner@example.test', timezone: 'UTC', ui_language: 'en',
  translation_language: 'ru', current_level: 'B1', learning_goal: 'general', ai_extraction_thoroughness: null,
  daily_goal: 10, email_verified_at: null, created_at: '2026-10-05T00:00:00Z', updated_at: '2026-10-05T00:00:00Z',
};
await context.addInitScript((serializedUser) => {
  localStorage.setItem('auth_token', 'mobile-test-token');
  localStorage.setItem('auth_user', serializedUser);
  localStorage.setItem('auth_roles', '[]');
}, JSON.stringify(user));
const page = await context.newPage();
const originalLesson = {
  id: 123, title: 'French lesson', lesson_date: '2026-10-05', teacher: 'Marie', topic: 'Everyday expressions',
  language: 'fr', tags: [], notes: '', homework: '', status: 'active', conversation_id: null, analysis_status: 'completed',
  lexemes: [{ id: 11, text: 'prendre soin de', type: 'phrase', level: 'B1', translation: 'заботиться о', example: 'Je prends soin de ma sœur.', example_translation: null, status: 'new', matched_lexeme_id: null, source: 'ai', language: 'fr' }],
  grammar: [{ id: 21, title: 'Passé composé', summary: 'Use it for a completed past action.', example: 'J’ai fini.', example_translation: 'Я закончил.', status: 'new', matched_grammar_rule_id: null, source: 'ai' }],
  corrections: [{ id: 31, original_text: 'Je suis allé hier.', corrected_text: 'Je suis allé hier soir.', explanation: 'Add a time detail.', source: 'ai' }],
};

await page.route('**/sanctum/csrf-cookie', (route) => route.fulfill({ status: 204 }));
await page.route('**/api/**', async (route) => {
  const url = new URL(route.request().url());
  const path = url.pathname;
  const method = route.request().method();
  if (path === '/api/auth/me') return route.fulfill({ json: { user, roles: [] } });
  if (path === '/api/profile') return route.fulfill({ json: { user, today_learned_count: 0, streak_days: 1 } });
  if (path === '/api/lessons/123/messages') return route.fulfill({ json: { messages: [], is_waiting: false } });
  if (path === '/api/lessons/123' && method === 'GET') return route.fulfill({ json: structuredClone(originalLesson) });
  if (method === 'POST' && path === '/api/lessons/123/lexemes') return route.fulfill({ status: 201, json: { ...JSON.parse(route.request().postData() || '{}'), id: 41, status: 'new', matched_lexeme_id: null, source: 'manual', language: 'fr' } });
  if (method === 'POST' && path === '/api/lessons/123/grammar') return route.fulfill({ status: 201, json: { ...JSON.parse(route.request().postData() || '{}'), id: 42, status: 'new', matched_grammar_rule_id: null, source: 'manual' } });
  if (method === 'POST' && path === '/api/lessons/123/corrections') return route.fulfill({ status: 201, json: { ...JSON.parse(route.request().postData() || '{}'), id: 43, source: 'manual' } });
  if (method === 'DELETE') return route.fulfill({ status: 204 });
  if (method === 'PUT') return route.fulfill({ json: JSON.parse(route.request().postData() || '{}') });
  if (method === 'POST' && path.endsWith('/restore')) return route.fulfill({ json: {} });
  return route.fulfill({ json: {} });
});

const failures = [];
const consoleErrors = [];
page.on('pageerror', (error) => consoleErrors.push(error.message));

try {
  await page.goto('/lessons/123');
  await page.waitForLoadState('networkidle');
  assert.equal(new URL(page.url()).pathname, '/lessons/123', 'lesson should remain open when APIs are stubbed');

  async function assertFits(width, action) {
    await page.setViewportSize({ width, height: 800 });
    if (action) await action();
    const size = await page.evaluate(() => ({ viewport: innerWidth, document: document.documentElement.scrollWidth }));
    const result = `lesson items ${width}px: document=${size.document}`;
    console.log(`${size.document <= size.viewport ? 'PASS' : 'FAIL'} ${result}`);
    if (size.document > size.viewport) failures.push(result);
  }

  for (const width of [360, 390]) {
    await assertFits(width, async () => {
      await page.getByRole('tab', { name: 'Words' }).click();
      await page.getByRole('button', { name: 'Add word' }).click();
      await page.getByPlaceholder('e.g. look after').fill('parler de');
      await page.getByRole('button', { name: 'Save word' }).click();
      await page.getByRole('tab', { name: 'Grammar' }).click();
      await page.getByRole('button', { name: 'Add grammar' }).click();
      await page.getByPlaceholder('e.g. Past habits with used to').fill('Conditionnel');
      await page.getByRole('button', { name: 'Save grammar' }).click();
      await page.getByRole('tab', { name: 'Corrections' }).click();
      await page.getByRole('button', { name: 'Add correction' }).click();
      await page.getByPlaceholder('Original wording').fill('Je vais allé.');
      await page.getByPlaceholder('Corrected wording').fill('Je vais aller.');
      await page.getByRole('button', { name: 'Save correction' }).click();
    });
  }

  assert.deepEqual(failures, [], 'lesson forms and shared item rows fit 360px and 390px');
  assert.deepEqual(consoleErrors, [], 'lesson page has no uncaught browser errors');
} finally {
  await context.close();
  await browser.close();
}
