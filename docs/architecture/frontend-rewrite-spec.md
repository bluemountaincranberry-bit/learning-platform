# Blue frontend rewrite specification

## Status

Implemented. Domain APIs, query state, shared UI, page decomposition and
infrastructure boundaries are active; the production build and zero-baseline
frontend boundary checker pass.

## Goal

Create a readable, scalable and visually consistent Vue SPA in which domain
ownership is real rather than represented only by re-export barrels. Pages
should compose stable domain features, server state should be predictable and
cached, and reusable UI should provide consistent states and styling.

## Non-goals

- No backend API or database redesign.
- No replacement of Reka UI/Tailwind/CVA with Element Plus or another UI kit.
- No product feature expansion or visual redesign unrelated to consistency.
- No microfrontend split.
- No removal of Pinia where the state is genuinely client-owned.

## Target structure

```text
resources/js/spa/
  app/
    App.vue
    bootstrap.ts
    providers/
    router/
  domains/
    user/
      api/ model/ ui/ lib/ index.ts
    content/
      api/ model/ ui/ lib/ index.ts
    learning/
      api/ model/ ui/ lib/ index.ts
    srs/
      api/ model/ ui/ lib/ index.ts
    ai/
      api/ model/ ui/ lib/ index.ts
    admin/
      api/ model/ ui/ lib/ index.ts
  pages/
    auth/ catalog/ learning/ review/ ai/ admin/
  widgets/
    app-shell/ catalog/ trainer/ ai-builder/
  shared/
    ui/ states/ lib/ types/
  infrastructure/
    http/ query/ storage/ telemetry/
```

Names may remain lower-case to match the current Blue codebase. The invariant
is ownership and public entrypoints, not capitalization.

## Responsibilities and dependency rules

| Layer | Owns | May depend on |
| --- | --- | --- |
| `infrastructure/http` | Axios instance, auth headers, error normalization | browser/runtime primitives |
| `infrastructure/query` | QueryClient defaults, global query/mutation policies | TanStack Query, notifications |
| `domains/*/api` | endpoint calls and response mappers | HTTP client, domain types |
| `domains/*/model` | types, query keys, queries, mutations, stores, domain state machines | domain API, query client |
| `domains/*/ui` | reusable domain-aware feature components | domain model, shared UI |
| `pages/*` | route screen composition and URL state | public domain barrels, widgets, shared UI |
| `widgets/*` | cross-page feature composition | public domain barrels, shared UI |
| `shared/ui` | visual primitives and accessible interaction patterns | Vue, styling utilities only |
| `app/router` | route tree, metadata, guards | User public auth boundary |

Rules:

- Pages/widgets cannot import another domain's `api`, `model` internals or
  root legacy paths.
- API functions never update Vue refs or Pinia state directly.
- Queries return server data; mutations own cache invalidation/update policy.
- Pinia is not used as a second cache for query data.
- Shared UI has no business-specific labels, API calls or domain imports.
- URL filters/pagination are represented in route state when they should be
  shareable/bookmarkable; ephemeral form state stays local.
- Cross-domain composition happens through public domain contracts or page
  orchestration, never through deep imports.

## Server-state standard

Install one `QueryClient` through `VueQueryPlugin` in the app bootstrap.
Default policy must be explicit and documented:

- conservative retry policy for learner-facing requests;
- global error reporting with an opt-out for pages that render inline errors;
- intentional `staleTime` per resource class rather than one blind global
  value;
- no window-focus refetch for expensive AI/learning requests unless opted in;
- query keys are created by domain factories and include all result-changing
  inputs;
- mutations invalidate the relevant key family or patch the affected cached
  record; async jobs use polling/events only where the backend contract needs
  it;
- abort signals are passed to cancellable read requests where Axios supports
  them.

Initial key families:

```text
user.me / user.profile / user.preferences
content.list(filters) / content.detail(id) / content.lexemes(id)
learning.myWords(filters) / learning.progress(filters) / learning.flow
srs.due(filters) / srs.reviewHistory(filters)
ai.recommendations(filters) / ai.lesson(id) / ai.tutor(conversationId)
admin.aiBuilder.catalog() / admin.aiBuilder.graph(key)
```

## UI consistency standard

