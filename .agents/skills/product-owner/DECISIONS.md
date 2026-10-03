# Decisions log

Newest first. Status: `answered` (Vika said it) · `assumed` (PO proxy decided,
waiting for review) · `open` (needs Vika, see SKILL.md § Escalate).
Newer entries override older ones and VISION.md.

| Date | Status | Decision | Why / source |
|---|---|---|---|
| 2026-10-03 | assumed | VIK-38: content actions are **Practice these words** (whole content) and **Explain with AI**; word-status segments default to New (triage, not a filter); level/type/search start empty in a Filter sheet; skipped words are called **Hidden** everywhere. | Vika feedback on content page; [design note](../../../docs/product/content-page-mobile.md) |
| 2026-10-03 | assumed | VIK-40: grammar practice = 5 exercise types (choose, build = Easy; fill, transform, fix = Hard; Medium = easy → hard), rounds of 5/10/15 (default Medium · 10), VIK-32 feedback, result screen. Spec: `docs/product/grammar-exercises-block.md`. | Same Easy/Medium/Hard model as VIK-29; one obvious next action |
| 2026-10-03 | assumed | VIK-40: AI-generated grammar exercises are shown to learners without admin review (marked "AI", reportable, hidden on report). Generated on tap when the pool is short. | Follows VIK-31 as Vika wrote it ("generate with AI, mark as AI exercise, report a bad exercise"); 0 exercises today |
| 2026-10-03 | assumed | VIK-40: a practice result never changes "learned" by itself; ≥ 80% offers "Mark as learned". Practicing adds the rule to My grammar. | AI proposes, Vika chooses |
| 2026-10-03 | assumed | VIK-29: Medium × Mixed, 20 scored activities (~15 min); encounter is instruction, and partial word progression continues later. One shared launcher; Today starts directly from its summary. | Keep one obvious action and an honest, bounded round; [design](../../../docs/product/2026-10-03-start-learning-design.md). |
| 2026-10-03 | assumed | VIK-29: every old mode has a home; longer sentence/transcript/exam modes stay in More practice with honest outcome labels. Challenge rewards 1×/1.5×/2× are a new server-side VIK-30 factor, distinct from CEFR. | Preserve core practice while simplifying entry; design mapping and inventory. |
| 2026-10-03 | assumed | VIK-29: saved defaults do not broaden chosen words; no-AI word review stays available, audio/speech failures use explicit fallbacks. Grammar reuses challenge vocabulary; VIK-10/40 retain scheduling/round decisions. | Source ownership, progressive disclosure and basic actions without AI. |
| 2026-10-03 | answered | Agents merge their own work: no open questions + all checks green → PR and merge to `main` right away, ticket Done. Otherwise In Review. Vika reviews merged work after the fact. | Vika |
| 2026-10-03 | assumed | VIK-5: one repetition card per learner and language-aware canonical lexeme; source encounters remain separate. Unknown lesson/manual words stay private; exact shared matches are reused. | Same word from lesson and video must share memory state; [ADR-010](../../../docs/architecture/adr/ADR-010-word-keyed-repetition-and-personal-lexemes.md). |
| 2026-10-03 | assumed | VIK-5: stopping learning deactivates repetition while preserving its history and source words; restarting reuses the card. Sense-specific cards and automatic fuzzy merges are deferred. | Keep Vika’s data and the default learning path simple; ADR-010. |
| 2026-10-03 | answered | Word lists on phone: full-width compact rows, filters collapsed (no filter pre-selected), action buttons named by what they do; less scrolling to pick words. | Vika feedback on content page, VIK-38 |
| 2026-10-03 | answered | Start learning = one screen: what you learn + current flow in one line + **Continue**; details ("what happens", "Change") open on demand. Change sheet like `example.jpg` (Clozemaster): Easy / Medium / Hard (Medium = mixed: recognize → write → hear → use) + optional Focus (words/listening/speaking) + count. Old flow profiles become presets. Progressive disclosure everywhere. | Vika, VIK-29/VIK-30 |
| 2026-10-03 | assumed | AI chat is removed from the main lesson screen; notes are written in an editor. | VIK-12 |
| 2026-10-03 | assumed | TED seed default: TED talks already in the DB + 5 short popular talks (≤15 min, B1–B2); Vika edits the list. | VIK-15 |
| 2026-10-03 | answered | First language is **English**; everything stays language-aware for later languages. | Vika's vision |
| 2026-10-03 | answered | Lessons are **group English lessons** with notes, images and PDFs (not 1:1 tutoring). | Vika's vision |
| 2026-10-03 | answered | Only user is Vika: breaking API/schema changes are fine, no compatibility layers. Keep her own learning data. | Vika's vision |
| 2026-10-03 | answered | Tasks live in **Linear**; short, clear, agent-executable. | Vika's vision |
| 2026-10-03 | answered | Starter data: English TED talks from YouTube, loaded through the pipeline by a repeatable command/seeder. | Vika's vision |
| 2026-10-03 | assumed | Lesson capture: one lesson page with notes + uploads (images, PDF); AI proposes words/grammar with checkboxes; Vika confirms. The free-form AI chat is not the main input. | Vision "AI picks, I choose"; BA 2026-10-03 §4 |
| 2026-10-03 | assumed | Words from lessons that are not in the shared catalog become **personal** words (visible only to their owner) and still go to repetition. | Simplest path to the core loop; BA §4.3 |
| 2026-10-03 | assumed | Learner UI language: Russian labels for UI chrome is **not** decided — use English consistently until Vika says otherwise. | Removes RU/EN mix with least work |
| 2026-10-03 | open (VIK-10) | Grammar repetition. 2026-08-01 decision was "my grammar, no SRS"; the new vision says "return to it and repeat it". Proposal: light repetition for grammar (exercises resurface on a schedule), no full SRS cards. | Conflict between old decision and new vision |
| 2026-10-03 | open (VIK-15) | Which TED talks to seed ("ones we already learned"): use the YouTube contents already in the DB, or a new list? | Vision is ambiguous |
| 2026-08-08 | answered | AI content analysis auto-applies candidates ≥ confidence floor into the shared catalog, no confirmation click. Applies to the catalog pipeline only, not to personal lists. | Vika, EPIC 9 |
| 2026-08-01 | answered (see open item above) | "My grammar" is a personal list without SRS. | Vika |

| 2026-10-03 | assumed | VIK-19: Today links to Dashboard; Lessons and Words include their detail pages; catalog study belongs under More → Catalog. Keep the existing shell-free focused practice/exam surfaces. More reuses the accessible shared dialog and preserves AI chat role restrictions. | Ticket defines the five destinations; smallest mobile shell change without changing training flows. |
| 2026-10-03 | assumed | VIK-15 resolves the earlier open selection item: seed five short TED talks (Cutts, Sivers, Treasure, Headlee, Urban) plus the already studied TED-Ed procrastination source; B1/B2 are learning targets. Skip existing video IDs in every status without changing personal data. | Existing TED seed default and read-only local catalog inventory; editable list and operation guide in `docs/starter-content.md`. |
