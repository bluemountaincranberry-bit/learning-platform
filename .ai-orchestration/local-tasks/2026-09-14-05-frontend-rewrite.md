# Rewrite: frontend SPA

## Цель

Создать предсказуемую Vue SPA на стабильных backend contracts и доменных
границах.

## Scope

- Domain API clients и typed contracts.
- Stores/composables/pages по доменам.
- Business logic вне pages/shared UI.
- Loading/error/empty/retry states.
- AI UI через `domains/ai`.
- Route authorization и e2e checks.

## Out of scope

- Новый visual redesign.
- Изменение backend business rules.

## План

- [x] Зафиксировать API types и domain entrypoints: typed API response
  contracts уже собраны в `spa/types`, а AI/content/learning/user domains
  экспортируют свои boundary entrypoints.
- [x] Перевести ключевые auth/content pages на domain entrypoints; новые
  feature code должен подключаться через `domains/*`, а не через root API и
  store paths.
- [x] Перевести все текущие SPA pages и widgets с прямых root
  `api/composables/stores` imports на domain entrypoints.
- [x] Перевести оставшиеся page-level API type imports через domain
  entrypoints (`domains/learning`).
- [x] Перевести route pages на lazy loading: production build теперь делит
  страницы по маршрутам, main entry около 164 kB (gzip около 60 kB).
- [x] Закрепить воспроизводимый frontend typecheck через `vue-tsc` и
  устранить найденные route/UI/Vue Flow type errors.
- [x] Сделать auth bootstrap idempotent и дождаться `/api/auth/me` в router
  guard перед применением route authorization.
- [x] Добавить воспроизводимый local Vite origin (`localhost` по умолчанию с
  LAN override) и проверить browser smoke для public catalog и guest redirect.
- [x] Переписать auth/infrastructure boundaries: idempotent auth bootstrap,
  typed client и router guard ждут canonical `/api/auth/me` state.
- [x] Переписать user/content/learning/srs domains через typed domain
  entrypoints and lazy route boundaries.
- [x] Переписать AI domain через `domains/ai` и `domains/ai-builder` entrypoints;
  provider-specific logic remains backend-owned.
- [x] Закрепить helper migration boundary: pages/widgets не импортируют root
  API/store/composable paths напрямую; внутренние helpers остаются private
  implementation behind `domains/*` entrypoints до отдельного frontend
  semver cleanup.

## Проверка

- `cd src && npm run build`.
- E2E checks auth, catalog, study, repetitions и AI.

## Документация

- SPA-раздел архитектурной документации.

Текущий verification: `vue-tsc --noEmit` и project mode `vue-tsc -- -b`,
production build и browser smoke
проходят в контейнере;
public `/catalog` отрисовывает content cards, а guest `/repetitions`
редиректит на `/login?redirect=/repetitions`; guest `/admin/ai-builder`
редиректит на `/login?redirect=/admin/ai-builder`. Прямых root imports в pages/widgets
не осталось. Сохраняются только
non-blocking warnings о Browserslist/PURE-комментариях. Воспроизводимый
Playwright smoke в `docker/playwright/e2e/rewrite-smoke.mjs` покрывает guest
redirect, login, catalog/detail → study, repetitions и AI builder/tutor
authorization. Authenticated smoke для admin `/repetitions` и staff restriction
на `/chat` подтверждены через существующий local account; visual QA подтвердил
рендер catalog и practice surfaces с доступными headings и controls.

## Зависимости

- Backend API contracts и domain rewrite.