Keep the current token source in `resources/css/app.css`, but formalize it into
semantic tokens for background, surface, text, border, focus, primary,
success, warning, danger, spacing, radius and typography.

Create or standardize a small set of primitives:

- `PageShell`, `PageHeader`, `Section`, `Stack` and `Inline` for layout;
- `UiButton`, `UiInput`, `UiSelect`, `UiTextarea`, `UiCheckbox` and
  `UiField` for forms;
- `UiCard`, `UiBadge`, `UiTabs`, `UiModal` and `UiTable` for common surfaces;
- `AsyncState`, `LoadingState`, `ErrorState`, `EmptyState` and `RetryButton`
  for predictable remote-data states.

Components use CVA/`cn` variants and semantic tokens. Pages should not repeat
long ad-hoc class strings for the same semantic pattern. Accessibility
contracts (labels, focus rings, keyboard behavior, disabled/busy states) are
part of each primitive's acceptance criteria.

## Page and domain decomposition

First decomposition targets:

1. `RepetitionsPage` + `useTrainingSession`: separate session state machine,
   query adapters, trainer toolbar, card surface and summary state.
2. `ContentDetailsPage`: separate content header, readiness panel, lexeme
   list/actions, grammar panel and AI actions.
3. `GraphCanvasPage`: separate graph canvas, node palette, run controls and
   status stream adapter.
4. `PromptEditorPage`: separate prompt editor, version list, test-run panel
   and AI assistant panel.

Each extracted feature must have one role, typed props/events and focused
loading/error/empty states. The page remains the route-level coordinator.

## Acceptance criteria

- Every active domain has a public `index.ts` and no page imports root API,
  composable, store or type implementation paths.
- Query and mutation modules exist for all migrated server-state flows; each
  has a query-key factory and explicit invalidation/update behavior.
- Pinia contains only documented client-owned state.
- All route records and route names live under `app/router` or `routes` and
  authorization metadata is typed.
- Shared UI primitives provide consistent focus, disabled, loading, error and
  empty behavior across catalog, study, repetitions, AI and admin surfaces.
- The four oversized pages are decomposed without changing their backend
  contracts or user-visible critical flows.
- Strict TypeScript and production build remain green.
- Existing backend/AI feature tests remain green; frontend contract tests and
  browser E2E cover auth, catalog, study, repetitions, AI and admin access.
- Static architecture checks fail on forbidden deep imports and duplicate
  server-state ownership.
- Documentation contains the final structure, migration rules, examples and
  known exceptions.

## Delivery sequence

```text
frontend baseline and rules
        ↓
app/infrastructure + QueryClient
        ↓
shared design-system primitives
        ↓
User/Content domain state migration
        ↓
Learning/SRS domain state migration
        ↓
AI/Admin domain state migration
        ↓
oversized page decomposition
        ↓
static checks, E2E, cleanup and review
```

Every task follows `spec → failing contract check → implementation → focused
verification → review → documentation`. No task may combine a server-state
migration with an unrelated product behavior change.

## Metrics

- 100% of pages/widgets use public domain entrypoints.
- 0 duplicated API calls for the same server resource outside domain `api`.
- 0 duplicate Pinia/query sources for the same server state.
- 100% of route-level remote states expose loading/error/empty/retry behavior
  where applicable.
- No screen in the first decomposition set exceeds roughly 300 lines without
  a documented reason.
- Initial SPA build remains within 10% of the current main-entry size unless
  the added bytes are attributable to an approved feature.

## Recorded decisions

1. TanStack Query is the server-state runtime.
2. Admin remains a route/widget boundary over AI, Content and User ownership.
3. A generated API schema client remains optional future work.

## Rejected alternatives

- Big-bang rewrite of every page before introducing contracts: too hard to
  review and rollback.
- Copying s365's Element Plus/SCSS stack: visual and dependency churn without
  a boundary benefit.
- Moving all state into Pinia: incorrect ownership for remote/cache state.
- Adding a global event bus for query synchronization: less explicit than
  TanStack invalidation and domain events.
# Implementation conventions

Shared UI primitives live in `resources/js/spa/shared/ui` and are exported from
its barrel. Pages consume domain APIs through domain folders; AI Builder pages
use the shared draft-state convention and expose save, unsaved, loading, and
error states consistently.
