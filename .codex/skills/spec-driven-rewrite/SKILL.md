---
name: spec-driven-rewrite
description: Plan and deliver a large rewrite of a pre-production application through approved specifications, bounded modules, contract-first tests, and staged implementation tasks.
metadata:
  short-description: Drive a large rewrite from spec to verified tasks
---

# Spec-Driven Rewrite

Use this skill when the user wants a substantial rewrite of a pre-production
project involving backend, frontend, AI, data model, or infrastructure.

## Outcome

Produce an approved rewrite specification and dependency-ordered implementation
tasks. Do not start coding until target architecture, scope, contracts, and
acceptance criteria are clear.

## Workflow

1. Read repository instructions, project context, architecture docs, tests,
   dependencies, current structure, and git status.
2. Separate repository facts from proposed decisions and open questions.
3. Define target architecture before file-level changes.
4. Keep the application a modular monolith unless distributed services are
   explicitly chosen.
5. Define module ownership, contracts, persistence, events, jobs, permissions,
   and observability for every major capability.
6. Treat AI as a platform with provider adapters, capabilities, prompting,
   runtime, retrieval, evaluation, cost controls, and tracing. Product code
   must not depend directly on vendor SDKs.
7. Split delivery into independent tasks. Each task has one outcome, scope,
   out-of-scope boundary, acceptance criteria, tests, dependencies, and docs.
8. Use contract-first/TDD delivery: write failing tests or executable contract
   checks before implementation where practical, then integration tests for
   important cross-module flows.
9. For a pre-user rewrite, intentional API/schema breaks are acceptable, but
   require migration/reset notes and proof of intended product behavior.
10. Preserve unrelated working-tree changes and report unresolved conflicts.

## Specification requirements

Cover goals, non-goals, assumptions, success metrics, current-state evidence,
target modules, dependency rules, backend, AI, frontend, data, security,
operations, API/event contracts, testing, task order, risks, rejected options,
and decisions requiring approval.

## Validation

Before handoff verify that every capability has an owner, no task hides an
unrelated contract change, critical flows have integration tests planned, AI
has latency/token/cost/error/quality visibility, task dependencies are
acyclic, and investor-readiness has a concrete definition of done.
