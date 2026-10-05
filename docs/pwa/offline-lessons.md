# Installable Learning App and offline lessons (VIK-22)

Open the **production build on HTTPS**. Sign in and open a lesson while online;
its notes/messages, words and grammar are saved automatically. Up to ten recent
lessons remain readable after a reload or new tab without network. The last
lesson-list responses help navigate back. A new login or release needs an online
visit again. Browser storage can be evicted; offline copies are not backups.

On Android Chrome, use **Install app / Add to Home screen** in the browser menu.
On iPhone/iPad Safari, use **Share → Add to Home Screen**, then open the icon and
sign in there (Safari and home-screen storage may be separate). Open a lesson,
turn on airplane mode and relaunch its icon. Verify the icon and standalone
window on both devices before merging this ticket. Browser automation verifies
Chromium/WebKit reading but cannot operate the native installation menus.

Offline reading preserves the current lesson UI. Sending notes, running analysis
and other mutations require network; nothing is queued or replayed. Attachments,
video/audio and unrelated private APIs are not downloaded. Failed writes use
the existing UI error path. Product defaults: [DECISIONS.md](DECISIONS.md).

## Privacy and versioning

The service worker admits private reads only when the request's bearer-token
SHA-256 digest matches the active offline session. Actual credentials are not
stored in CacheStorage. Session switches and logout purge all private caches;
a generation check blocks late responses from the previous session. Other tabs
reload when the stored token changes. HTTP 401/403 revokes cached access;
404 invalidates the missing lesson. Network exceptions can use a snapshot;
HTTP errors are never replaced by successful stale content. Successful message writes invalidate only messages, retaining the detail
snapshot that the unchanged UI does not refetch. Other lesson mutations evict
the affected lesson until the next read. The last list remains a reading snapshot.

The Vite build emits `public/sw.js` at the root for scope `/` and
`public/build/offline.html` with matched hashed JS/CSS. The worker version hashes
its source, the generated shell, asset paths and manifest/icon contents. Each
version has separate static/private caches. Controlled app navigations always use the active release shell; asset downloads
and server/admin routes remain outside that navigation fallback. A new worker waits until all old
controlled tabs close, then activates and deletes old version caches. It does
not force a reload while someone is writing. Online API reads are network-first.

## Deployment contract

- Run `make wt-build` (or `make npm ARGS="run build"` in the main checkout).
  Publish **the whole build, manifest/icons and generated `public/sw.js` together**.
  `sw.js` and `build/offline.html` are generated and excluded from Git.
- Serve `/sw.js` as JavaScript, HTTPS, scope `/`, without redirects; configure
  `Cache-Control: no-cache` for `/sw.js`, HTML and the manifest. Hashed
  `/build/assets/*` may use immutable caching. Keep previous hashed assets
  through rollout while old tabs are open. Do not route missing build files to
  a successful HTML SPA response.
- Keep the deployment atomic. An incomplete asset set makes SW installation fail
  rather than activating a partially cached release. Development Vite builds
  do not register a worker. Use a separate origin for dev if a production worker
  has previously controlled it.
- With all app tabs closed, reopen online after deploy, open the lesson again,
  then disconnect. Confirm the new release is the only asset/private cache and
  the refreshed notes can be read. No migrations or worker/queue restart needed.

Implementation sources: [MDN service-worker lifecycle](https://developer.mozilla.org/en-US/docs/Web/API/Service_Worker_API/Using_Service_Workers),
[Apple home-screen configuration](https://developer.apple.com/library/archive/documentation/AppleApplications/Reference/SafariWebContent/ConfiguringWebApplications/ConfiguringWebApplications.html),
[WebKit home-screen storage](https://webkit.org/blog/14787/webkit-features-in-safari-17-2/).

## Focused verification

- `make wt-test ARGS="--filter=PwaShellTest"`: direct SPA entry and installation
  metadata through Laravel; test-only encryption key, no learner DB writes.
- `node --test src/tests/Pwa/service-worker.test.mjs`: SW fetch/message/lifecycle
  seams, cold start, logout/account change, delayed response, HTTP denial,
  ten-lesson bound, release cleanup, mutation invalidation and storage failures.
- `src/tests/Pwa/pwa-browser.mjs` runs in a one-off `blue-browser` container
  with the worktree mounted at `/workspace:ro`, `--network none`, and
  `PWA_ARTIFACT_DIR` pointing to a writable artifact directory. Copy the script
  to `/tmp` and link `/tmp/node_modules` to `/app/node_modules` inside that
  container. Its loopback fixture server serves real production assets and
  synthetic APIs; Real fixture transport disconnection (plus Chromium offline emulation) exercises
  reload/new-tab offline reading,
  360/390px overflow, update activation, account change and logout. No production credentials
  or database are used.

Parallel sessions must hold the existing
`.claude/parallel-runs/20261004-five-tasks/merge.lock` for Docker operations and
fetch/rebase/recheck/push/PR/merge.
