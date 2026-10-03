# Grammar exercises block (VIK-40)

Design for practicing one grammar rule. Implementation: VIK-31.
Mockup (open in a browser, phone width): [mockups/grammar-exercises-block.html](mockups/grammar-exercises-block.html).

Related: VIK-29 (start-learning flow, Easy/Medium/Hard), VIK-32 (hint → retry →
answer), VIK-10 (grammar repetition,
[ADR-012](../architecture/adr/ADR-012-grammar-rule-repetition-schedule.md)),
VIK-26 (personal rules).

## Today

- `grammar_rule_exercises` holds `cloze` and `multiple_choice`, created by an
  admin through `AiGrammarExerciseService` as `draft`; learners see only
  `published`. There are 0 rows, so every rule page says "No exercises yet".
- The rule page lists every exercise inline with "Reveal answer". No round, no
  result, nothing is saved.
- `grammar_exam_attempts` (pre/post warm-up from a content page) and
  `GrammarConfidenceService` already turn per-rule scores into
  `user_grammar_rules.confidence_calculated`.

## Exercise types

One rule, five types. Level follows VIK-29: **Easy** = recognize,
**Hard** = produce, **Medium** = a round that starts easy and ends hard.

| Type (`type`) | Level | What you do | Example (Present Perfect) | Checked by |
|---|---|---|---|---|
| Choose the form (`multiple_choice`, exists) | Easy | Tap 1 of 3–4 options for the gap | I ___ this film twice. → *have seen* · saw · have saw · seen | `answer_index` |
| Build the sentence (`build`) | Easy | Tap word tiles in order | *ever / you / Have / been / to Japan* → Have you ever been to Japan? | exact order of `tiles` |
| Fill the gap (`cloze`, exists) | Hard | Type the missing form; base verb shown | She ___ (lose) her keys. → *has lost* | `answer` + `accepted_answers` |
| Transform (`transform`) | Hard | Rewrite into negative / question / other form | They have finished. → **Question:** *Have they finished?* | `answer` + `accepted_answers` |
| Fix the mistake (`fix`) | Hard | Type the corrected sentence | I have seen him yesterday. → *I saw him yesterday.* | `answer` + `accepted_answers` |

Answer matching for typed answers: trim, case-insensitive, ignore final
punctuation, contractions equal to full forms (`haven't` = `have not`), and any
string from `accepted_answers`. No AI grading in a round, so practice works with
the AI provider down once exercises exist.

## Round flow

**Entry → Start card → N exercises → Result.** Same pattern as VIK-29:
one tap to start, details on demand.

1. **Start card** (the exercises block on the rule page, also used from other
   entry points). Three lines max above the button:
   - `Practice this rule`
   - `Medium · 10 exercises · ~4 min`
   - last result if any: `Last time 7/10 · 2 days ago`
   - Button **Practice**. Link **Change** opens the VIK-29 sheet with
     Level (Easy / Medium / Hard) and Count (5 · 10 · 15). Default Medium · 10.
     The choice is remembered per learner (same setting as VIK-29).
2. **Exercise screen** (full screen, one exercise at a time): close ×,
   progress `3 / 10`, rule title as a small link that opens the rule in a
   bottom sheet, prompt, input or options, primary button in the thumb zone.
   Menu `⋯` → **Report a bad exercise**.
3. **Feedback** (VIK-32 contract, same for every type):
   - correct on first try → green, explanation one line, **Next**;
   - first wrong → **hint** (never the answer; for choose-the-form the wrong
     option is struck out) and **Try again**;
   - second wrong (or **Show answer**) → correct answer, one-sentence
     explanation, link **See rule** (bottom sheet, scrolled to examples),
     **Next**.
4. **Result screen**: score, list of mistakes with the right answer, what
   changed for the rule, actions **Practice mistakes** (only missed exercises,
   new round) and **Done** (back where the round started). When the score is
   ≥ 80% on Medium or Hard, also offer **Mark as learned** (Vika decides; no
   automatic status change).

Order inside a Medium round of 10: 2 choose → 2 build → 2 fill → 2 transform →
2 fix. Easy round: choose and build only. Hard round: fill, transform, fix.
If a type has too few exercises, take the next type at the same level.

## Where exercises come from

- **Pool per rule** in `grammar_rule_exercises`. Learners see `published`
  exercises plus AI exercises that are not reported (`origin = ai`, shown with
  a small "AI" badge). Admin review is no longer required for learners; admins
  can still edit/archive.
- **Generate on demand**: tapping **Practice** on a rule whose pool cannot fill
  the round queues generation (existing `AiGrammarExerciseService`, extended
  with the new types, `hint`, `accepted_answers`, `tiles`). First batch = 15
  (3 per type). The screen shows "Preparing exercises…" and starts the round
  as soon as ≥ 5 exist; it polls the generation status.
- **Top-up**: after a round, if fewer than 10 unseen exercises remain for this
  learner, queue another batch of 10 in the background. The prompt receives
  the existing prompts "do not repeat these".
