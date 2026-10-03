---
name: ai-orchestration
description: Use when the user asks to use the local AI orchestration process, research-first flow, proposal-first flow, multi-agent delivery, Vika/project profiles, or wants an agent workflow that can later be adapted to Codex or Claude.
---

# AI Orchestration

Use this skill as the entry point for the local orchestration system in `.ai-orchestration/`.

Read first:

- `.ai-orchestration/README.md`
- `.ai-orchestration/QUICK_START.md`
- `.ai-orchestration/profiles/vika.md`
- `.ai-orchestration/profiles/project.md`
- `.ai-orchestration/project-docs/project-overview.md`
- `.ai-orchestration/core/decision-gates.md`

Then choose the workflow:

- Full task lifecycle: `.ai-orchestration/workflows/task-lifecycle.md`
- Research-first idea or large task: `.ai-orchestration/workflows/research-to-delivery.md`
- Concrete implementation task: `.ai-orchestration/workflows/multi-agent-delivery.md`

Read role files only when needed:

- `.ai-orchestration/agents/research-agent.md`
- `.ai-orchestration/agents/task-planner.md`
- `.ai-orchestration/agents/solution-designer.md`
- `.ai-orchestration/agents/implementation-agent.md`
- `.ai-orchestration/agents/test-agent.md`
- `.ai-orchestration/agents/review-agent.md`
- `.ai-orchestration/agents/documentation-agent.md`

Rules:

1. Keep the main agent as orchestrator.
2. Use short context packets for specialist roles.
3. Do not pass the whole chat to a specialist unless there is a clear reason.
4. For ideas, new features, architecture, AI, or cloud direction, start with research and ask Vika for the next step.
5. For small local changes, implement directly and report verification.
6. For large work, create Russian markdown tasks in `.ai-orchestration/local-tasks/` after Vika approves.
7. Keep responses concise and in Russian unless the user asks otherwise.
8. Suggest modern technologies only when they add product, architecture, AI/cloud, observability, or learning value.
9. After completed implementation, delete the finished local task and update short project documentation when useful.
10. Use `.ai-orchestration/templates/local-task-template.md` for local task files.
