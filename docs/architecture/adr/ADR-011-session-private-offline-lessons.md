# Session-private offline lessons and coherent PWA releases

VIK-22 caches lesson text through the service-worker HTTP seam, leaving lesson UI
and domain APIs unchanged. A persisted SHA-256 bearer-token digest admits only
requests from the active session; logout/account changes purge private caches,
and a generation check rejects late writes from the previous session. Network
errors may use snapshots, but auth denial revokes them. No private mutations
are replayed. Server learning data remains the source of truth.

The worker precaches a public SPA shell and hashed assets as one release.
Controlled learner navigation uses that release's shell even online; fetching
current server HTML while an old worker waits would mix new JS with old private
snapshots. Updates activate after old tabs close, without interrupting unsaved
notes, then remove old version caches. Private snapshots must be refreshed
online after a new release or login. Browser eviction/storage failures can
remove offline availability; an online read still returns its fresh response.

Registration reuses the active worker on offline boot rather than awaiting a
network update. The SPA synchronizes the session before routing/API requests,
purges on logout initiation, and reloads other tabs when the stored token changes.
The cache is not encrypted storage or a backup: same-origin scripts/browser
users retain the usual access to browser storage. Cache only the defined reading
endpoints, not unrelated private data or attachment binaries. Defaults and
verification/deployment contract: [PWA docs](../../pwa/offline-lessons.md).
