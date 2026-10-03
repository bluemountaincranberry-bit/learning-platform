# Blue Rewrite Specification

## Status

Approved implementation baseline. This is the target direction for a large
pre-production rewrite delivered through bounded, verified tasks; it is not a
request to land everything in one change.

## Goal

Build a clean, readable and scalable learning platform with a modular Laravel
backend, domain-oriented Vue SPA, and an AI platform independent from vendor
SDKs. API and schema breaks are acceptable because the project has no real
users yet, but intended product behavior must remain covered by executable
checks.

## Current-state evidence

- Existing backend modules: `Ai`, `Content`, `Learning`, `Srs`, `User`.
- AI is already the largest area and contains agents, graphs, tools, prompts,
  RAG, embeddings, recommendations and tracing.
- Common models, root jobs, root services and provider wiring still exist
  outside owning modules.
- `AiContentAnalysisService` and `SentencePracticeService` are each over 500
  lines and need capability-level boundaries.
- Frontend already has domains, pages, widgets, stores and shared UI, but its
  API and state boundaries need standardization.
- Existing testing policy is integration-first with Pest feature and unit tests.
- The working tree has in-progress changes; they must be preserved and
  classified before rewrite work begins.

## Target architecture

Keep a modular monolith with explicit ownership:

```text
User | Content | Learning | Srs | Ai | Admin | Integrations | Observability
```

Each module may contain `Domain`, `Application`, `Infrastructure`,
`Interfaces`, `Routes` and its own service provider. Layers are added only
when they clarify responsibility.

Rules:

- Every capability has one owning module.
- Direct calls are for simple synchronous use cases.
- Jobs handle heavy, slow, external or retryable work.
- Events represent meaningful business facts and secondary effects.
- Cross-module calls use explicit contracts, not hidden model coupling.
- Queue jobs are owned by the module whose business workflow they execute;
  temporary compatibility wrappers may exist only during migration.
- Redis is runtime infrastructure, not the source of truth.
- Kafka is optional event distribution, not a replacement for transactions.

## AI platform

```text
Modules/Ai/
  Core/ Providers/ Prompting/ Runtime/ Capabilities/
  Retrieval/ Evaluation/ Observability/ Interfaces/
```

Capabilities include tutor, content analysis, explanations, recommendations,
exercise generation, enrichment and grading. Required contracts and behavior:

- provider-independent text, JSON, streaming, tool-calling and embeddings;
- model/provider routing by capability;
- timeout, retry, backoff, idempotency and failure classification;
- prompt versioning, approval boundaries and regression fixtures;
- explicit tool side-effect permissions;
- trace id, model, latency, token usage, cost and outcome per call;
- quality evaluation datasets and safety checks;
- rate limits and configurable budgets;
- async processing for analysis, enrichment and embeddings.

AI must not own learning progress, SRS state or content visibility.

## Frontend target

```text
resources/js/spa/
  domains/user content learning srs ai
  pages widgets shared infrastructure
```

Domains own typed API clients, composables, stores and feature models. Pages
compose features. Shared UI has no business rules. Loading, error, empty,
authorization and retry states are part of the contract.

## Delivery method

```text
specification → acceptance criteria → failing contract test
→ implementation → integration test → review → documentation
```

Task dependency graph:

```text
baseline
   ↓
backend foundation ─────┐
   ↓                    ↓
domain modules       AI platform
   └──────────────┬─────┘
                  ↓
             frontend SPA
                  ↓
        investor readiness
```

The existing in-progress working-tree changes are not part of the rewrite
baseline until their owner explicitly incorporates or discards them.

Current migration progress: the Content and User aggregates now have
module-owned canonical models with temporary legacy aliases. User-owned
learning preferences, lexeme progress, learner-state markers and grammar
progress are also canonical in the User module. ContentLexeme, Lexeme,
GrammarRule, GrammarTopic and the Lexeme catalog child models are now
canonical in the Content module, and module services now depend on canonical
Content, Lexeme and User types. The User model
preserves Sanctum, Filament and Spatie compatibility while the alias is still
referenced by legacy configuration. AI provider construction, Content YouTube queue
wiring, Content processing jobs, AI runtime/embedding/enrichment jobs, Learning
exercise processing and Content transcript translation are module-owned. SRS
card/review access is behind a module-owned persistence contract. The old
global job names remain only as compatibility wrappers for external callers
during the migration window.

## Definition of done

- Module ownership, dependencies and API/event contracts are documented.
- Critical flows have success and failure integration tests.
- AI exposes quality, latency, token, cost and error signals.
- Permissions, secrets, queues, migrations and backups have rules.
- Frontend uses stable typed contracts and consistent states.
- CI runs formatting, static checks, focused tests and build verification.
- Architecture, security, operations, onboarding and known risks are ready for
  technical due diligence.

## Out of scope

- Microservices without measured need.
- Kubernetes or Kafka for appearance only.
- Replacing AI providers before contracts and evaluations exist.
- Hypothetical optimization without product metrics.
