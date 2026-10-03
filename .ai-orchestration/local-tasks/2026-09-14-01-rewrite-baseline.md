# Rewrite: baseline и архитектурные правила

## Цель

Подготовить точку отсчёта для большого rewrite и зафиксировать ownership,
dependency rules и quality gates.

## Scope

- Классифицировать текущие изменения и сохранить их.
- Составить карту модулей, моделей, jobs, events, API и frontend domains.
- Уточнить rewrite spec фактическими зависимостями.
- Зафиксировать baseline тестов и сборки.
- Добавить ADR и проверки запрещённых cross-module зависимостей.

## Out of scope

- Переписывание production-кода.
- Изменение API/schema.

## План

- [x] Собрать dependency map и module ownership в
  `docs/architecture/module-ownership.md`.
- [x] Зафиксировать решения и открытые вопросы в rewrite spec и ADR.
- [x] Подготовить baseline checks и зафиксировать результаты в due-diligence
  package.
- [x] Обновить архитектурную документацию.

## Проверка

- `git diff --check`
- `make test`
- `cd src && npm run build`

Baseline and current verification on 2026-09-14:

- Laravel: 980 passed, 2947 assertions.
- Frontend: `vue-tsc --noEmit` and production build pass in Docker with Vite
  runner config loader; route-level lazy loading removed the large initial
  chunk. Non-blocking Browserslist/PURE-comment warnings remain.

## Документация

- `docs/architecture/rewrite-spec.md` и ADR.

## Зависимости

- Первая задача rewrite; блокирует остальные.
