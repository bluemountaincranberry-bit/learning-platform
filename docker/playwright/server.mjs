import http from 'node:http';
import fs from 'node:fs';
import { URL } from 'node:url';
import { chromium } from 'playwright';
import { fetchTranscript as fetchNodeTranscript } from 'youtube-transcript';

const port = Number(process.env.PORT || 3000);
const storageStatePath = process.env.YOUTUBE_STORAGE_STATE_PATH || '';
const browserPromise = chromium.launch({
  headless: true,
  args: ['--no-sandbox', '--disable-dev-shm-usage'],
});

function json(response, status, body) {
  response.writeHead(status, { 'content-type': 'application/json' });
  response.end(JSON.stringify(body));
}

function readBody(request) {
  return new Promise((resolve, reject) => {
    let body = '';
    request.on('data', (chunk) => {
      body += chunk;
      if (body.length > 100_000) reject(new Error('Request body is too large.'));
    });
    request.on('end', () => resolve(body));
    request.on('error', reject);
  });
}

function extractPlayerResponse(html) {
  const markerPosition = html.indexOf('ytInitialPlayerResponse');
  if (markerPosition < 0) return null;

  const start = html.indexOf('{', markerPosition);
  if (start < 0) return null;

  let depth = 0;
  let inString = false;
  let escaped = false;

  for (let index = start; index < html.length; index += 1) {
    const character = html[index];
    if (inString) {
      if (escaped) escaped = false;
      else if (character === '\\') escaped = true;
      else if (character === '"') inString = false;
      continue;
    }

    if (character === '"') inString = true;
    else if (character === '{') depth += 1;
    else if (character === '}' && --depth === 0) {
      try {
        return JSON.parse(html.slice(start, index + 1));
      } catch {
        return null;
      }
    }
  }

  return null;
}

function selectTrack(tracks, language) {
  if (!language) return tracks[0] || null;
  const requested = language.toLowerCase();
  return tracks.find((track) => track.languageCode?.toLowerCase() === requested)
    || tracks.find((track) => track.languageCode?.toLowerCase().startsWith(`${requested}-`))
    || null;
}

function parseJson3(body) {
  let payload;
  try {
    payload = JSON.parse(body);
  } catch {
    return null;
  }

  if (!Array.isArray(payload.events)) return null;
  const segments = payload.events.flatMap((event, index) => {
    if (!Array.isArray(event.segs)) return [];
    const text = event.segs.map((segment) => segment.utf8 || '').join('').trim();
    if (!text) return [];
    const startMs = Number(event.tStartMs || 0);
    return [{
      startMs,
      endMs: startMs + Number(event.dDurationMs || 0),
      text,
      sourceKey: String(index),
    }];
  });

  return segments.length > 0 ? segments : null;
}

async function fetchCaption(page, track) {
  const url = `${track.baseUrl}${track.baseUrl.includes('?') ? '&' : '?'}fmt=json3`;
  return page.evaluate(async (captionUrl) => {
    const response = await fetch(captionUrl, { credentials: 'include' });
    return { status: response.status, body: await response.text() };
  }, url);
}

async function extractTranscript({ url, language }) {
  const browser = await browserPromise;
  const context = await browser.newContext({
    locale: language || 'en',
    userAgent: 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/131 Safari/537.36',
    ...(storageStatePath && fs.existsSync(storageStatePath) ? { storageState: storageStatePath } : {}),
  });
  const page = await context.newPage();
  const captured = [];

  page.on('response', async (response) => {
    if (!response.url().includes('/api/timedtext')) return;
    try {
      const body = await response.text();
      if (body.trim() !== '') captured.push({ url: response.url(), body });
    } catch {
      // The response may be disposed before its body is readable.
    }
  });

  try {
    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30_000 });
    await page.waitForTimeout(2_000);

    const playerResponse = extractPlayerResponse(await page.content());
    const tracks = playerResponse?.captions?.playerCaptionsTracklistRenderer?.captionTracks || [];
    const track = selectTrack(tracks, language);

    // Start the real player so YouTube can attach its browser-generated
    // attestation data to caption requests where it is required.
    const playButton = page.locator('.ytp-play-button').first();
    if (await playButton.isVisible().catch(() => false)) {
      await playButton.click().catch(() => {});
    }
    await page.evaluate(() => document.querySelector('video')?.play().catch(() => {}));
    const captionsButton = page.locator('.ytp-subtitles-button').first();
    if (await captionsButton.isVisible().catch(() => false)) {
      await captionsButton.click().catch(() => {});
    }
    await page.waitForTimeout(5_000);

    let segments = null;
    let selectedLanguage = track?.languageCode || language || null;
    const capturedTrack = captured.find((item) => !language || new URL(item.url).searchParams.get('lang')?.startsWith(language));
    if (capturedTrack) segments = parseJson3(capturedTrack.body);

    if (!segments && track?.baseUrl) {
      const result = await fetchCaption(page, track);
      if (result.status >= 200 && result.status < 300) segments = parseJson3(result.body);
    }

    if (!segments) {
      throw new Error(`Browser did not receive usable YouTube captions (tracks=${tracks.length}, captured=${captured.length}).`);
    }

    return {
      language: selectedLanguage,
      source: 'youtube-playwright',
      segments,
    };
  } finally {
    await context.close();
  }
}

async function extractNodeTranscript({ url, language }) {
  const items = await fetchNodeTranscript(url, language ? { lang: language } : undefined);
  const segments = items
    .filter((item) => typeof item.text === 'string' && item.text.trim() !== '')
    .map((item, index) => ({
      startMs: Math.round(Number(item.offset || 0) * 1000),
      endMs: Math.round((Number(item.offset || 0) + Number(item.duration || 0)) * 1000),
      text: item.text.trim(),
      sourceKey: String(index),
    }));

  if (segments.length === 0) throw new Error('Node youtube-transcript returned no captions.');
  return { language: language || null, source: 'youtube-transcript-node', segments };
}

const server = http.createServer(async (request, response) => {
  if (request.method === 'GET' && new URL(request.url, `http://${request.headers.host}`).pathname === '/health') {
    return json(response, 200, { status: 'ok', storageStateLoaded: Boolean(storageStatePath && fs.existsSync(storageStatePath)) });
  }

  if (request.method !== 'POST' || new URL(request.url, `http://${request.headers.host}`).pathname !== '/transcript') {
    if (request.method === 'POST' && new URL(request.url, `http://${request.headers.host}`).pathname === '/transcript-node') {
      try {
        const payload = JSON.parse(await readBody(request));
        return json(response, 200, await extractNodeTranscript({ url: payload.url, language: payload.language || null }));
      } catch (error) {
        return json(response, 502, { error: error instanceof Error ? error.message : 'Node transcript extraction failed.' });
      }
    }
    return json(response, 404, { error: 'Not found' });
  }

  try {
    const payload = JSON.parse(await readBody(request));
    if (typeof payload.url !== 'string' || payload.url.trim() === '') {
      return json(response, 422, { error: 'url is required' });
    }

    return json(response, 200, await extractTranscript({ url: payload.url, language: payload.language || null }));
  } catch (error) {
    return json(response, 502, { error: error instanceof Error ? error.message : 'Browser transcript extraction failed.' });
  }
});

server.listen(port, '0.0.0.0', () => {
  console.log(`Playwright transcript worker listening on ${port}`);
});
