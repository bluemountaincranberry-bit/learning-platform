# Frontend architecture research: Blue vs s365_Frontend

## Decision being researched

Как превратить текущий Vue SPA Blue в предсказуемую domain-oriented систему с
единым server-state подходом, reusable UI и понятным разделением
`domains`, `pages`, `routes` и shared infrastructure.

## Sources and method

Исследованы локальные исходники:

- Blue: `src/resources/js/spa`, `src/resources/css/app.css`, `src/package.json`.
- соседний проект: `/home/user/Projects/365/s365_Frontend/app/Views/assets`,
  его `package.json`, `router.js`, `serverState/queryClient.ts`, domain slices
  и shared UI/styles.
- актуальная официальная документация TanStack Vue Query по [query keys](https://tanstack.com/query/latest/docs/framework/vue/guides/query-keys),
  [query functions](https://tanstack.com/query/latest/docs/framework/vue/guides/query-functions),
  [queries/mutations](https://tanstack.com/query/latest/docs/framework/vue/quick-start)
  и [default behavior](https://tanstack.com/query/latest/docs/framework/vue/guides/important-defaults).

## Facts: current Blue

- Blue uses Vue 3, strict TypeScript, Pinia, Vue Router 5, Vite 7,
  Tailwind-style utility classes, Reka UI and class-variance-authority.
- Current SPA inventory is approximately 21 API modules, 16 composables, 2
  Pinia stores, 41 type files, 31 pages, 10 widgets and 20 shared UI
  primitives.
- `domains/ai`, `domains/content`, `domains/learning` and `domains/user` exist,
  but most of them currently re-export files from root `api`, `composables`,
  `stores` and `types` directories.
- Server state is manually managed with `ref`, `computed`, `watch` and
  request functions. There is no TanStack Query client or query-key registry.
- The largest screens are `RepetitionsPage.vue` (~870 lines),
  `GraphCanvasPage.vue` (~752), `ContentDetailsPage.vue` (~638), and
  `PromptEditorPage.vue` (~497). `useTrainingSession.ts` is ~492 lines.
- Shared UI already has useful primitives (`UiButton`, `UiCard`, `UiInput`,
  `SelectField`, `UiBadge`, `UiEmptyState`, `UiSectionHeader`), but pages still
  repeat layout, typography, state and spacing classes directly.
- Current auth bootstrap and route guards are functional and already have
  browser smoke coverage. They should be migrated behind a stable application
  boundary, not redesigned during the first data-state migration.

## Facts: useful patterns in s365_Frontend

- Domains are organized as `domains/<Domain>/{api,model,ui,lib}` with a small
  public `index.ts` barrel. The `model` area contains types, query keys,
  queries, mutations, stores and domain-specific events where needed.
- Server state is centralized in `serverState/queryClient.ts` and installed
  once through `VueQueryPlugin` in the app entrypoint.
- Query keys are explicit factories, for example `all` plus parameterized
  `list/detail` keys. Query functions call domain API functions rather than
  embedding transport details in pages.
- Mutations invalidate or patch affected query cache entries and can track
  asynchronous job completion through domain events/transports.
- Routes are centralized in `router.js` and route-name constants are exported
  from `constants/routes/index.ts`. Large areas use nested routes and pages
  compose tabs/feature components.
- Pages are shells: they coordinate navigation and local presentation state,
  while tables/forms/queries/actions live in smaller components and domain
  modules.
- Shared visual primitives and SCSS tokens provide consistent tables, cards,
  dialogs, filters, headers and form controls.

## What should not be copied blindly

- s365 still contains a large legacy root `api/` tree, JavaScript files and
  page-local composables. Its domain structure is an active migration pattern,
  not proof that every boundary is already clean.
- s365 uses Element Plus and Bootstrap/SCSS conventions. Replacing Blue's
  Reka UI/Tailwind/CVA stack with Element Plus would create visual and
  dependency churn without improving the product boundary.
- s365 has permissive TypeScript settings (`strict: false`); Blue's strict
  TypeScript is a valuable readability and contract gate and should remain.
- A global event bus is present in s365. Blue should prefer query invalidation,
  direct emits and explicit domain events; an event bus is justified only for
  cross-cutting browser concerns that cannot use those mechanisms.

## Options considered

### A. Keep manual fetch state and only move files

Lowest migration cost, but it preserves duplicated loading/error/refetch logic,
does not solve cache consistency, and leaves the current domain barrels as
facades over a root architecture.

### B. Adopt TanStack Query for server state and migrate by domain (recommended)

Use TanStack Query for remote data, cache, background refetch and mutations;
keep Pinia for auth/session/UI preferences and other genuinely client-owned
state. Move each API/type/query/mutation into a domain slice and let pages
consume only the public domain barrel.

This matches the useful s365 pattern, aligns with the official TanStack model
that query keys uniquely describe cached data, and lets each changing query
input be represented as a cache dependency. It also gives a consistent place
for retries, stale time, invalidation, optimistic updates and global error
reporting.

### C. Replace the SPA with a heavier meta-framework or global state library

High migration cost and little product value for this Laravel-served SPA. It
would move complexity rather than clarify ownership and would make the current
backend/API rewrite harder to verify.

## Recommendation

Choose option B. Introduce one typed TanStack Query client, query-key factories
and domain-owned query/mutation modules. Keep Pinia only for client state. Keep
the existing visual stack, but consolidate its repeated patterns into a small
design-system layer and decompose the four oversized screens into page shells,
widgets and domain feature components.

## Important design decisions

1. `domains/*` own API functions, DTO types, query keys, queries, mutations,
   mappers and domain feature UI.
2. `pages/*` own route composition, URL parameters and screen-level layout;
   they do not call Axios or access another domain's internals.
3. `routes/*` owns route records, names, metadata and authorization policy
   declarations. Auth decisions remain implemented by the User domain/app
   router guard.
4. `shared/ui/*` owns visual primitives and state presentation only; it must
   not know Content, Learning, SRS or AI business rules.
5. Pinia owns auth identity, profile/session preferences and local UI state;
   TanStack Query owns server state. No duplicate source of truth is allowed.
6. Query keys include every variable used by the query function that changes
   the result; parameter objects are normalized before key creation.
7. Mutations invalidate or update the smallest affected query families and
   expose pending/error/success state to the page.
8. The first migration preserves API behavior and user-visible behavior. A
   later cleanup may remove compatibility paths once static checks prove no
   consumers remain.

## Risks and assumptions

- Adding TanStack Query increases dependency and mental-model surface; the
  migration must include conventions and examples, not only package changes.
- `RepetitionsPage` and `useTrainingSession` contain real product behavior;
  decomposition must preserve the learning/SRS contract and be covered by
  focused tests plus browser E2E.
- The current SPA uses a Laravel Vite dev-server origin. Query migration must
  preserve the existing Axios auth interceptor and local/CI browser setup.
- Unified styles mean one documented token/component vocabulary, not forcing
  every screen to have identical layout or removing intentional focus-layout
  variants.

## Research verdict

`pass_with_notes`: the neighboring project provides a strong, applicable
organizational pattern and TanStack Query is a good fit for Blue's growing
server state. The migration should be staged and behavior-preserving; a
big-bang file move or UI-library replacement is rejected.
