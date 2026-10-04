# Transcript practice decisions (VIK-53)

2026-10-04 · **assumed**, product-owner proxy.

- Segment navigation shows sequence and timestamp only. Both dictation and
  shadowing keep the phrase hidden until the existing card's answer phase.
  This avoids giving away recall answers while keeping source navigation usable.
- Selecting another segment removes the explicitly supplied word link; selecting
  the current segment preserves it. Never infer a word from the segment or
  distribute its result to multiple words. A requested segment that is absent
  produces an empty state, not a substitute segment with the old word link.
- A completed transcript-only attempt says it was saved and that confidence,
  SRS and points do not change. A linked attempt says its result was recorded
  for the explicitly linked word. The existing API does not report individual
  effects, so the page promises no specific reward or review change.
- Existing shell route keys reset the card on segment navigation; changing mode
  resets the completion notice. Retry processing uses the existing attempt ID.
  No learner history, server outcome logic, SRS models or common cards change.

The central [product-owner log](../../../.agents/skills/product-owner/DECISIONS.md)
and current main-checkout skills were read only during this parallel run; these
scoped decisions are kept here to avoid concurrent edits to shared skill files.

## Verification

`docker/playwright/e2e/transcript-practice.mjs` drives the real SPA, router and
exercise cards with fixtures only at HTTP, YouTube and browser audio boundaries.
It checks hidden/revealed targets, segment playback, submitted word identity,
mode/segment reset, missing-segment state, return routes and 360/390px widths.

`TranscriptPracticeOutcomeTest` exercises the existing API and processor against
an in-memory SQLite database: transcript-only and explicitly linked attempts,
with/without an existing SRS card, retain their actual effects and retry without
additional rewards or history. These tests preserve the existing API contract;
VIK-30 owns server outcome/reward changes.
