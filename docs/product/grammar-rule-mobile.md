# Grammar rule page on mobile (VIK-41)

The rule detail page uses a compact title row (back arrow, title, level and
secondary-action menu) followed by one primary action. Add to my grammar
becomes Practice after saving; Practice scrolls to the existing exercises.
Discuss with AI, Mark as learned and Remove remain in the menu. Back returns
to the previous app page, or the grammar list on a direct visit.

On phones the sections use the page width without another padded card.
Examples use 18px sentence text, marked grammar spans when available, and
16px lighter translation text below. Unmarked examples retain their sentence.
Three examples appear initially; Show all/Show fewer controls the rest.
More examples expands the list so a generated batch is visible. Speech and
personal hiding retain the existing behavior. Missing target spans and
per-learner translation languages remain VIK-56 work.

## Decisions

The layout and three-example preview come directly from VIK-41. Always-visible
translations follow the VIK-39 decision in
[DECISIONS.md](../../.agents/skills/product-owner/DECISIONS.md).
No new product direction, catalog publication, schema or data change.

## Verification, 2026-10-04

29 focused Vitest tests pass across the page, header, examples and span parser.
Tests ran with Happy DOM on an isolated copy of the worktree resources and
existing installed dependencies under the session's scratch directory. The
back-navigation fixture explicitly supplies the browser history adapter's
`back` state because memory history does not generate it.
Frontend boundaries, naming conventions and `git diff --check` pass.

Two-axis code review: no Spec findings or new documented Standards violations.
One low-priority Standards smell remains: the chat route query is also built
by AskAiButton. Its shared UI file was kept outside this ticket's scope.

The required `make wt-build` did not run successfully: snap-confine reported
missing `cap_dac_override`. Automatic approval rejected elevated execution;
no alternative build or runtime path was used to bypass that refusal.
Browser measurements at 360/390px, horizontal overflow, actual font sizing
and first-screen explanation visibility remain **not verified**. The source
layout targets a 100px header (44px row + 12px gap + 44px button), but this is
not a browser measurement. The ticket must stay In Review until the build
and mobile checks pass.

## Phone review steps

Once an authorized worktree preview is running, open `/grammar/<rule-id>`
at 360px and 390px. Measure the header including actions (at most about
120px at 390px), confirm Explanation begins in the first screen, and check
for horizontal overflow. Try unsaved, saved, learned and guest states; open
the menu and use every action. Check long titles, marked/unmarked examples,
Show all/Show fewer and pronunciation in a browser supporting speech.
