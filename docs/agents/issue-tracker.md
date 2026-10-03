# Issue tracker: Linear

Tickets for this repo live in **Linear**. Agents use the Linear MCP server
(tools named `mcp__linear__*` or similar). If no Linear tool is available,
stop and tell Vika to connect it:

```bash
claude mcp add --transport http linear https://mcp.linear.app/mcp
```

then run `/mcp` to authenticate. Until then, draft tickets as local files
under `.scratch/<feature-slug>/issues/NN-<slug>.md` with the same template.

## Workspace layout

- Team: `Viktoryia` (id c3c3d887-b8f9-4504-91c8-ca799277a876).
- Projects = epics: **Lesson Notebook**, **Learning Loop**, **Mobile**,
  **Platform**. Create a project when a new epic appears (e.g. AI Platform).
- Labels: type (`feature`, `bug`, `research`, `tech`), module (`content`,
  `learning`, `srs`, `ai`, `user`, `spa`, `infra`), plus triage labels from
  `triage-labels.md`.
- Priority: Linear priority (Urgent/High/Medium/Low) = P0/P1/P2/P3.
- Blocking: use Linear's native "blocked by" relation.

## Status flow

| Status | Type | Meaning | Who moves it |
|---|---|---|---|
| Backlog | backlog | Idea, or blocked by unfinished tickets | anyone |
| Todo | unstarted | Specified and unblocked — an agent may take it (if `ready-for-agent`) | agent, when its blockers are Done |
| In Progress | started | Agent or Vika is working on it | agent on claim |
| Needs Input | started | Agent stopped with questions in a comment; waits for Vika | agent; Vika answers in a comment and moves it back to Todo |
| In Review | started | Work pushed, but something blocks auto-merge (see the comment) | agent on finish |
| Done | completed | Merged to `main` (agent auto-merge or Vika) | agent / Vika |
| Canceled / Duplicate | canceled | Not doing | Vika |

## Ticket template

Write for an agent that has the repo but not this conversation. Short and
concrete. Use the `GLOSSARY.md` vocabulary. No file paths or code unless
they encode a decision.

```markdown
## Why
One or two sentences: the user value, tied to the core loop in VISION.md.

## What to build
The end-to-end behaviour from Vika's point of view (phone first).

## Acceptance criteria
- [ ] Observable, checkable outcome
- [ ] Works on a 390px screen without horizontal scroll (learner UI)
- [ ] Tests at the agreed seam

## Decisions / assumptions
- PO proxy assumptions (link DECISIONS.md entries), or "None".

## Blocked by
- LIN-123, or "None".
```

Research tickets replace "What to build" with **Question** and **Output**
(e.g. an ADR or a short doc in `docs/`), and their acceptance criterion is
"a decision is recorded".

## When a skill says "publish to the issue tracker"

Create one Linear issue per ticket in dependency order (blockers first), in
the matching project, with labels and priority. Apply `ready-for-agent`
unless the ticket needs Vika (then `ready-for-human`).

## When a skill says "fetch the relevant ticket"

Read the Linear issue with its description and comments.
