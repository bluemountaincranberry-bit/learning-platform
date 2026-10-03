# Technical due diligence package

## Current architecture

Blue is a Laravel modular monolith with Vue 3 SPA, Postgres, Redis and
Horizon. Business ownership is split between User, Content, Learning, Srs,
Ai and Admin modules. AI provider construction and capability boundaries are
explicit and vendor-independent; AI does not own learning or SRS state.

The rewrite target and dependency order are documented in
[`rewrite-spec.md`](../architecture/rewrite-spec.md). Key decisions are in
[`ADR-007`](../architecture/adr/ADR-007-modular-monolith-rewrite.md),
[`ADR-008`](../architecture/adr/ADR-008-ai-platform-boundary.md) and
[`ADR-009`](../architecture/adr/ADR-009-contract-first-rewrite-testing.md).
The phase-by-phase review and verification record is in
[`rewrite-completion-review.md`](../architecture/rewrite-completion-review.md).

## Quality gates

The repository CI workflow is `.github/workflows/ci.yml` and runs:

- Laravel Pint formatting checks for rewrite-owned module boundaries (the
  repository-wide legacy baseline currently has 134 pre-existing findings);
- the complete Pest backend suite;
- reproducible `vue-tsc --noEmit` typecheck;
- the production Vite build;
- blocking production JavaScript and Composer dependency audits;
- a seeded Compose Playwright smoke for the critical SPA boundaries.

Local equivalents are `make test`, `docker run ... npx vue-tsc --noEmit` and
`docker run ... npm run build` from `src/`.

The current local evidence is:

- backend: 990 passing tests, 2,973 assertions;
- frontend: `vue-tsc --noEmit` passes;
- frontend production build passes; route-level lazy loading keeps the main
  JavaScript entry at about 164 kB (gzip about 60 kB), with only non-blocking
  Browserslist/PURE-comment warnings; current pages and widgets consume domain
  entrypoints.
- production JavaScript audit and Composer lockfile audit currently report `0`
  vulnerabilities; the full development JavaScript tree still reports 7
  dev-tool advisories requiring a dedicated compatibility pass.
- frontend E2E smoke is reproducible through
  `docker/playwright/e2e/rewrite-smoke.mjs` and covers guest auth redirect,
  login, catalog/detail/study navigation, repetitions and AI role boundaries;
  it requires the local Compose stack and seeded demo accounts.

## Operational boundaries

- Secrets are supplied through environment/config files and are not committed.
- Redis is runtime/cache infrastructure; Postgres remains the durable source
  of truth.
- Horizon runs as a dedicated Compose service and queued workflows have
  explicit retry/timeout settings.
- AI calls expose trace, model, usage, cost and error metadata where tracing is
  enabled; evaluation fixtures live under `src/tests/Fixtures/Evals/`.
- Sentence practice generation and answer grading are separate AI capability
  contracts behind the application facade, so provider changes do not leak
  into learning orchestration.
- AI background work has explicit module-owned jobs for agent turns, content
  and lesson analysis, graph fan-out/resume, embeddings and lexeme enrichment;
  deprecated root job names are temporary compatibility entrypoints.
- SRS card/review persistence is accessed through a module-owned repository
  contract; card lookup and review are scoped by acting user, while the
  existing schema and API remain compatible during migration.
- The Content aggregate is now owned by `Modules/Content/Domain/Models`; the
  old `App\\Models\\Content` class is only a temporary compatibility alias.
- The User aggregate is now owned by `Modules/User/Domain/Models`; the old
  `App\\Models\\User` class is only a temporary compatibility alias. Sanctum,
  Filament and Spatie guard/morph compatibility are explicitly covered while
  legacy configuration is being migrated.
- User-owned learning preferences follow the same module boundary, with the
  legacy model retained only as a compatibility alias for older callers.
- User-owned lexeme progress follows the same boundary and is covered by
  Learning, Content and student-AI regression tests; the legacy model remains
  only as a compatibility alias.
- User-owned skip, confidence and self-check context markers now follow the
  same User boundary, with compatibility aliases retained during migration.
- User-owned grammar progress now follows the User boundary as well, with
  grammar readiness, progress and AI context consumers using the canonical
  module model.
- Module services use the canonical User type; the legacy User class remains
  only for authentication/provider and persisted morph compatibility during
  the migration window.
- The ContentLexeme aggregate is canonical in the Content module; its legacy
  model remains only as a compatibility alias while external callers migrate.
- The canonical Lexeme vocabulary aggregate is also owned by the Content
  module; vocabulary, AI, Learning and SRS consumers use its module type.
- AI analysis runs, candidates, agent conversations/messages, graph runs,
  branch results and trace/span persistence are canonical AI models; legacy
  root classes remain temporary compatibility aliases.
- Lexeme examples, translations, senses and associations are canonical child
  models in the Content module, with legacy aliases retained for migration.
- GrammarRule and GrammarTopic are canonical Content catalog aggregates, with
  published/draft, revision, progress and AI consumers covered by regression
  tests.
- `/health` provides an unauthenticated database readiness signal for a load
  balancer or container supervisor; queue, Redis and AI dependencies remain
  separately observable.
- Learner API routes require Sanctum authentication; learner-owned resources
  use ownership checks, admin APIs use role/ability gates, and content-scoped
  learning/AI operations reject non-public content without disclosing its
  existence.
- A local PostgreSQL backup/restore drill has been completed and is documented
  in [`backup-restore-runbook.md`](backup-restore-runbook.md). No production
  backup availability or RPO/RTO is claimed until hosting and retention are
  selected.

## Proposed pre-launch measures

These are initial targets to validate in staging and revise from real traffic;
they are not production claims yet:

- API availability: 99.5% monthly for learner-facing HTTP requests, excluding
  planned maintenance.
- API latency: p95 under 500 ms for synchronous non-AI endpoints; AI request
  latency is measured separately by capability and provider.
- Queue reliability: at least 99% of background jobs complete without a final
  failure, with retry counts and age of the oldest pending job visible.
- AI quality: every evaluation suite records pass rate, and every provider call
  records latency, tokens, estimated cost, model and error outcome.
- AI cost: report cost per completed learner AI action and per content-analysis
  run; set budget alerts before enabling production traffic.
- Recovery: validate the selected production RPO/RTO against a scheduled
  restore drill; current local evidence does not establish production values.

## Open risks

1. Configure production backup storage and document retention/RPO/RTO values;
   the local restore drill is complete.
2. The full development JavaScript tree still has 7 advisory records in build
   tooling; the current registry offers no lockfile-only remediation, so a
   compatibility upgrade pass is still required before treating dev tooling as
   fully clean.
3. Measure real navigation usage and tune route prefetching; the initial SPA
   bundle is now split at route boundaries.
4. Reduce the repository-wide legacy Pint baseline (134 findings); CI currently
   gates the rewrite-owned module boundaries separately.
5. Run a clean-checkout CI run in the target hosting environment and configure
   production backup retention/RPO/RTO before launch.
