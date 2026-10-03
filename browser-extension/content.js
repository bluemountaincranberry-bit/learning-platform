function pickTrack(tracks, language) {
  return tracks.find((track) => track.languageCode === language)
    || tracks.find((track) => track.languageCode?.startsWith(`${language}-`))
    || tracks[0];
}

function playerResponse() {
  const responses = [];
  for (const source of [...document.scripts].map((item) => item.textContent || '')) {
    const response = parsePlayerResponse(source);
    if (response) responses.push(response);
  }

  const htmlResponse = parsePlayerResponse(document.documentElement?.outerHTML || '');
  if (htmlResponse) responses.push(htmlResponse);

  return responses.find((response) => response?.captions?.playerCaptionsTracklistRenderer?.captionTracks?.length)
    || responses[0]
    || null;
}

function parsePlayerResponse(source) {
  const marker = 'ytInitialPlayerResponse';
  const markerIndex = source.indexOf(marker);
  if (markerIndex < 0) return null;

  const jsonStart = source.indexOf('{', markerIndex);
  if (jsonStart < 0) return null;

  let depth = 0;
  let inString = false;
  let escaped = false;
  for (let index = jsonStart; index < source.length; index += 1) {
    const character = source[index];
    if (inString) {
      if (escaped) escaped = false;
      else if (character === '\\') escaped = true;
      else if (character === '"') inString = false;
      continue;
    }
    if (character === '"') inString = true;
    else if (character === '{') depth += 1;
    else if (character === '}' && --depth === 0) {
      try { return JSON.parse(source.slice(jsonStart, index + 1)); } catch { return null; }
    }
  }
  return null;
}

chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (message.type !== 'extractTranscript') return;
  const debug = { startedAt: new Date().toISOString() };
  (async () => {
    const response = await getPlayerResponse(debug);
    const tracks = response?.captions?.playerCaptionsTracklistRenderer?.captionTracks || [];
    debug.trackCount = tracks.length;
    const track = pickTrack(tracks, message.language);
    debug.selectedLanguage = track?.languageCode || null;
    let segments = track?.baseUrl ? await fetchTrackSegments(track.baseUrl, debug) : [];
    if (segments.length) debug.path = 'caption-track';
    if (!segments.length) segments = await readTranscriptPanel(debug);
    if (segments.length && !debug.path) debug.path = 'transcript-panel';
    debug.segmentCount = segments.length;
    console.info('[Learning App YouTube]', debug);
    if (!segments.length) {
      if (!track?.baseUrl) throw new Error('YouTube did not expose a captions track.');
      throw new Error(`YouTube returned an empty captions response (tracks: ${tracks.length}, transcript elements: ${document.querySelectorAll('transcript-segment-view-model, [class*="ytwTranscriptSegmentViewModelHost"]').length}). Open “Show transcript” and try again.`);
    }
    sendResponse({ title: response?.videoDetails?.title || document.title, segments, debug });
  })().catch((error) => {
    debug.error = error.message;
    console.warn('[Learning App YouTube]', debug);
    sendResponse({ error: error.message, debug });
  });
  return true;
});

async function fetchTrackSegments(baseUrl, debug) {
  const urls = [
    `${baseUrl}${baseUrl.includes('?') ? '&' : '?'}fmt=json3`,
    baseUrl,
  ];
  debug.trackAttempts = [];
  for (const url of urls) {
    try {
      const response = await fetch(url, { credentials: 'include' });
      const body = await response.text();
      debug.trackAttempts.push({ format: url === baseUrl ? 'xml-default' : 'json3', status: response.status, bytes: body.length });
      if (!response.ok) continue;
    if (!body.trim()) continue;
    if (body.trim().startsWith('{')) {
      try {
        const json = JSON.parse(body);
        const segments = (json.events || []).flatMap((event, index) => {
          const text = (event.segs || []).map((segment) => segment.utf8 || '').join('').trim();
          if (!text) return [];
          const startMs = Number(event.tStartMs || 0);
          return [{ start_ms: startMs, end_ms: startMs + Number(event.dDurationMs || 0), text, sourceKey: String(index) }];
        });
        if (segments.length) return segments;
      } catch {
        // Try the XML representation below.
      }
    }
    const xml = new DOMParser().parseFromString(body, 'text/xml');
    const segments = [...xml.querySelectorAll('text')].map((node, index) => ({
      start_ms: Math.round(Number(node.getAttribute('start') || 0) * 1000),
      end_ms: Math.round((Number(node.getAttribute('start') || 0) + Number(node.getAttribute('dur') || 0)) * 1000),
      text: node.textContent.trim(),
      sourceKey: String(index),
    })).filter((segment) => segment.text);
    if (segments.length) return segments;
    } catch (error) {
      debug.trackAttempts.push({ format: url === baseUrl ? 'xml-default' : 'json3', error: error.message });
    }
  }
  return [];
}

