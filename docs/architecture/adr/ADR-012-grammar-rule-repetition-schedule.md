# ADR-012: Grammar rules repeat on the shared scheduler, one rating per round

Status: Accepted as a PO-proxy `assumed` decision (VIK-10, 2026-10-04). That
grammar is repeated at all is Vika's `answered` decision; the rules below are
the proxy's and Vika may revise them, see
[DECISIONS.md](../../../.agents/skills/product-owner/DECISIONS.md).
Evidence: [research note](../grammar-repetition-research.md).
Product spec: [grammar exercises block](../../product/grammar-exercises-block.md).
Implementation: VIK-65; Today entry point: VIK-47.

Each rule in My grammar has its own **grammar review** schedule. A finished
practice round on the rule is one review. Its score becomes one rating, and the
ADR-011 rules turn that rating into the next date. Exercises are not
scheduled. Each review round takes fresh exercises from the rule's pool.

## Rules

1. **What is scheduled.** Every My grammar rule, `learning` or `learned`,
   catalog or personal (VIK-26). Schedule state lives on the My grammar row:
   `next_practice_at`, `interval_days`, `ease` (start 2.5), `last_reviewed_at`,
   `lapse_count`. A rule with no counted review is **new**.
2. **Entering.** A rule added to My grammar (catalog, lesson, or VIK-31
   auto-add) is new and due at once. If the add came from a finished round,
   that round is its first review. Removing a rule from My grammar clears
   `next_practice_at` and keeps the rest. Re-adding it resumes from the kept
   state.
3. **What counts as a review.** A finished `practice` round or a content
   `post` exam on a My grammar rule, with at least 3 scored items. These do not
   count: `pre` exams (they are diagnostic), **Practice mistakes** rounds (the
   same-session relearn), and any round after the first counted one on the same
   learner-local day. Rounds that do not count still update confidence and
   history.
4. **Rating from score** (`score_pct`, the VIK-31 formula):

   | Score | Rating | Next |
   |---|---|---|
   | < 60% | **Again** | Lapse: 1 day, ease −0.2 (floor 1.3), `lapse_count` +1. A new rule stays new. |
   | 60–79% | **Hold** | Same interval again (min 1 day). Ease unchanged. A new rule stays new. |
   | ≥ 80% | **Got it** | New → 1 day; second pass → 3 days; then interval × ease. |
   | ≥ 90% on a Medium or Hard round | **Easy** | New → 3 days; then interval × ease × 1.3, ease +0.15. |

   An Easy-level round (recognition only) gives at most Got it. Hold is the
   one grammar-specific rating. A 70% round shows partial control, so the rule
   neither grows nor lapses.
5. **Elapsed time, not schedule.** As ADR-011 rule 4: a pass grows from the
   real time since the last counted review,
   `next = max(current, round(elapsed_days × factor))`. Practicing early never
   shortens a schedule. A late pass grows from the longer gap. Again is a lapse
   whenever it happens.
6. **Cap.** `interval_days` ≤ 90, so every rule returns at least once a
   quarter.
7. **Learned is separate.** The schedule never changes `learning` / `learned`
   (VIK-40: a result never changes "learned" by itself). Learned rules keep
   their schedule. A lapse on a learned rule makes it due tomorrow and the
   result screen says the rule needs practice again.
8. **Due.** A rule is due when `next_practice_at` ≤ the end of the learner's
   local day (`users.timezone`, else app timezone).
9. **History.** Each counted attempt stores its `schedule_rating`
   (`again | hold | good | easy`). Uncounted attempts store `null`. The attempt
   rows plus this column are enough to replay and re-fit (FSRS later).

## Today and My grammar

- **Rule of the day** (VIK-47): the due rule with the largest
  `overdue_days / interval_days`, then the lowest confidence, then the earliest
  added. Reviewed rules come before new ones. At most **one new rule per day**
  enters through Today, so six rules saved from one lesson don't arrive as a
  wall. The others stay available in My grammar.
- The Today round is **Medium · 5** (one of each type). It counts as a review.
  After it, if more rules are due: "N more rules due" → the next rule. Never
  forced.
- **Nothing due** → "Extra practice": the `learning` rule with the lowest
  confidence not practiced in the last 24 h. Elapsed rule 5 keeps the early
  round from distorting its schedule. My grammar empty → no card.
- **No exercises and AI down** → skip to the next due rule that has exercises.
  The skipped rule stays due.
- **My grammar** shows a "Due" badge, sorts due rules first, and shows
  "Next practice in N days" or "Due today" on every row.

## Module boundaries

- **Srs** owns the scheduling rules: a pure scheduler that takes the
  current state, the rating and the elapsed days, and returns the next interval
  and ease. It reuses the ADR-011 graduation and growth rules (VIK-33). If
  VIK-33 has not landed, the grammar ticket creates the seam with these rules
  and VIK-33 builds on it. Do not create a second copy.
- **Learning** owns My grammar and its schedule state. A
  `GrammarPracticeCompleted` (and post-exam) listener decides whether the
  attempt counts (rule 3), maps the score to a rating (rule 4), calls the Srs
  scheduler through its contract and saves the result. It also serves due rules
  and the rule of the day for Today.
- Grammar does not use `srs_cards`. VIK-11 reshapes them around lexemes, and
  the word review queue and its counts stay words-only.

## Existing data

Add the columns. Then, for each existing My grammar row, replay its counted
attempts in time order through these rules. A row with no counted attempts
becomes new and due. This only adds columns and computes values, so no learner
data is rewritten or lost.

## Measurement

Grammar retention is the pass rate (≥ 80%) of counted reviews by elapsed time
since the previous counted review. Use the same `1 / 7 / 30` buckets as
ADR-011. Target 80–90%. Below that, shorten the growth factor. Above it with
a high load, lengthen it. Revisit together with the FSRS decision in ADR-011.

## Considered options

See the [research note](../grammar-repetition-research.md#options-compared):
a fixed 1/3/7/14 ladder (stops growing, second algorithm), per-exercise cards
(memorizes sentences), grammar rows in `srs_cards` (conflicts with VIK-11),
FSRS now (deferred) and confidence-only selection (no time dimension).

## Consequences

- Replaces the VIK-40 draft ladder (1 → 3 → 7 → 14, hold 60–79%, reset < 60%).
  Hold and reset stay. The growth and the cap change.
- Implementation: VIK-65 (Learning + Srs, after VIK-31). VIK-47 (Today)
  uses its due-rule query.
- Extends ADR-011 with one rating (Hold) used only by grammar. Word reviews
  keep the three ratings.
- Mixed rounds over several due rules (interleaving) are the evidence-backed
  next step. They are a separate follow-up and not part of this decision.