- **Limits**: generation is a queued job, one running job per rule, max 3
  batches per rule per learner per day (VIK-31 "rate-limited"). AI down → the
  round uses whatever exists; with nothing there, the block says "Exercises
  can't be prepared right now" with **Try again**. Explanation, examples and
  "Mark as learned" keep working.
- **Avoiding repeats**: pick order = never seen → seen and missed last time →
  seen longest ago. Never the same exercise twice in a round. At generation,
  drop exercises whose normalized prompt already exists for the rule.
- **Report a bad exercise**: hides it for this learner at once, the round
  replaces it with the next one, the report is visible to admins
  (`grammar_exercise_reports`). Three reports or one admin archive → hidden
  for everyone.
- **Personal rules** (VIK-26) use the same pool and flow; their exercises are
  visible only to the owner, like the rule itself.

## What a result produces

One append-only attempt row per finished round. Reuse `grammar_exam_attempts`
with a new `type = practice` (it already has per-rule `items`, `score_pct` and
is read by `GrammarConfidenceService`); `content_id` is set when the round
started from a lesson/content page. Closing a round early saves nothing.

`items` per exercise:

```json
{
  "exercise_id": 412,
  "type": "transform",
  "level": "hard",
  "outcome": "first_try | after_hint | answer_shown | reported",
  "attempts": 2,
  "given": "Have they finish?",
  "ms": 9400
}
```

`score_pct` = (first_try × 1 + after_hint × 0.5) / scored items × 100;
reported items are not scored. `level` of the round and `count` are stored in
the row as well.

Effects after the row is saved (event `GrammarPracticeCompleted` in the
Learning module; listeners do the secondary work):

- rule added to **My grammar** as `learning` if it was not there;
- `GrammarConfidenceService` recalculates `confidence_calculated`; My grammar
  and the rule page show it as "Practice says 72%";
- "last practiced" + last score shown on the start card and in My grammar;
- **grammar review** (VIK-10, [ADR-012](../architecture/adr/ADR-012-grammar-rule-repetition-schedule.md)):
  the round's score becomes one rating that moves `next_practice_at` on the
  My grammar row (Again / Hold / Got it / Easy by score, thresholds in
  ADR-012 rule 4). Intervals start at 1 → 3 days and grow up to 90 days. Only the first finished round
  per rule per day with ≥ 3 scored items counts. Practice mistakes and `pre`
  exams don't count. Practicing early never shortens the schedule. The result
  screen shows the next date ("Next practice in 3 days").

## Entry points

All open the same start card → round → result; **Done** returns to where it
started.

| Where | What it shows |
|---|---|
| Rule page | The exercises block replaces "No exercises yet": start card + **Practice**. On phone a sticky **Practice** button stays at the bottom while reading. |
| My grammar | Each row: rule, confidence, last practiced, "Due today" / "Next practice in N days", a **Practice** button. Due rules first. |
| Today | "Rule of the day" card, Medium · 5: the most overdue due rule (at most one new rule per day), else "Extra practice" on the lowest-confidence learning rule. "N more rules due" after the round. Not shown when My grammar is empty. Rules: ADR-012 § Today. |
| Lesson / content page grammar block | Each rule row links to the rule page and has **Practice**. Mixed rounds over several rules are out of scope for VIK-31. |

## Implementation (VIK-31)

- **Where it lives.** Content owns the exercise pool, answer checking
  (`GrammarAnswerChecker`), reports and AI batch bookkeeping
  (`grammar_exercise_generations`). Learning owns the round
  (`GrammarPracticeService`, `GrammarRoundComposer`) and the event
  `GrammarPracticeCompleted` with its listeners (add to My grammar +
  confidence, background top-up). AI owns `GenerateGrammarExercisesJob`.
- **API** (auth): `GET /api/grammar-rules/{id}/practice` (start card),
  `POST …/practice/rounds` (200 ready · 202 preparing · 503 unavailable),
  `POST /api/grammar-exercises/{id}/check` (attempt 1|2, `show_answer`),
  `POST /api/grammar-exercises/{id}/report` (returns the replacement),
  `POST /api/grammar-rules/{id}/practice/complete`. The old
  `GET /api/grammar-rules/{id}/exercises` (answers in the payload) is gone.
- **Exercise fields:** `instruction` (task line, e.g. "Make it a question"),
  `hint`, `accepted_answers`, `tiles`, `origin`, `dedup_key` (normalized prompt; normalized answer for build, whose prompts are generic).
  A generated hint that contains the answer is dropped; the round then shows
  a generic hint.
- **Limits** (`config/ai.php` → `exercises.practice`): first batch 15, top-up
  10 when fewer than 10 unseen remain, round starts at 5, 3 batches per rule
  per learner per day, one active batch per rule.
- **SPA:** start card on the rule page (+ sticky Practice on phones), round
  page `/grammar/:id/practice` (full screen), Practice on My grammar rows.
  Component tests: `npm test` (vitest).
- **Not yet:** personal rules (waits for VIK-26), `next_practice_at`
  (waits for VIK-10), Today and lesson entry points (VIK-47).

## Out of scope

Speaking or listening grammar exercises, AI-graded free writing, mixed
multi-rule rounds, points/streaks.
