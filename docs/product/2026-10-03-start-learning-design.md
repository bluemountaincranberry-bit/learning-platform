# Start learning: Continue, then change on demand

Design decision for [VIK-29](https://linear.app/viktoryia/issue/VIK-29/design-start-learning-flow-continue-in-one-tap-simple).
Implementation: VIK-30. Owner: Learning; source eligibility belongs to Content,
preferences to User, outcomes to Learning/SRS. This document specifies future
behaviour; it does not describe a shipped launcher.

## Decision and evidence

Use the same launcher for content, lessons, My words and Practice. Show the
source, ready-word count and current session in three lines, then Continue.
Change opens a sheet. Today embeds that same summary and settings but its
Continue goes directly to the first card: no second launcher.

Vika has already chosen this structure and Medium's progression in
[DECISIONS.md](../../.agents/skills/product-owner/DECISIONS.md).
[example.jpg](../../example.jpg) supplies the compact challenge/focus/count
sheet reference. Keep the app's English UI, light theme and purple primary
action; the screenshot's game/hands-free split is not needed here.

The [flow inventory](2026-10-03-start-learning-inventory.md) records current
implementation evidence. [Adaptive flow](../architecture/adaptive-learning-flow.md)
currently uses weights, not a guaranteed sequence, and Balanced permits only
two appearances per lexeme per session. The ordered Medium progression below
is an explicit VIK-30 change: never advertise it as already implemented.
Canonical lexeme identity and retained source context follow
[ADR-010](../architecture/adr/ADR-010-word-keyed-repetition-and-personal-lexemes.md).
Grammar scheduling is decided in ADR-012 (VIK-10). No pronunciation-provider
decision is made here.

## Screen 1 and source contract

Above Continue, exactly three rendered lines in the default state:

1. `Adele — Someone Like You` (source title; one line, ellipsis if long).
2. `12 words ready` (eligible distinct lexemes, not exercise count).
3. `Medium · Mixed · ~15 min` (saved effective settings).

Continue is the only primary action. Below it: `Change`,
`What happens in this session?`, and, when applicable, `Repeat last session`.
The source title has its full accessible name. The back arrow has accessible
name `Back`; decorative shell icons do not add explanatory copy above Continue.
The session can use fewer words than the ready pool.

Expanded explanation appears **below** Continue, three steps:
`Meet and recognize new words` → `Write, listen, then use them` →
`Review familiar words and see your result`.
For a source with no new words use `Recall your words` →
`Practice the skill that needs work` → `See your result`.
These details may scroll; the collapsed start screen must fit.

| Entry | Source preselection and return destination |
|---|---|
| Content | That content's chosen/to-learn words, or explicit selected IDs; return to content. Never silently include the whole catalog. |
| Lesson | That lesson's saved/chosen words; return to lesson. Enable when VIK-25 supplies eligible lexemes; otherwise `Choose words to practice` links to its word list. |
| My words | Current explicit selection; without one, active personal learning words in the current language. Return to My words. |
| Practice | Current-language My words, due first; `Change source` below Continue opens a source picker. No compulsory source selection. |
| Today (VIK-28) | Due reviews → new saved items from latest lesson/chosen content, capped by daily goal → optional available grammar round. Continue directly starts this plan. |
| Grammar rule | VIK-40 owns the rule title/count and round. Reuses challenge control and feedback copy, without showing word-focus choices. |

Count only accessible, eligible items in the source and current learning
language. Explicit selections take precedence over saved defaults; changing a
default never broadens that selection. Unchosen catalog words are not added to
personal learning automatically. A source-free lexeme supports basic recall
from saved meaning/context without AI. Duplicated encounters do not inflate
the ready count. VIK-30 uses the identity contract available on its branch;
canonical cross-source consolidation is VIK-11's work, not a launcher migration.

Empty: `No words ready` + `Choose words to practice` (replaces Continue).
Loading: keep the summary skeleton; disable Continue until a real plan exists.
Unavailable source: `This source is unavailable` + `Back to My words`;
never silently switch sources. A removed last-session source uses this state.

## Change sheet

Fields, in order:

- Challenge: `Easy 1×`, `Medium 1.5×`, `Hard 2×`; Medium selected by default.
  One changing helper line: `Choose and recognize` / `Recognize, write, hear, use` /
  `Write, dictate, speak`.
- Focus: `Mixed`, `Words`, `Listening`, `Speaking`. Mixed is explicit so a user
  can return to the default. Words emphasizes vocabulary; Mixed adds listening
  and sentence use, with speaking offered through its focus rather than required.
- Round: `10`, `20`, `30` activities, default 20, estimated ~15 minutes.
  An activity is one completed exercise, not one unique word. Encounter is
  instruction and does not consume an activity slot. Hints/retries belong to
  that exercise, not extra slots. Duration is an estimate, never a cutoff.
- `Advanced` collapsed. `Save changes` applies the draft and returns to Start;
  it does not start practice. Closing by X, backdrop or Back discards the draft.

Advanced opens a separate detail view in the sheet: new words/day (default
existing effective profile cap), hints, voice, speed, reveal timing, plus
`More practice`. Include only settings supported by the selected mode. It may
scroll and has Back/Save; the **collapsed** sheet fits without scrolling.
Reveal timing never reveals a typed answer automatically. Browser voice choices
follow the source language. No microphone prompt until a speaking activity.

Save challenge/focus/count as the learner's defaults; retain source selection
only in the current plan. Repeat last session restores its configuration and
source, rebuilds today's eligible pool and never replays old outcomes. It is
absent without a previous session. Existing profile assignments remain respected:
an admin restriction disables the affected choice with an explanation on Change.
Migration of old profile selection seeds the new controls once; old profile names
are hidden behind these presets rather than deleted.

The point badges are a **proposed** VIK-30 challenge reward. Current points use
CEFR multipliers, not these challenge factors. VIK-30 must apply 1/1.5/2 server-side
exactly once to the existing activity/score/hint/CEFR calculation, recording the
effective challenge with the outcome. Do not relabel CEFR difficulty or recalculate
old ledger entries. Points never affect confidence or SRS intervals.

## Complete mapping

Profiles have one initial control mapping. Focus and challenge thereafter are
independent; profiles are implementation presets, not visible competing choices.

| Existing flow | Challenge × focus | Preserved intent |
|---|---|---|
| Balanced | Medium × Mixed | Everyday mixed progression; effective new-word cap. |
| Fast vocabulary | Easy × Words | Recognition/assisted recall; seed 10-activity short round. |
| Deep mastery | Hard × Mixed | Productive work/context; seed 30-activity longer round. |
| Listening first | Medium × Listening | Listening weighted first after essential introduction. |
| Speaking first | Medium × Speaking | Guided speech after essential introduction. |

Each existing mode has **one home** below. Shared exercise components can be
reused across combinations; the home says where the old dedicated shortcut goes,
not that the exercise becomes forbidden elsewhere.

| Mode / variant | Home or explicit disposition |
|---|---|
| Adaptive | Medium × Mixed, the default orchestrator. |
| Recall & answer: Reveal & self-grade | Easy × Words. Reveal follows an attempted recall; no automatic learned marker. |
| Recall & answer: Tap letters | Easy × Words, assisted recall. |
| Recall & answer: Choose the word (Cloze) | Easy × Words, sentence recognition. |
| Recall & answer: Type it | Hard × Words; also used for Medium's unassisted recall. |
| Cloze focused mode | Medium × Words, typed sentence gap; distinguish it from choose-word. |
| Listening cards | Easy × Listening, listen then choose/reveal and self-grade. |
| Dictation | Hard × Listening; also Hard × Mixed's listening task. |
| Context / sentence practice | Hard × Words, active sentence use; cached typed gap supports Medium. |
| Read & reveal | Easy × Words → More practice, optional sentence encounter; no scored mastery outcome. |
| Write flexible | Hard × Words → More practice, meaning-based sentence translation. |
| Write exact | Hard × Words → More practice, exact sentence translation. |
| Build the sentence | Easy × Words → More practice, sentence-token recognition/order. |
| Speaking / shadowing | Hard × Speaking (unprompted speech/retelling); its guided shadowing component also supports Easy/Medium × Speaking. |
| Transcript exercises | Medium × Listening → More practice: source-bound listening/gaps, requires timed transcript. |
| Ready check | Hard × Mixed → More practice, separate content exam with its existing readiness/result rules. |
| Learn card | Introductory encounter before the chosen combination; personal selection/Already know actions remain in the source word list. |
| Quick-check | Easy × Words; shared recognition component used within Medium. |
| Listen-recognize | Easy × Listening; shared recognition component used within Medium. |
| Legacy quick-check recommendation | Easy × Words fallback when adaptive flag is off. Does not claim mixed progression. |

No existing core practice mode is deleted. Separate mode grids/profile essays
are removed from the launcher. Transcript/context long rounds remain available
under More practice and preserve source/return context. Pronunciation tracking
and a speaking coach are VIK-45; no new paid provider is implied.

More practice keeps each existing outcome meaning: Read & reveal/Build the
sentence advance local practice only; Write flexible/exact show their existing
grading without claiming saved SRS/confidence; Ready check keeps exam results.
Show `Practice only — does not update repetition` for modes without a persisted
learning outcome. Connecting these modes to repetition is separate follow-up
work, rather than a claim that the launcher already fixes their persistence.
At session end a failed save shows `Result not saved — Retry`; completion is
confirmed only after the terminal outcome succeeds. Returning/retrying retains
the operation key so it cannot award points twice.

The following matrix makes every supported choice executable:

| Challenge | Mixed | Words | Listening | Speaking |
|---|---|---|---|---|
| Easy | Choose word, tap letters, listen/choose | Choose word, tap letters, reveal/self-grade | Hear → choose/reveal → self-grade | Hear → see phrase → guided shadow → self-grade |
| Medium | Ordered spiral below | Recognize → type → typed sentence gap | Introduce if new → hear/choose → type heard word → sentence dictation | Introduce if new → hear → guided shadow → say phrase without model → self-grade |
| Hard | Type → dictate → use sentence; speech optional via focus | Type → typed gap → produce sentence | Dictation → transcript gap when available | Speak without model → retell a short source segment; self-grade if scoring unavailable |

Focus changes activity emphasis, not the selected source or CEFR level. Cache,
audio, transcript and microphone capabilities filter this table before starting.
No audio: fall back to typed recall/available context, labelled `Audio unavailable
— using word practice` below Continue. Speaking unavailable/permission denied:
offer `Use word practice` and return to Start with an accurate summary; never
pretend speaking was assessed. Missing AI examples: reuse existing context; if
none, use recall and label the substitution on that card. Basic word review
always remains possible; generation is optional, not a Continue dependency.

## Medium × Mixed sequence

This is the default planned progression; Focus alternatives are the matrix above.
The selector supplies eligible lexemes/difficulty, prioritizes due retries and
weak skills, and chooses substitutions. The session coordinator enforces the
next progression step rather than accepting an unconstrained weighted draw.
The current two-appearance limit must be reconciled explicitly in VIK-30: count
scored exercises; allow at most four per new lexeme in this default spiral.
Persist completed evidence so a short round can continue the next stage later;
do not introduce a separate mastery state or reset existing confidence.

New word, exact order:

1. Introduction (encounter stage): source example + meaning; read/hear it, then `Ready`. Instruction,
   no SRS grade and no mastery claim.
2. Recognize: choose the target word in the example from plausible choices.
3. Recall: hide target/model; type the word from meaning/context.
4. Listen: hear the word/short source phrase without its text, then choose the
   matching meaning. Missing audio substitutes recall, with a labelled reason.
5. Use: type the target into a saved sentence gap. This is constrained sentence
   use, not a claim of free speech. No suitable example → recall fallback.

Interleave different lexemes between scored steps (at least three other
completed exercises where enough items exist); these are not four consecutive
prompts for one word. Small pools may relax spacing, never loop indefinitely.
Twenty activities is the hard round cap: finish the current exercise and end,
even if some words have not completed every step. Never pad with invented tasks.

Review, exact order:

1. Recall first, without showing the answer; no automatic encounter/recognition.
2. Then one exercise for weakest available skill: recognition → choice;
   listening → hear/choose; production → typed sentence gap; speaking → only
   when explicitly focused, otherwise another recall. Ties: production before
   listening before recognition; mastered skills need no extra task.
3. If recall failed, step 2 instead becomes assisted recognition after feedback;
   unassisted recall returns after three other activities if the round has room.
   Otherwise carry the existing retry to the next session.
4. Proceed to the next word; successful reviews do not run the whole new-word spiral.

VIK-32 owns the shared hint → retry → answer feedback component. This launcher
uses that component rather than introducing a separate attempt threshold.
The merged VIK-40 grammar design uses first wrong → hint + Try again, second
wrong → answer + explanation. Word modes must share that policy when VIK-32
implements it. These are one exercise with one terminal outcome, recording
hint usage. The same contract applies in each mode.
The coordinator must have one scheduling outcome per lexeme review episode,
with skill attempts recorded separately; do not advance the same SRS card four
times for four skills. Existing idempotency/source keys remain authoritative for
network retries. VIK-30 tests this seam with SRS, confidence and points; this
document does not prescribe a new API/schema or silently duplicate legacy writers.

Finish: `Session complete`, activities completed, words practiced, points and
existing next-review information; `Back to [source]` primary, `Practice more`
secondary. Never equate recognition success with an automatic Learned marker.

## Phone mockups and size verification

ASCII layouts below specify a 390 × 780 CSS-pixel viewport, including a 56px
app header, 64px bottom navigation and 24px bottom safe area. At 360px, side
padding stays 16px, leaving 328px content; at 390px it leaves 358px. Text is
16px/24px, source title 20px/28px, controls at least 44px tall.
The diagrams are design mockups, not browser-tested production screenshots.

Start, collapsed (three text lines above primary action):

```text
┌─────────────────────────────────────┐
│ ←                            Profile│ 56 header
│                                     │ 24 top gap
│ Adele — Someone Like You            │ 28 title, 1 line
│ 12 words ready                      │ 24 count, 1 line
│ Medium · Mixed · ~15 min             │ 24 summary, 1 line
│                                     │ 24 gap
│ [             Continue            ] │ 48
│ [              Change             ] │ 44
│ What happens in this session?     ▾ │ 44
│ Repeat last session                 │ 44 (optional)
│                                     │ flexible free space
│ Today  Lessons  Words Practice More │ 64 nav
└─────────────────────────────────────┘ 24 safe area
```

Start fixed height budget: 56 + 24 + 28 + 24 + 24 + 24 + 48 + 44 + 44 + 44 +
64 + 24 = **448px**, leaving 332px at 780px. Longer titles truncate, not wrap.
At 360px the 31-character summary fits 328px with normal 16px proportional
text; implementation must check the actual font. Expanded details/large text
may scroll vertically and must never scroll horizontally.

Change, collapsed, displayed over Start:

```text
┌─────────────────────────────────────┐
│            dimmed Start             │
├─────────────────────────────────────┤
│ Session settings                  × │ 48
│ Challenge                           │ 24
│ [Easy 1×] [Medium 1.5×] [Hard 2×]    │ 56
│ Recognize, write, hear, use          │ 24
│ Focus                               │ 24
│ [Mixed] [Words] [Listening][Speaking]│ 44
│ Activities                          │ 24
│ [ 10 ]      [ 20 ]      [ 30 ]      │ 44
│ Advanced                          › │ 44
│ [           Save changes          ] │ 48
│                                     │ 24 safe area
└─────────────────────────────────────┘
```

Sheet: 48 + 24 + 56 + 24 + 24 + 44 + 24 + 44 + 44 + 48 + 24 plus
48px vertical gaps and 32px padding = **484px**. It fits 390 × 780 and 360 × 640.
Four focus controls share 328px with 4px gaps: 79px each, enough for Speaking
at 14px; selected state uses border + check, not colour alone. Accessibility:
named radio groups, keyboard selection, modal focus trap, Escape/back dismissal,
focus returned to Change. At larger text zoom allow vertical sheet scrolling;
no fixed-height clipping. Actual layout verification belongs to VIK-30.

## Related-ticket handoff and acceptance evidence

- VIK-13 remains independent research. This provides a provisional new/review
  default and the full More practice disposition for its author to confirm;
  it does not claim VIK-13 is complete or prove a memory benefit.
- VIK-28 retains due → new → grammar order, a 10–15 minute estimated default
  and a clear end. Its Start/Continue uses this shared plan directly, preserving
  one tap from Today. Its VIK-11/VIK-13 blockers are unchanged.
- VIK-40's merged [grammar design](grammar-exercises-block.md) owns types,
  rounds and result consequences: Easy = choose/build; Hard = fill/transform/fix;
  Medium = easy → hard. Grammar defaults to 10 exercises with 5/10/15 choices,
  not the word round's 10/20/30. Share the challenge preference, remember count
  per round kind, and omit focus/point badges in grammar (points are out of its
  scope). Its Practice button remains the direct action from the rule card.
  Grammar scheduling follows VIK-10
  ([ADR-012](../architecture/adr/ADR-012-grammar-rule-repetition-schedule.md)). The sheet
  pattern and feedback are shared; grammar has its own instructional sequence.
- VIK-30 implements the plan, preference persistence, challenge points and
  fallback contracts above. Tests: every challenge × focus, each entry source,
  short pool, no AI/audio/microphone, repeat last, terminal outcome idempotency
  and exactly one SRS scheduling outcome per review episode. Check live UI at
  360/390px, long titles, text zoom, collapsed/expanded settings and safe areas.

VIK-29 acceptance: three default lines shown in Start; all five flows and all
existing modes have homes/dispositions; exact Medium new/review steps specified;
both ASCII mockups fit the stated 390px viewport by the height/width budgets;
VIK-13/28/40 contracts above are reconciled without taking over their decisions.
No runtime checks are implied by these document-level checks.
