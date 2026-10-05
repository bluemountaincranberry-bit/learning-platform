# Grammar repetition research (VIK-10)

Question: what is the simplest effective way to schedule grammar practice so
rules in My grammar are remembered? Vika decided grammar **is** repeated
(comment on VIK-10, 2026-10-03). This note decides **how**.
Decision: [ADR-012](adr/ADR-012-grammar-rule-repetition-schedule.md).

## What exists (main, 2026-10-04)

- **My grammar** = `user_grammar_rules` (status `learning` / `learned`,
  `confidence_calculated`). No schedule columns.
- **Practice rounds** (VIK-31, In Review): a finished round writes one
  `grammar_exam_attempts` row (`type = practice`, `score_pct`, per-item
  outcomes) and raises `GrammarPracticeCompleted`. Exercises are picked
  never seen → missed last time → seen longest ago, and the AI pool is topped up,
  so a repeated round on a rule rarely shows the same items.
- **Content grammar exams**: `pre` and `post` attempts from a content page,
  same table.
- **Word scheduler**: SM-2 family in the Srs module. ADR-011 (VIK-14) fixes
  its growth rules and adds learning steps (implementation VIK-33). It says
  "Grammar repetition (VIK-10) may reuse the seam".
- **Today** (VIK-28, VIK-47, not built): a "rule of the day" card that starts
  a short round.

## Sources

| Source | Claim used here |
|---|---|
| [Kim & Webb 2022, *Language Learning* 72(1)](https://onlinelibrary.wiley.com/doi/abs/10.1111/lang.12479) | Meta-analysis, 48 experiments, N = 3,411: spacing has a medium-to-large effect on L2 learning. "Shorter spacing was as effective as longer spacing in immediate posttests but was less effective in delayed posttests". "Equal and expanding spacing were statistically equivalent." |
| [Serrano 2022, *SSLLT* 12(3), state-of-the-art review](https://files.eric.ed.gov/fulltext/EJ1365275.pdf) | Grammar: Bird (2010), 3- vs 14-day lags, no difference at 7 days, "the longer lag proved more helpful for long-term retention after 60 days"; Rogers (2015), 2.25 vs 7 days, advantage for longer. Productive grammar: "no differences between lags (Serfaty & Serrano, 2022) or an advantage to shorter lags (Suzuki, 2017; Suzuki & DeKeyser, 2017a)". Implication: "for the proceduralization of grammar rules, shorter lags might be more beneficial". Interleaved grammar practice "promote[s] better long-term results than blocked practice"; teachers should contrast structures (e.g. past simple vs present perfect) in one session. |
| [Serfaty & Serrano 2022, *Applied Psycholinguistics*](https://www.cambridge.org/core/journals/applied-psycholinguistics/article/lag-effects-in-grammar-learning-a-desirable-difficulties-perspective/A9E9F6888901DBF1F2D7B52EC084C010) | 1-day vs 7-day lags, tests at 7 and 28 days: short lags helped at 7 days, long lags at 28 days; long lags helped faster / more proficient learners, short lags slower / less proficient ones. |
| [Suzuki & DeKeyser 2017, *Language Teaching Research* 21(2)](https://eric.ed.gov/?id=EJ1132625) | For proceduralization of morphology, "massed practice led to accurate utterances to the same extent as distributed practice". Early practice need not be widely spaced. |
| [ADR-011](adr/ADR-011-repetition-scheduler-learning-steps-before-fsrs.md) and its [research note](repetition-algorithm-research.md) | Anki SM-2 rules (Again / Good / Easy, ease 2.5, floor 1.3), 1 → 3 day graduation, elapsed-time growth, one day-level outcome per day, FSRS deferred until enough clean history. |

## What the evidence says for this app

1. **Repeat the rule, not the item.** All studies space *practice of a
   structure* with varied sentences. Our exercise pool already gives fresh
   items per round, so the scheduled unit is the rule. Scheduling single
   exercises would train answers to known sentences.
2. **Start short, then expand.** Short early lags (1–3 days) help productive
   use and weaker learners; longer lags win for retention after weeks. An
   expanding schedule is no worse than an equal one. A 1 → 3 day start
   followed by multiplicative growth fits both.
3. **The schedule must keep growing.** Bird's 60-day advantage for 14-day lags
   means a ladder that stops at 14 days under-serves long-term memory.
   A cap keeps old rules visible (90 days).
4. **Mix rules when several are due.** Interleaving beats blocked practice for
   grammar. A mixed round over due rules is the natural next step after
   VIK-47 (follow-up idea, not in this decision).

## Options compared

| Option | Verdict |
|---|---|
| **A. Fixed ladder 1 → 3 → 7 → 14 days** (VIK-40 draft) | Simple, but growth stops at 14 days and it is a second algorithm next to the word scheduler. |
| **B. Rule-level schedule on the shared SM-2 rules (ADR-011)**, state on the My grammar row, one rating per round from its score | **Chosen.** Same rules and future FSRS swap as words. Grows past 14 days. Uses data VIK-31 already writes. |
| C. One SRS card per exercise | Trains memorized sentences. AI pools churn, and 15+ cards per rule would flood the queue. |
| D. Grammar cards inside `srs_cards` | Clashes with VIK-11's word-keyed cards (lexeme FK) and the word Review UI, and word due counts would include rules. The rules can be shared without sharing the table. |
| E. FSRS now | Deferred for the same reasons as words (ADR-011). |
| F. No schedule, pick the lowest confidence | No time dimension: strong rules never come back, and forgetting stays invisible. |
