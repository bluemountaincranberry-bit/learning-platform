// Real transcript page/cards/router; only HTTP, microphone and YouTube are fixtures.
// Run in the browser container against an isolated worktree Vite preview.
import assert from 'node:assert/strict';
import { chromium } from 'playwright';
const baseURL = process.env.E2E_VITE_ORIGIN || 'http://172.18.0.1:5353';
const browser = await chromium.launch({ headless: true, args: ['--no-sandbox', '--disable-dev-shm-usage'] });
const context = await browser.newContext({ baseURL, viewport: { width: 360, height: 800 } });
const target = 'Could you help me?';
const segments = [
    { id: 11, sequence: 0, start_ms: 1000, end_ms: 3000, text: target, lexemes: [{content_lexeme_id: 7}], language:'en', source:'fixture' },
    { id: 12, sequence: 1, start_ms: 5000, end_ms: 7000, text: 'Another secret phrase', lexemes: [], language:'en', source:'fixture' },
];
const submissions = [];
let rejectSubmission = false;
const pageErrors = [];
await context.addInitScript(() => {
    localStorage.setItem('auth_token', 'transcript-fixture');
    window.playback = [];
    Object.defineProperty(navigator, 'mediaDevices', { configurable:true, value:{ getUserMedia:async () => ({getTracks:()=>[{stop(){}}]}) } });
    window.MediaRecorder = class {
        static isTypeSupported() {return true;}
        constructor() {this.mimeType='audio/webm';this.state='inactive';}
        start() {this.state='recording';}
        stop() {this.state='inactive';this.ondataavailable({data:new Blob(['fixture audio'], {type:'audio/webm'})});queueMicrotask(() => this.onstop());}
    };
    window.YT = { Player: class {
        constructor(element, options) { queueMicrotask(() => options.events.onReady()); }
        seekTo(seconds) { window.playback.push(['seek', seconds]); }
        playVideo() { window.playback.push(['play']); }
        pauseVideo() {}
        getCurrentTime() { return 0; }
        destroy() {}
    } };
});
await context.route('**/*', async route => {
    const request = route.request();
    const url = new URL(request.url());
    if (request.isNavigationRequest() && url.origin === new URL(baseURL).origin) return route.fulfill({ contentType:'text/html', body:'<div id="app"></div><script type="module" src="/resources/js/spa/main.ts"></script><link rel="stylesheet" href="/resources/css/app.css">' });
    if (url.hostname.includes('youtube')) return route.fulfill({ contentType:'text/javascript', body:'' });
    if (url.pathname === '/api/profile') return route.fulfill({json:{user:{id:1,name:'Tester',daily_goal:10},today_learned_count:0,streak_days:0}});
    if (url.pathname === '/api/auth/me') return route.fulfill({json:{user:{id:1,name:'Tester',email:'test@example.test'},roles:['student']}});
    if (url.pathname === '/api/content/1') return route.fulfill({json:{content:{id:1,title:'Practice source',source_url:'https://www.youtube.com/watch?v=dQw4w9WgXcQ',language:'en'}}});
    if (url.pathname === '/api/content/1/transcript') return route.fulfill({json:{segments,has_more:false}});
    if (url.pathname === '/api/learning/exercise-attempts' && request.method() === 'POST') {
        if (rejectSubmission) return route.fulfill({status:503,json:{message:'Submission unavailable'}});
        submissions.push(request.postData());
        return route.fulfill({ status:202,json:{attempt:{id:submissions.length,status:'pending'}} });
    }
    if (/\/api\/learning\/exercise-attempts\/\d+$/.test(url.pathname)) return route.fulfill({json:{attempt:{id:submissions.length,status:'completed',exercise_type:'dictation',score:100,is_correct:true,user_text:target,error_type:null}}});
    if (url.pathname === '/api/content/1/readiness') return route.fulfill({status:503,json:{message:'Return destination fixture unavailable'}});
    if (url.pathname === '/api/learning/flow') return route.fulfill({json:{profile:null,config:{},presets:[]}});
    if (url.pathname.startsWith('/api/')) return route.fulfill({json:{data:[]}});
    return route.continue();
});
const page = await context.newPage();
page.setDefaultTimeout(10000);
page.on('pageerror', error => pageErrors.push(error.message));
try {
  for (const width of [360,390]) {
    await page.setViewportSize({width,height:800});
    await page.goto('/practice/transcript?content_id=1&segment_id=11&return_to=repetitions');
    await page.getByRole('heading', {name:'Listen and type what you hear'}).waitFor();
    assert.equal((await page.locator('body').innerText()).includes(target), false, 'expected answer must be hidden outside and inside the card before reveal');
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `hidden state fits ${width}px`);
    console.log('PASS target hidden');
    await page.waitForFunction(() => window.YT && document.querySelector('script[data-youtube-iframe-api]'));
    await page.locator('button:has(svg)').first().click();
    assert.deepEqual(await page.evaluate(() => window.playback), [['seek',0.7],['play']], 'Dictation Replay must play selected segment');
    await page.getByRole('button', {name:'Replay context'}).click();
    assert.deepEqual(await page.evaluate(() => window.playback.slice(-2)), [['seek',0.7],['play']]);
    console.log('PASS both replays');
    assert.equal((await page.locator('body').innerText()).includes('Your result will update learning progress and SRS.'), false, 'transcript-only practice must not promise word effects');
    await page.getByLabel('Your answer', {exact:true}).fill(target);
    await page.getByRole('button', {name:'Check answer'}).click();
    await page.getByText('Correct answer:').waitFor();
    await page.getByText('Attempt saved.', {exact:false}).waitFor();
    assert.ok((await page.locator('body').innerText()).includes('does not change word confidence, SRS or points'));
    assert.equal(/name="content_lexeme_id"/.test(submissions.at(-1)), false);
    console.log('PASS transcript-only save/reveal/copy');
    await page.getByRole('button', {name:'Segment 2'}).click();
    await page.getByLabel('Your answer', {exact:true}).waitFor();
    assert.equal(await page.getByLabel('Your answer', {exact:true}).inputValue(), '');
    const freshText = await page.locator('body').innerText();
    assert.equal(freshText.includes(target), false);
    assert.equal(freshText.includes('Another secret phrase'), false);
    assert.equal(freshText.includes('Attempt saved.'), false);
    console.log('PASS segment change resets result and answer');
    await page.goto('/practice/transcript?content_id=1&segment_id=11&content_lexeme_id=7&return_to=repetitions');
    await page.getByLabel('Your answer', {exact:true}).fill(target);
    await page.getByRole('button', {name:'Check answer'}).click();
    await page.getByText('The result is recorded for the explicitly linked word.').waitFor();
    assert.match(submissions.at(-1), /name="content_lexeme_id"\r\n\r\n7/);
    await page.getByRole('button', {name:'Segment 2'}).click();
    await page.getByLabel('Your answer', {exact:true}).fill('Another secret phrase');
    await page.getByRole('button', {name:'Check answer'}).click();
    await page.getByText('Attempt saved.', {exact:false}).waitFor();
    assert.equal(/name="content_lexeme_id"/.test(submissions.at(-1)), false, 'another segment must not reuse the previously linked word');
    console.log('PASS explicit link retained only for its segment');
    const changedRoute = new URL(page.url());
    assert.equal(changedRoute.searchParams.get('return_to'), 'repetitions');
    assert.equal(changedRoute.searchParams.get('content_id'), '1');
    await page.getByRole('button', {name:/^Shadowing Listen/}).click();
    await page.getByRole('button', {name:'Record your voice'}).waitFor();
    assert.equal((await page.locator('body').innerText()).includes('Another secret phrase'), false);
    await page.locator('button:has(svg)').first().click();
    assert.deepEqual(await page.evaluate(() => window.playback.slice(-2)), [['seek',4.7],['play']], 'shadowing uses the current segment');
    await page.getByRole('button', {name:'Record your voice'}).click();
    await page.getByRole('button', {name:/Stop recording/}).click();
    await page.getByRole('button', {name:'Check pronunciation'}).click();
    await page.getByText('Pronunciation result', {exact:true}).waitFor();
    assert.ok((await page.locator('body').innerText()).includes('Another secret phrase'));
    await page.getByText('Attempt saved.', {exact:false}).waitFor();
    assert.equal(/name="content_lexeme_id"/.test(submissions.at(-1)), false);
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `no horizontal scroll at ${width}px`);
    await page.evaluate(() => scrollTo(0,0));
    if (process.env.E2E_ARTIFACT_DIR) await page.screenshot({path:`${process.env.E2E_ARTIFACT_DIR}/transcript-${width}.png`,fullPage:true});
    await page.getByRole('button', {name:/^Dictation Listen/}).click();
    await page.getByLabel('Your answer', {exact:true}).waitFor();
    assert.equal((await page.locator('body').innerText()).includes('Attempt saved.'), false);
    rejectSubmission = true;
    await page.getByLabel('Your answer', {exact:true}).fill('Another secret phrase');
    await page.getByRole('button', {name:'Check answer'}).click();
    await page.getByText('Request failed with status code 503', {exact:true}).waitFor();
    assert.equal((await page.locator('body').innerText()).includes('Attempt saved.'), false);
    assert.equal((await page.locator('body').innerText()).includes('Another secret phrase'), false);
    rejectSubmission = false;
    await page.getByRole('button', {name:'← Back'}).click();
    await page.waitForURL(url => url.pathname === '/repetitions' && url.searchParams.get('content_id') === '1', {waitUntil:'commit'});
    await page.goto('/practice/transcript?content_id=1&segment_id=999&content_lexeme_id=7');
    await page.getByText('The selected transcript segment is unavailable.', {exact:false}).waitFor();
    assert.equal(await page.getByLabel('Your answer', {exact:true}).count(), 0);
    await page.getByRole('button', {name:'← Back'}).click();
    await page.waitForURL('**/catalog/1');
    console.log(`PASS ${width}px: shadowing reveal/replay/save, mode reset, source/return context, missing segment, no horizontal scroll`);
  }
  assert.deepEqual(pageErrors, [], 'no unhandled browser errors');
} finally { await context.close(); await browser.close(); }
