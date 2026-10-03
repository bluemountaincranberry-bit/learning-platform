# Профиль проекта

## Назначение

Этот файл хранит постоянный контекст проекта. Его нужно обновлять по мере
понимания продукта, архитектуры и рабочих правил.

## Что проект делает

Learning App - продукт для изучения языка через контент.

Система включает:

- обучение через видео, тексты, грамматику и извлеченные lexemes;
- каталог и обработку content;
- study flow, progress tracking и SRS;
- AI features: explanations, chat, recommendations, embeddings;
- admin/editor flows для управления контентом и обработкой.

Ключевые пользователи:

- learner - учится и проходит study flow;
- admin/editor - управляет контентом, модерацией и processing flows.

## Технический стек

- Backend: Laravel 12, PHP 8.2.
- Admin: Filament 5.
- Frontend: Vue 3 SPA, TypeScript, Pinia, Vue Router, Vite.
- Auth/permissions: Laravel Sanctum, Spatie Laravel Permission.
- Search/AI data: Laravel Scout, Elastic Scout driver.
- Observability/ops: Horizon, Pulse, Telescope, Pail.
- Tests: Pest, Laravel feature/integration tests.
- Infrastructure: Docker, Postgres, Redis.

## Архитектурные правила

- Сначала читать существующий код и документацию.
- Предпочитать локальные изменения существующим паттернам проекта.
- Не расширять scope без отдельного решения.
- Проверять изменения минимальными релевантными тестами.
- Думать как modular monolith.
- Сначала определить модуль-владельца: `User`, `Content`, `Learning`, `SRS`, `AI`, `Admin`, `Integrations`, `Observability / Infrastructure`.
- Для взаимодействия модулей выбирать самый простой подход: direct call, job/queue или event.
- Event-first применять только для важных бизнес-фактов, а не для всего подряд.
- AI и cloud/observability идеи предлагать как варианты развития, если они дают продуктовую или обучающую пользу.

## Важные документы проекта

- `AGENTS.md`
- `engineering/AGENT_BEHAVIOR.md`
- `engineering/testing-policy.md`
- `docs/architecture/`
- `docs/architecture/modules-and-events.md`
- `docs/architecture/engineering-principles.md`
- `docs/architecture/content-ingestion-status-model.md`
- `docs/architecture/queue-and-observability.md`

## Local planning model

- `.ai-orchestration/local-tasks/` - временные markdown-задачи на русском для текущей работы.
- `.ai-orchestration/project-docs/` - короткие устойчивые знания, которые должны остаться после задачи.
- Завершенные задачи удаляются из `local-tasks/`.

Локальные задачи нужны только как рабочий буфер. Постоянные знания должны жить
в документации, а не в завершенных задачах.

## Project documentation model

- `.ai-orchestration/project-docs/` - короткая актуальная документация для агента и Вики.
- `docs/architecture/` - более постоянная техническая архитектура.
- `engineering/` - правила работы агентов, testing policy и skills.

После завершения значимой задачи documentation-agent должен обновить короткую
документацию, если изменились поведение, архитектура, модульные границы,
workflow или важные команды.

## Testing policy

Проект использует `integration-first` подход.

Приоритет:

1. Integration tests для ключевых потоков между модулями.
2. Feature tests для user/admin HTTP/API сценариев.
3. Unit tests для локальной доменной логики и вычислений.

Команды:

- `make test`
- `make test ARGS="--filter=ProcessContent"`
- `make test ARGS="tests/Feature/ExampleTest.php"`
