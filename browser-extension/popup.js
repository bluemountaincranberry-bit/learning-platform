const $ = (id) => document.getElementById(id);

async function load() {
  const saved = await chrome.storage.local.get(['apiBase', 'token', 'language', 'level']);
  $('apiBase').value = saved.apiBase || 'http://localhost:8088';
  $('token').value = saved.token || '';
  $('language').value = saved.language || 'en';
  $('level').value = saved.level || '';
}

$('import').addEventListener('click', async () => {
  const apiBase = $('apiBase').value.replace(/\/$/, '');
  const token = $('token').value.trim();
  const language = $('language').value.trim().toLowerCase();
  const level = $('level').value.trim() || null;
  $('status').textContent = 'Reading captions from the active YouTube tab…';
  await chrome.storage.local.set({ apiBase, token, language, level });

  try {
    const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
    if (!tab?.id || !tab.url?.match(/^https:\/\/(www\.)?youtube\.com\//)) {
      throw new Error('Open a YouTube video in the active tab first.');
    }
    if (!token) throw new Error('Add the Learning App API token first.');
    let result = await chrome.tabs.sendMessage(tab.id, { type: 'extractTranscript', language });
    if (result?.debug) console.info('[Learning App YouTube]', result.debug);
    if (result?.error || !result?.segments?.length) {
      const [mainWorldResult] = await chrome.scripting.executeScript({
        target: { tabId: tab.id },
        world: 'MAIN',
        func: extractTranscriptInMainWorld,
        args: [language],
      });
      if (mainWorldResult.result?.segments?.length) {
        result = mainWorldResult.result;
      } else {
        await ensureApiPermission(apiBase);
        const fallback = await submitUrlToServer(apiBase, token, tab.url, language, level);
        if (fallback.content) {
          $('status').textContent = `Browser captions unavailable. Server ingestion started for “${fallback.content.title}” (content #${fallback.content.id}).`;
          return;
        }
        throw new Error(`${result?.error || 'No captions found.'} Main-world debug: ${JSON.stringify(mainWorldResult.result?.debug || {})} Server fallback: ${fallback.message || 'failed'}`);
      }
    }
    if (!result?.segments?.length) throw new Error('No captions found. Open a video with subtitles enabled.');
    await ensureApiPermission(apiBase);
    const response = await fetch(`${apiBase}/api/content/import-youtube-transcript`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'Authorization': `Bearer ${token}` },
      body: JSON.stringify({ source_url: tab.url, title: result.title, language, level, segments: result.segments })
    });
    const rawBody = await response.text();
    let body = {};
    try { body = rawBody ? JSON.parse(rawBody) : {}; } catch { /* keep a useful status error below */ }
    if (!response.ok) throw new Error(body.message || `Import failed (${response.status}).`);
    if (!body.content) throw new Error('Learning App returned an invalid response.');
    $('status').textContent = `Imported “${body.content.title}” (content #${body.content.id}). Processing started.`;
  } catch (error) {
    const message = error?.message || String(error);
    $('status').textContent = message.includes('Receiving end does not exist')
      ? 'Reload the YouTube tab after reloading the extension, then try again.'
      : message;
  }
});

async function submitUrlToServer(apiBase, token, sourceUrl, language, level) {
  const response = await fetch(`${apiBase}/api/content/submit-youtube`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'Authorization': `Bearer ${token}` },
    body: JSON.stringify({ source_url: sourceUrl, language, level }),
  });
  const rawBody = await response.text();
  let body = {};
  try { body = rawBody ? JSON.parse(rawBody) : {}; } catch { /* handled by the message */ }
  return response.ok ? body : { message: body.message || `HTTP ${response.status}` };
}

async function ensureApiPermission(apiBase) {
  let origin;
  try {
    origin = new URL(apiBase).origin;
  } catch {
    throw new Error('Learning App URL is invalid.');
  }
  const pattern = `${origin}/*`;
  const granted = await chrome.permissions.contains({ origins: [pattern] });
  if (granted) return;
  const requested = await chrome.permissions.request({ origins: [pattern] });
  if (!requested) throw new Error(`Chrome permission for ${origin} was not granted.`);
}

load();

