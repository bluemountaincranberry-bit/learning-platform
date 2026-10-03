# Project Overview

## Продукт

Learning App - приложение для изучения языка через контент.

Пользователь учится через видео, тексты, грамматику, извлеченные lexemes,
study flow, progress tracking и SRS. Admin/editor управляет контентом,
обработкой и операционными сценариями.

## Стек

- Backend: Laravel 12, PHP 8.2.
- Admin: Filament 5.
- Frontend: Vue 3 SPA, TypeScript, Pinia, Vue Router, Vite.
- Auth/permissions: Laravel Sanctum, Spatie Laravel Permission.
- Search/AI data: Laravel Scout, Elastic Scout driver.
- Observability/ops: Horizon, Pulse, Telescope, Pail.
- Tests: Pest, Laravel feature/integration tests.
- Infrastructure: Docker, Postgres, Redis.

## Модули

- `User` - пользователь, auth, роли, права, профиль.
- `Content` - источники, каталог, обработка, видимость, moderation, lexeme extraction.
- `Learning` - study flow, learned state, sessions, self-check, progress read models.
- `SRS` - spaced repetition, due items, review logic, intervals, history.
- `AI` - explanations, chat, recommendations, embeddings, providers.
- `Admin` - backoffice, content/user management, operations, admin observability.
- `Integrations` - external APIs, OAuth, webhooks, contracts.
- `Observability / Infrastructure` - queues, monitoring, logs, events, Redis, possible Kafka.

## Архитектурные правила

- Это modular monolith.
- Перед изменением определить модуль-владелец.
- Выбирать самый простой способ взаимодействия: direct call, job/queue или event.
- Event-first использовать для важных бизнес-фактов, а не для всего подряд.
- Новые технологии предлагать только при продуктовой, архитектурной или обучающей пользе.

## SPA UI structure

- Frontend shell lives in `src/resources/js/spa/widgets/shell/`.
- Reusable UI primitives live in `src/resources/js/spa/shared/ui/`.
- Route-level screens live in `src/resources/js/spa/pages/`.
- Domain entrypoints live in `src/resources/js/spa/domains/` and re-export feature APIs/hooks by business area.
- The learner UI now uses a light technical theme with purple accents and mobile-first spacing in `src/resources/css/app.css`.
- Modern select inputs use `reka-ui` via `src/resources/js/spa/shared/ui/SelectField.vue`.
- Dashboard is the main entry point; catalog, study, repetitions, my words, progress, grammar, chat, categories and source submission are separate route surfaces.

## Progress schema compatibility

- `user_lexeme_progress` uses canonical `lexeme_id`, unique per user, after the July 2026 migrations; `content_lexeme_id` retains source context.
- Confidence remains occurrence-scoped and SRS still uses `type:text`; proposed consolidation and a data-preserving migration plan are in `docs/architecture/adr/ADR-010-word-keyed-repetition-and-personal-lexemes.md`.

## Основные документы

- `docs/architecture/modules-and-events.md`
- `docs/architecture/engineering-principles.md`
- `docs/architecture/content-ingestion-status-model.md`
- `docs/architecture/queue-and-observability.md`
- `engineering/testing-policy.md`
- `engineering/skills/laravel_architecture.md`
- `engineering/skills/vue_spa_architecture.md`
- `engineering/skills/async_and_integrations.md`

## Проверки

- `make test`
- `make test ARGS="--filter=ProcessContent"`
- `make test ARGS="tests/Feature/ExampleTest.php"`

Подход: integration-first. Для значимых задач сначала проверять ключевые
потоки между модулями, затем user/admin API сценарии, затем unit-level
вычисления и инварианты.
