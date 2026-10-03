# Rewrite completion review

## Review status

Reviewed against `docs/architecture/rewrite-spec.md` and the OpenSpec change.
The modular refactor is complete. Production launch approval remains a
separate operational sign-off.

## Phase evidence

| Phase | Review evidence |
| --- | --- |
| Baseline | Ownership map, ADR-007/008/009, preserved dirty worktree, and baseline quality evidence are documented. |
| Backend foundation | Canonical module models, module-owned jobs/integrations, explicit owner contracts, zero compatibility aliases and zero PHP boundary violations. |
| AI platform | Capability contracts, provider factory, OpenAI/Ollama embeddings routing, agent/graph/tool permissions, evaluation fixtures and trace/cost observability are covered by focused and integration tests. |
| Domain modules | Content → Learning → SRS → User ownership and critical failure/idempotency flows are covered by feature tests and canonical persistence boundaries. |
| Frontend SPA | Domain entrypoints, lazy routes, auth guards, typed checks, production build, and the seeded Compose Playwright smoke are verified. |
| Investor readiness | CI gates, dependency audits, health endpoint, restore runbook, technical due-diligence package, risk register, and production sign-off checklist are present. |

## Invariant checks

- No runtime compatibility models, jobs or services remain at the application
  root; canonical module namespaces are used directly.
- Pages and widgets do not import root API/store/composable paths directly;
  admin AI builder pages use the domain-owned builder API explicitly.
- AI does not own learner progress, SRS state, or content visibility.
- Postgres remains durable state; Redis is runtime/cache infrastructure.
- `git diff --check` and Compose configuration validation pass.

## Verification record

- Focused final regression: 88 tests, 305 assertions; provider/repository/morph
  verification: 27 tests, 64 assertions; PHP boundaries: 0 violations across
  470 module files.
- Full Laravel suite: 1003 passed, 13 failed, 3045 assertions. Every failure is
  an Elasticsearch integration timeout (`cURL error 28` against
  `http://elasticsearch:9200`); no application assertion failed.
- RBAC compatibility regression: 7 tests, 21 assertions.
- AI evaluation/observability focused suite: 42 tests, 128 assertions.
- Frontend: `vue-tsc --noEmit`, project typecheck mode, and production Vite
  build pass.
- Browser: `docker/playwright/e2e/rewrite-smoke.mjs` passes 8 flows, including
  Prompt/Graph Builder draft save and unsaved-state checks.
- Composer lockfile audit and production npm audit report no vulnerabilities.
- Local `/health` reports database readiness and all Compose services are up.

## OpenSpec requirement traceability

| Spec | Implementation and review evidence |
| --- | --- |
| `module-boundaries` | Strict PHP checker reports zero private cross-module imports and zero cycles; provider, repository, morph, ownership and atomic review feature tests pass. |
| `naming-conventions` | PHP/frontend/naming self-tests and API mapping fixtures preserve `lexemeId`, `contentLexemeId`, review and exercise-attempt meanings across PHP, JSON and TypeScript. |
| `frontend-domains` | Frontend boundary checker, typecheck, production build and 8-flow Playwright smoke verify domain entrypoints, request/user isolation, shared async states and keyboard-capable dialogs. |
| `ai-execution` | Capability/provider contract tests, candidate validation and stale-run guards, tool authorization/idempotency tests, evaluation fixtures, trace/cost tests and secret sanitization cover validated outcomes and observable failures. |
| `business-events` | Transaction rollback/commit, outbox retry, consumer deduplication, stale-version and review idempotency tests cover reliable publication and repeated/out-of-order delivery. |

## Operational sign-offs

These are remaining implementation or operational decisions:

1. Select production backup storage/retention and record measured RPO/RTO.
2. Run the restore drill in the target hosting environment.
3. Decide whether to remediate development-tool advisories before launch.
