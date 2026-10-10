# Practice page: compact mobile-first design

Design note for [VIK-69](https://linear.app/viktoryia/issue/VIK-69/practice-page-compact-layout-continue-where-i-stopped-clear-modes-and).

## Findings

- The current Practice page puts the session description, learning-flow profile,
  and every practice mode in a long vertical list. On a phone the learner must
  scroll past several large cards before reaching secondary modes and scope
  controls.
- The repository's Clozemaster references (`example.jpg` and
  `photo_2026-08-13_*.jpg`) use a compact launch surface, one prominent action,
  and a separate settings panel with selectable choices. The useful pattern is
  progressive disclosure; the app keeps its existing light theme, English UI,
  and purple primary action.
- Existing entry points already carry source and selected-word IDs in the URL.
  The trainer rebuilds its eligible queue from current server state when a
  session starts, so completed review outcomes are not replayed.

## Design

- The first screen shows a short title, the current source, the saved practice
  mode, and one full-width primary action. After a learner starts a session,
  store that mode and source per signed-in user. On a later visit, `Continue`
  starts a fresh eligible queue with that same setup; current due words and
  saved word states determine its cards. If no prior setup exists, use the
  default adaptive mode and label the action `Start practice`.
- `Change` opens a mobile bottom sheet and a centered desktop dialog. The
  learner selects a mode there and confirms with one primary action. Keep the
  available modes and their concise explanations; place less common exercises
  under a collapsed `More practice` group.
- Keep trainer settings available from the header. Add an explicit `Edit
  learning flow` action to the existing profile editor. Scope changes remain
  available from the source summary.
- Preserve all current navigation destinations, focused modes, answer styles,
  and per-mode settings. The summary reports the last setup, not a promised
  exact interrupted card, because the current trainer does not persist a card
  queue or in-progress answer.

## Assumptions

- `Continue` means continue with the most recently used source and mode, while
  rebuilding the queue from current server state. Persist no answers or word
  payload in browser storage.
- The ticket's “editor” means the existing learning-flow profile editor.
- Design direction was inferred from Vika's request to proceed and the
  repository references; no visual interview was needed.
