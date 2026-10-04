# Repetition algorithm research (VIK-14)

Question: should repetition get in-session learning steps and/or move to FSRS?
What is the smallest change with a clear memory benefit? Decision:
[ADR-011](adr/ADR-011-repetition-scheduler-learning-steps-before-fsrs.md).

## Current scheduler (main, 2026-10-04)

Code: `src/app/Modules/Srs/Domain/IntervalCalculator.php`,
`Srs/Application/SrsService.php`, `Srs/Domain/ReviewGradeRules.php`.

- New card: `interval_days = 1`, `ease_factor = 2.5`, due now
  (`CreateSrsCardOnLearningStartedListener`).
- Grade ≤ 2 fails: interval 1 day, ease −0.2, state `relearning`, due tomorrow.
  Nothing brings the card back in the same session.
- Grade 3: interval × **1.3** (not × ease), ease −0.1. Grade 4: × ease, +0.05.
  Grade 5: × ease, +0.15. Ease clamped to 1.3–2.8.
- Interval grows from the *scheduled* previous interval, not the real elapsed
  time. Every graded outcome reschedules the card, due or not: Review
  (`SrsController::review`), exercise attempts (`ExerciseAttemptService`,
  score ≥ 90 → 3, else 1) and batch self-check (`SelfCheckService`, 3 or 1).

What learners actually send: the trainer has three buttons, **Again = 1,
Got it = 3, Easy = 5** (`WordCard.vue`); typed answers and exercises send 3 or 1.
Grades 2 and 4 are never sent by the UI.

### Finding: "Got it" never spaces a word out

From a new card (interval 1), Got it gives `round(1 × 1.3) = 1`. A word that is
always answered Got it is due **every day forever**, and its ease falls to the
1.3 floor. Only Easy grows the interval (1 → 3 days), after which Got it grows it
by 1.3× (3 → 4 → 5 → 7 → 9 …). Grade 3 in SM-2 is a pass that uses the
E-Factor (SuperMemo); in Anki, Good multiplies by ease and leaves ease unchanged
(Anki FAQ). Today's behaviour is the "low interval hell" Anki describes, and it
costs reviews without memory benefit. `IntervalCalculatorTest` never uses
grade 3, so no test pins this behaviour.

### Finding: same-day outcomes compound

Mixed practice (VIK-29: recognize → write → hear → use) grades the same word
several times in one round. After VIK-11 these all hit one word-keyed card. With
a correct ×ease rule, four same-day passes would multiply the interval by
about 2.5⁴ ≈ 39. Growth must use elapsed time, so a same-day pass adds nothing.

### Finding: the retention metric is not retention

`ProgressStatsService::getRetention` counts every review row at least 1/7/30
days after the word's *first* review, grouped by `content_lexeme_id`. It mixes
same-day exercise attempts with real recalls and ignores the gap since the last
review. It can't show whether a scheduling change improved memory. VIK-11
changes its grouping key to the canonical lexeme.

## Sources

| Source | What it says (used here) |
|---|---|
| [SuperMemo: SM-2](https://super-memory.com/english/ol/sm2.htm) | I(1)=1, I(2)=6, I(n)=I(n−1)×EF; EF' = EF + (0.1 − (5−q)(0.08 + (5−q)0.02)), min 1.3; q < 3 restarts repetitions; "after each repetition session of a given day repeat again all items that scored below four … until all of these items score at least four." |
| [Anki FAQ: which algorithm](https://faqs.ankiweb.net/what-spaced-repetition-algorithm.html) | Anki SM-2 adds learning steps ("performance during the learning stage does not reflect performance in the retaining stage"); failures in learning don't lower ease; Again −20 ease points, Good × ease with ease unchanged, Easy × ease × easy bonus, +15; late reviews boost the interval. FSRS: R/S/D memory model, "fewer reviews than Anki's default algorithm to achieve the same retention level". |
| [Anki manual: deck options](https://docs.ankiweb.net/deck-options.html) | Learning steps default `1m 10m`; Again → first step, Good → next step, last step → graduating interval; relearning steps for lapses. With FSRS: "Ensure that all your learning and re-learning steps are shorter than 1d … Steps such as 10m or 30m are good." Desired retention default 90%; FSRS parameters come from an optimizer over the learner's review history. |
| [FSRS algorithm wiki](https://github.com/open-spaced-repetition/awesome-fsrs/wiki/The-Algorithm) | FSRS-6: 21 parameters; R(t,S) = (1 + factor·t/S)^(−w20); interval solved from desired retention; ratings Again/Hard/Good/Easy; explicit same-day stability update. |
| [SRS benchmark](https://github.com/open-spaced-repetition/srs-benchmark) | ~10k Anki users, ~350M evaluated reviews: FSRS-6 log loss 0.346 vs AVG baseline 0.395 (no same-day reviews); FSRS default parameters (no optimization) are already usable (FSRS-7 default 0.362). |
| [awesome-fsrs implementations](https://github.com/open-spaced-repetition/awesome-fsrs) | Reference ports: Rust (`fsrs-rs`, with optimizer), Python (`py-fsrs`, with optimizer), TypeScript (`ts-fsrs`). **No PHP port listed.** Papers: Ye et al., KDD 2022; IEEE TKDE 2023. |
| [Rawson & Dunlosky 2011, JEP: General 140(3)](https://eric.ed.gov/?id=EJ934616) | Recall to a criterion in the first session, then relearn in spaced sessions. Relearning has "pronounced effects on long-term retention with a relatively minimal cost"; the initial-criterion effect fades as relearning grows. Prescription: 3 correct recalls initially, then 3 widely spaced relearning sessions. |

## Options compared

| Option | Memory benefit | Cost / risk |
|---|---|---|
| A. Tune SM-2-lite only (fix Good) | Removes daily re-showing of known words; no in-session relearning | Hours; failed words still wait a day with no correct recall |
| B. Fixed SM-2-lite + learning/relearning steps (Anki SM-2 shape) | Failed and new words get a correct recall in the same session (SM-2 same-day rule, Rawson & Dunlosky criterion); known words space out | Small: scheduler + session re-queue; fits the existing card columns |
| C. FSRS-6 now | Best calibrated model in the benchmark; target retention as one knob | No PHP port (port + test vectors, or a sidecar); optimizer needs a review history we don't have yet; current history is dominated by the Good-stuck bug and mixed exercise types; VIK-11 is reshaping cards right now |
| D. B now, FSRS behind the same scheduler seam later (chosen) | B's benefit now; FSRS when data shows SM-2 miscalibration | FSRS keeps sub-day steps (Anki guidance), so B's steps survive the switch |
