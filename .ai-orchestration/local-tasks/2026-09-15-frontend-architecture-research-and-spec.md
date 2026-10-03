# Frontend architecture research и spec

## Цель

Исследовать архитектуру `s365_Frontend`, сопоставить её с Blue и подготовить
утверждаемую спецификацию большого frontend rewrite с доменами, TanStack
Query, pages, routes и единым UI foundation.

## Фактический research

- Research report: `docs/architecture/frontend-rewrite-research.md`.
- Proposed spec: `docs/architecture/frontend-rewrite-spec.md`.
- Сильный паттерн s365: `domains/<Domain>/{api,model,ui,lib}`, query keys,
  queries/mutations, единый QueryClient и маленькие page/tab shells.
- Blue уже имеет domain barrels и reusable primitives, но server state пока
  вручную управляется через composables, а domains часто только
  переэкспортируют root implementation.

## Решение для следующего этапа

Использовать staged migration: сначала app/infrastructure и TanStack Query,
затем UI tokens/primitives, потом домены и oversized pages. Сохранить строгий
TypeScript и Reka UI/Tailwind/CVA. Backend contracts не менять.

## Acceptance criteria research/spec

- [x] Сравнить структуру domains/pages/routes/server state/UI в обоих проектах.
- [x] Зафиксировать факты отдельно от предложенных решений.
- [x] Рассмотреть минимум три подхода и выбрать рекомендуемый.
- [x] Описать target structure, dependency rules, query policy, UI standard,
  acceptance criteria, metrics, risks и open decisions.
- [x] Разложить дальнейшую реализацию на dependency-ordered slices.

## Следующие implementation slices

1. Frontend baseline: import-boundary rules, route conventions, state
   ownership matrix and TanStack Query installation contract.
2. Query infrastructure: typed HTTP/error adapter, QueryClient defaults,
   query-key factory convention and test harness.
3. Shared UI foundation: tokens, page/layout primitives, async state
   components and component contract tests.
4. User/Content migration: auth/profile/catalog/content detail queries and
   mutations behind public domain barrels.
5. Learning/SRS migration: training/review/self-check/progress query and
   mutation slices, preserving the session state machine.
6. AI/Admin migration: tutor, recommendations, lessons and AI builder query
   slices with authorization-aware states.
7. Page decomposition: repetitions, content detail, graph canvas and prompt
   editor.
8. Static architecture checks, E2E expansion, legacy helper cleanup and
   final review/documentation.

## Проверка research/spec

- Local source comparison completed.
- Official TanStack Vue Query docs checked for query keys, query functions,
  mutations, QueryClient and defaults.
- No application code changed by the original research task.

## Реализованный baseline-срез

- добавлена зависимость `@tanstack/vue-query`;
- создан единый `infrastructure/query/queryClient.ts` с явной политикой
  `staleTime`, `gcTime`, retry и refetch-on-focus;
- QueryClient подключён через `VueQueryPlugin` в `spa/main.ts`;
- production build и `vue-tsc --noEmit` проверены в чистой временной копии
  проекта.

Локальный `src/node_modules` не переустанавливался: существующая директория
содержит файлы с несовместимыми правами. Это не влияет на lock-файл или код;
чистая проверка выполнена отдельно.

## Реализованный infrastructure-срез

- добавлен typed `request<T>` boundary в `infrastructure/http/apiClient.ts`;
- добавлен нормализованный `ApiClientError` с сохранением Axios-compatible
  response shape для существующего `parseApiError`;
- добавлен первый domain-owned key factory:
  `domains/content/model/contentQueryKeys.ts`;
- ключи для list/detail/categories/transcript включают изменяющиеся inputs.

## Реализованный UI foundation-срез

- добавлены `UiPageShell` и `UiPageHeader` для единообразной композиции
  экранов;
- добавлен `UiStack` для повторяемых вертикальных и горизонтальных layout
  patterns;
- добавлен доступный `UiSpinner`;
- добавлен `AsyncState`, объединяющий loading/error/empty/content состояния и
  retry action;
- новые primitives опубликованы через `shared/ui/index.ts`.

## Реализованный User/Content-срез

- каталог переведён с ручного `loading/error/items` состояния на
  `useContentListQuery` с debounce поиска и сохранением предыдущего результата
  при смене фильтра;
- добавлены `useContentQuery` и `useContentCategoriesQuery` для следующих
  экранов миграции;
- добавлены `useProfileQuery` и `useUpdateProfileMutation`;
- query keys и query-модули принадлежат соответствующим domains;
- `CatalogPage` теперь задаёт начальный scope через параметры composable и не
  запускает ручную загрузку.

## Open decisions

Для следующих slices остаются решения из секции `Open decisions requiring
approval` в frontend spec: граница admin UI и момент перехода на generated
API client. Добавление `@tanstack/vue-query` и сохранение текущего UI stack
зафиксированы baseline-срезом.
