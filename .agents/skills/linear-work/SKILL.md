---
name: linear-work
description: Run Linear tickets end to end for this repo — pick, claim, spec, implement, test, report, move status. Use for "/linear-work VIK-42", "/linear-work next", "/linear-work status", or "where did we stop / what is left".
---

# Linear work loop

One session = one ticket. Team `Viktoryia` (key `VIK`), tracker conventions and the status flow in
`docs/agents/issue-tracker.md`. Product questions go to the `product-owner`
skill first.

## Modes

- `status` → go to § Status. No code changes.
- `next` (or no argument) → pick the **frontier** ticket: status Todo, label
  `ready-for-agent`, every blocker Done; highest priority, then lowest number.
  Show it in one line and continue with § Run.
- `VIK-N` → § Run on that ticket.

## Status

First, move every Backlog ticket labeled `ready-for-agent` whose blockers are
all Done to Todo. Then report in a short table: In Progress, In Review, Needs Input tickets (with the
open question from the last comment), and the next 3 frontier tickets. Add
`git worktree list` and local `vik-*` branches with unmerged commits. End with
one recommendation: what to run next and which tickets can run in parallel
(no shared blockers, different modules).

## Skill routing

Pick the working skill by the ticket's type label, and say which one in the
"Agent started" comment:

| Ticket | Skill |
|---|---|
| Bug | `diagnosing-bugs`, then `tdd` for the fix |
| Feature, Improvement, Tech | `tdd` |
| Research | `research`; an ADR or glossary outcome → `domain-modeling` |
| "Design:" ticket with UI | `prototype` |
| Unclear scope | `product-owner`; still unclear → step 5 questions |

Before step 9, run `code-review` on the ticket branch and fix what it finds.

## Run

1. **Load.** Read the ticket, its comments, blockers and related tickets. If a
   blocker is not Done → stop and say which one.
2. **Workspace.** Work on the ticket's own branch. If this session is in a git
   worktree, rename its branch to the ticket's `gitBranchName`; otherwise
   create and switch to it. Never work on `main`.
3. **Claim.** Status → In Progress, assignee → me, comment "Agent started".
4. **Understand.** Read `PROJECT_CONTEXT.md`, `docs/agents/domain.md`, the
   ADRs and docs of the modules the ticket touches. Think the ticket through:
   every user scenario, edge case and the product's next step. Gaps that fit
   the ticket join its scope; larger ones become new tickets in step 9.
5. **Questions.** Answer every product question with `product-owner` and log
   `assumed` decisions. Only for its § Escalate items, or a choice that would
   waste days if wrong: post one Linear comment with numbered questions and a
   recommended answer for each, status → Needs Input, and stop. That is a valid end of the session.
6. **Design / research tickets** (label Research, or a "design pass" step in
   the ticket): write the doc/ADR/mockup the ticket asks for, commit it, and
   finish via step 9. For tickets that state follow-up updates (e.g. "update
   VIK-31 scope"), edit those tickets.
7. **Implementation tickets:** vertical steps, test-first at the seams the
   ticket names (`tdd` skill), to the standards in
   `engineering/coding-standards.md`. Build for growth: new modules,
   abstractions, events, API and schema changes are welcome when they serve the
   product; record significant choices as ADRs (`domain-modeling`).
   Mobile UI: check 360/390px, no horizontal scroll.
8. **Verify** with the tests that cover the change and the flows it touches:
   - in the main checkout: `make test ARGS="--filter=…"`, `make npm ARGS="run build"`;
   - in a worktree: `make wt-test ARGS="--filter=…"`, `make wt-build`;
   - queue/job/provider code changed → `make workers-restart` before manual checks.
   Every acceptance criterion is checked or explicitly marked not verified.
9. **Finish.**
   - Commit on the ticket branch: `VIK-N: <what changed>` + attribution trailer.
   - Push the ticket branch (`git push -u origin <branch>`); open a PR with `gh`
     if it is installed, body via the `pr` skill. `main` belongs to Vika: she
     merges after review.
   - Linear comment (the report): what was done, files/areas changed, checks run
     with results, docs updated, assumptions made (link DECISIONS.md), risks,
     how Vika can try it (URL/steps on the phone).
   - Tick the acceptance criteria that are verified in the description.
   - Status → In Review. New work discovered → new tickets (template from the
     tracker doc), linked as related.
10. Reply in chat with the ticket link and a 3-line summary.

## Parallel sessions

Each parallel ticket runs in its own worktree session started by Vika:
`claude -w vik-42 -n VIK-42 "/linear-work VIK-42"`. Two sessions never take
tickets that touch the same files or migrations; `status` says which are safe.
