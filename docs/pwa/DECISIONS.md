# VIK-22 product decisions

2026-10-04, **assumed (PO proxy)**, derived from product-owner VISION.md's
mobile-first, basic reading and simple default principles:

- Save the ten most recently read lessons and their message text automatically,
  plus the last lesson-list response and the session identity needed to reopen
  them. Offline is reading only; writes and AI requests still need the network.
- Cache private responses only for the currently selected bearer-token session;
  store its SHA-256 digest rather than a second copy of the credential. Logout,
  account changes and server auth denial discard the private snapshots. A new
  login, even to the same account, starts fresh. These are disposable copies;
  server learning data is preserved.
- Cache HTML/CSS/JS as a matched release. An updated worker waits for old tabs to
  close before activating, to preserve unsaved work and prevent mixed bundles.
  A new release discards previous private snapshots; reopen lessons online to
  refresh offline copies. No forced reload while writing notes.
- Cache text/structured lesson responses, not attachment binaries, audio, video,
  arbitrary private APIs or admin pages. Offline attachments can be added later
  through a separately approved scope.

This ticket-specific log avoids concurrent edits of the shared skill-owned
DECISIONS.md. No product-owner Escalate item; the session authorizes Linear and
PR operations. Native Android/iOS installation must still be checked on devices.
