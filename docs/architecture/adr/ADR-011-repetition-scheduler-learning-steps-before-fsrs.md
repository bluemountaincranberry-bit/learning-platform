# ADR-011: Learning steps on a fixed SM-2 scheduler now, FSRS later

Status: Accepted (VIK-14, 2026-10-04; PO-proxy assumption, see
[DECISIONS.md](../../../.agents/skills/product-owner/DECISIONS.md)).
Implementation: VIK-33, after VIK-11 ([ADR-010](ADR-010-word-keyed-repetition-and-personal-lexemes.md)).
Evidence and sources: [research note](../repetition-algorithm-research.md).

Keep the SM-2-family scheduler on the word-keyed repetition card. Fix its
growth rules and add learning/relearning steps, so a failed or new word is
recalled correctly before the session ends and known words space out. Do not
adopt FSRS yet. It needs a PHP port and a clean review history to fit, and
neither exists today. Put the scheduler behind one seam so FSRS can replace the
day-scale part later. Sub-day steps stay, as Anki recommends for FSRS.

The largest current defect is not the algorithm family. "Got it" (grade 3)
multiplies a 1-day interval by 1.3, which rounds back to 1. A word always
answered Got it is due every day forever.

## Scheduling rules (for VIK-33)

Card phases: **new → learning → review**. A lapse moves review →
**relearning** → review. Ratings are the trainer's three: Again (fail),
Got it (pass), Easy (pass). Any graded outcome, from Review or an exercise,
uses these rules.

1. **Learning step: 10 min.** A new card, or a lapsed one, enters the step and
   is due in 10 min. The current session re-shows it after a few other cards,
   or last if fewer remain. A pass completes the step. Again repeats it.
   Re-show at most twice per session. If the session ends first, the card stays
   due and opens the next session. Step outcomes never change ease
   (Anki: "initial acquisition does not influence a card's ease").
2. **Graduation: 1 day, then 3 days.** Got it on the step → review, interval
   1 day. Next pass → 3 days (SM-2 uses a fixed second interval of 6 days; 3 is
   gentler for new vocabulary). Easy on the step → straight to 3 days.
3. **Review growth.** Got it: interval × ease, ease unchanged. Easy: interval ×
   ease × 1.3, ease +0.15. Again: lapse, ease −0.2 (floor 1.3), relearning step,
   then 1 day. This follows the Anki SM-2 rules. The "Good = ×1.3, ease −0.1"
   rule is removed.
4. **Elapsed time, not schedule.** Growth starts from the real time since the
   last day-level outcome: `next = max(current, round(elapsed_days × factor))`.
   An early or same-day pass never shortens a card and never compounds.
   Four passes in one Mixed round count as one. A late pass grows from the
   longer real gap. A fail always counts.
5. **History.** Every outcome stays in review history with its timestamp,
   grade and exercise type. Mark same-day step outcomes, for example a
   `learning_step` phase or an elapsed time under 1 day, so metrics and a
   future FSRS fit can tell them apart.

Grammar repetition (VIK-10) may reuse the seam. This ADR does not decide it.

## Measurement plan

Single learner, so this is a before/after comparison, not an A/B test.

- **Fix the metric first** (part of VIK-33). Retention = pass rate of the
  **first graded outcome of a card on a day**, bucketed by elapsed time since
  its previous day-level outcome: `1` = 1–6 days, `7` = 7–29, `30` = 30+.
  Exclude learning-step outcomes. This keeps the 1/7/30 keys in `/api/me/stats`
  while measuring retention.
- **Also report:** relearn success (share of lapsed words passed on their
  1-day review), graded outcomes per day (workload), and cards whose interval
  is still 1 day after 3+ passes (should drop to ~0).
- **Baseline:** compute these over existing history right before VIK-33
  ships, and record it in VIK-33. **Compare** after 4 weeks of use.
- **Targets:** day-bucket retention 85–95% (Anki's default target is 90%),
  relearn success ≥ 80%, daily workload not higher than baseline at the
  same number of active cards.

## Revisit FSRS when

At least 1,000 day-level outcomes have been recorded under these rules
(roughly 3 months), and either the 7- or 30-bucket retention stays outside
85–95% or the workload is too high. Then port FSRS-6 into the Srs domain
behind the same seam with default parameters, verify it against `py-fsrs`
test vectors, add stability and difficulty to the card, and seed them by
replaying each card's history. Fit parameters offline with the `py-fsrs`
optimizer on exported history. Learning steps and day-level outcome rules
stay. A paid or external service is an Escalate item.

## Considered options

| Option | Why not (now) |
|---|---|
| Tune SM-2-lite only | Fixes spacing but failed words still wait a day with no correct recall. SM-2 itself re-shows grades < 4 the same day. |
| FSRS-6 now | No PHP implementation. The optimizer needs a history we don't have, and today's history is distorted by the Good bug. VIK-11 is reshaping cards in parallel. |
| Day-scale learning steps (10 min → 1 d → 3 d as steps) | Day-length steps clash with FSRS ("steps of 1 day or greater are not recommended"). Here 1 d and 3 d are review intervals that FSRS can later replace. |
| Strict criterion (3 correct recalls per session) | Strongest initial learning (Rawson & Dunlosky 2011), but too heavy for a 20-activity round (VIK-29). Spaced relearning carries most of the long-term benefit. |

## Consequences

- VIK-33 depends on VIK-11. Both change `SrsService` and `IntervalCalculator`,
  and the elapsed-time and same-day rules assume one card per word.
- VIK-11's scope is unchanged. Its migration already keeps every review row
  with timestamps and grades, which the elapsed-time rule and FSRS need.
  Merged duplicate cards take the schedule of the most recently updated card.
  Rule 4 then works from the merged history.
- Live cards are not rewritten. Existing intervals stay, and the new rules
  apply from each card's next outcome.