async function extractTranscriptInMainWorld(language) {
  const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
  const label = (button) => `${button.getAttribute('aria-label') || ''} ${button.textContent || ''}`.toLowerCase();

  const parseCaptionBody = (body) => {
    if (!body.trim()) return [];
    if (body.trim().startsWith('{')) {
      try {
        const json = JSON.parse(body);
        return (json.events || []).flatMap((event, index) => {
          const text = (event.segs || []).map((segment) => segment.utf8 || '').join('').trim();
          if (!text) return [];
          const startMs = Number(event.tStartMs || 0);
          return [{ start_ms: startMs, end_ms: startMs + Number(event.dDurationMs || 0), text, sourceKey: String(index) }];
        });
      } catch { return []; }
    }
    const xml = new DOMParser().parseFromString(body, 'text/xml');
    return [...xml.querySelectorAll('text')].map((node, index) => ({
      start_ms: Math.round(Number(node.getAttribute('start') || 0) * 1000),
      end_ms: Math.round((Number(node.getAttribute('start') || 0) + Number(node.getAttribute('dur') || 0)) * 1000),
      text: node.textContent.trim(),
      sourceKey: String(index),
    })).filter((segment) => segment.text);
  };

  const readPlayerTimedText = async () => {
    const subtitleButton = document.querySelector('.ytp-subtitles-button, button.ytp-subtitles-button');
    const subtitleLabel = subtitleButton ? label(subtitleButton) : '';
    const canToggle = subtitleButton && !subtitleButton.disabled && subtitleButton.getAttribute('aria-disabled') !== 'true' && !/unavailable|недоступ|niedostęp/.test(subtitleLabel);
    const before = performance.getEntriesByType('resource').length;
    if (canToggle) subtitleButton.click();
    await wait(2500);
    const resources = performance.getEntriesByType('resource')
      .slice(before)
      .map((entry) => entry.name)
      .filter((url) => /(?:timedtext|api\/timedtext)/i.test(url));
    const attempts = [];
    for (const url of [...new Set(resources)].reverse()) {
      try {
        const response = await fetch(url, { credentials: 'include' });
        const body = await response.text();
        attempts.push({ status: response.status, bytes: body.length });
        const segments = parseCaptionBody(body);
        if (segments.length) return { segments, attempts };
      } catch (error) { attempts.push({ error: error.message }); }
    }
    return { segments: [], attempts };
  };

  const timedText = await readPlayerTimedText();
  if (timedText.segments.length) {
    return {
      title: document.title,
      segments: timedText.segments,
      debug: { path: 'main-world-timedtext-resource', timedTextAttempts: timedText.attempts },
    };
  }

  const buttons = [...document.querySelectorAll('button')];
  const transcriptButtons = buttons.filter((button) => {
    const value = label(button);
    return !/close|закры|zamkn/.test(value) && /transcript|транскрип|transkrypc/.test(value);
  });
  const showButton = transcriptButtons.find((button) => /show transcript|показать транскрип|wyświetl transkrypc/.test(label(button))) || transcriptButtons[0];
  showButton?.click();
  await wait(3500);

  const timestamp = (value) => {
    const parts = value.trim().split(':').map(Number);
    return parts.length && parts.every((part) => !Number.isNaN(part))
      ? parts.reduce((total, part) => total * 60 + part, 0) * 1000
      : 0;
  };
  const nodes = [...document.querySelectorAll('transcript-segment-view-model, [class*="ytwTranscriptSegmentViewModelHost"]')];
  const segments = nodes.map((node, index) => ({
    start_ms: timestamp(node.querySelector('.ytwTranscriptSegmentViewModelTimestamp')?.textContent || ''),
    end_ms: timestamp(nodes[index + 1]?.querySelector('.ytwTranscriptSegmentViewModelTimestamp')?.textContent || ''),
    text: (node.querySelector('.ytAttributedStringHost')?.textContent || '').trim(),
    sourceKey: String(index),
  })).filter((segment) => segment.text);

  return {
    title: document.title,
    segments,
    debug: {
      path: 'main-world-transcript-panel',
      timedTextAttempts: timedText.attempts,
      transcriptButtons: transcriptButtons.map((button) => label(button)),
      transcriptElements: nodes.length,
    },
  };
}