async function readTranscriptPanel(debug) {
  const existing = readTranscriptSegments();
  if (existing.length) return existing;

  const expand = document.querySelector('#expand, tp-yt-paper-button#expand');
  if (expand && expand.offsetParent !== null) {
    expand.click();
    await new Promise((resolve) => setTimeout(resolve, 500));
  }

  const candidates = [...document.querySelectorAll('button')].filter((candidate) => {
    const label = `${candidate.getAttribute('aria-label') || ''} ${candidate.textContent || ''}`.toLowerCase();
    return !/close|закры|zamkn/.test(label) && /transcript|транскрип|transkrypc/.test(label);
  });
  debug.transcriptButtons = candidates.map((candidate) => ({
    label: candidate.getAttribute('aria-label') || candidate.textContent?.trim() || '',
    visible: candidate.offsetParent !== null,
  }));
  const showButtons = candidates.filter((candidate) => /show transcript|показать транскрип|wyświetl transkrypc/i.test(`${candidate.getAttribute('aria-label') || ''} ${candidate.textContent || ''}`));
  const buttonsToTry = [...showButtons, ...candidates.filter((candidate) => !showButtons.includes(candidate))];
  for (const button of buttonsToTry) {
    debug.clickedTranscriptButton = button.getAttribute('aria-label') || button.textContent?.trim() || '';
    button.click();
    for (const delay of [500, 1000, 1500]) {
      await new Promise((resolve) => setTimeout(resolve, delay));
      const segments = readTranscriptSegments();
      debug.transcriptElements = document.querySelectorAll('transcript-segment-view-model, [class*="ytwTranscriptSegmentViewModelHost"], ytd-transcript-segment-renderer').length;
      if (segments.length) return segments;
    }
  }
  return readTranscriptSegments();
}

function readTranscriptSegments() {
  const modernNodes = [...document.querySelectorAll('transcript-segment-view-model, [class*="ytwTranscriptSegmentViewModelHost"]')]
    .filter((node, index, nodes) => nodes.indexOf(node) === index);
  const modernSegments = modernNodes
    .map((node, index, nodes) => ({
      start_ms: parseTranscriptTimestamp(node.querySelector('.ytwTranscriptSegmentViewModelTimestamp')?.textContent || ''),
      end_ms: parseTranscriptTimestamp(nodes[index + 1]?.querySelector('.ytwTranscriptSegmentViewModelTimestamp')?.textContent || ''),
      text: (node.querySelector('.ytAttributedStringHost')?.textContent || '').trim(),
      sourceKey: String(index),
    }))
    .filter((segment) => segment.text);
  if (modernSegments.length) return modernSegments;

  return [...document.querySelectorAll('ytd-transcript-segment-renderer')]
    .map((node, index) => ({
      start_ms: 0,
      end_ms: 0,
      text: (node.querySelector('#segment-text, .segment-text')?.textContent || node.textContent || '').trim(),
      sourceKey: String(index),
    }))
    .filter((segment) => segment.text);
}

function parseTranscriptTimestamp(value) {
  const parts = value.trim().split(':').map(Number);
  if (!parts.length || parts.some(Number.isNaN)) return 0;
  return Math.round(parts.reduce((total, part) => total * 60 + part, 0) * 1000);
}

async function getPlayerResponse() {
  for (const delay of [0, 500, 1500]) {
    if (delay) await new Promise((resolve) => setTimeout(resolve, delay));
    const response = playerResponse();
    if (response?.videoDetails?.videoId) return response;
  }
  return null;
}
