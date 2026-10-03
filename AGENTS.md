# AGENTS.md

Короткая инструкция для AI-агентов, работающих в этом репозитории.

## Проект

Это Learning App: приложение для изучения языка через контент.

Основные части:

- backend: Laravel 12 в `src/`;
- admin: Filament;
- frontend: Vue 3 SPA, TypeScript, Pinia, Vue Router, Vite;
- infrastructure: Docker, Postgres, Redis;
- AI workflow и локальные задачи: `.ai-orchestration/`;
- engineering правила: `engineering/`;
- техническая документация: `docs/`.

## С чего начинать

1. Определи тип запроса: research, proposal, implementation, review, docs или маленькая правка.
2. Для agent workflow читай `.ai-orchestration/README.md` и нужный workflow из `.ai-orchestration/workflows/`.
3. Для контекста проекта читай `.ai-orchestration/project-docs/project-overview.md` и `PROJECT_CONTEXT.md`.
4. Для правил поведения читай `engineering/AGENT_BEHAVIOR.md`.
5. Для проверок читай `engineering/testing-policy.md`.

Не открывай все документы подряд. Читай только то, что нужно текущему scope.

## Orchestration

Используй `.ai-orchestration/` как основной источник процесса.

Полезные режимы:

- `research first` - идея, большая задача, архитектура, AI/cloud направление;
- `proposal first` - есть несколько нормальных подходов;
- `implement directly` - маленькая понятная правка;
- `review only` - проверка текущего diff.

Если задача большая, сначала подготовь предложение или локальную markdown-задачу в
`.ai-orchestration/local-tasks/`. Завершенные локальные задачи удаляются, а
устойчивые знания переносятся в `.ai-orchestration/project-docs/` или обычные docs.

## Архитектурные границы

Проект развивается как modular monolith.

Перед изменением определи модуль-владелец:

- `User` - auth, роли, права, профиль;
- `Content` - контент, каталог, обработка, модерация, lexeme extraction;
- `Learning` - study flow, sessions, learned state, progress;
- `SRS` - spaced repetition, due items, review history;
- `AI` - explanations, chat, recommendations, embeddings, providers;
- `Admin` - backoffice и операционные сценарии;
- `Integrations` - внешние API, OAuth, webhooks, contracts;
- `Observability / Infrastructure` - queues, monitoring, logs, Redis, Horizon.

По умолчанию выбирай самый простой способ взаимодействия:

- direct call для простого синхронного use case;
- job/queue для тяжелой или фоновой работы;
- event для важных бизнес-фактов и secondary effects.

## Engineering skills

Подключай только релевантные документы:

- backend scope: `engineering/skills/laravel_architecture.md`;
- SPA scope: `engineering/skills/vue_spa_architecture.md`;
- jobs, events, queues, integrations: `engineering/skills/async_and_integrations.md`;
- tests: `engineering/testing-policy.md`.

## Правила изменения кода

- Сначала изучи существующий код и локальные паттерны.
- Держи scope узким.
- Не меняй несвязанные файлы.
- Не откатывай чужие изменения.
- Не добавляй новые абстракции без явной пользы.
- Не расширяй API, data model, бизнес-логику или архитектуру без approval.
- Если меняется устойчивое поведение, обнови короткую документацию.

## Команды

Корневые команды:

```bash
make up
make test
make test ARGS="--filter=ProcessContent"
make test ARGS="tests/Feature/ExampleTest.php"
make npm ARGS="run build"
```

Laravel и frontend приложение находятся в `src/`.

Прямые команды внутри `src/`:

```bash
npm run build
composer test
php artisan test
```

Предпочитай focused verification: запускай минимальный набор проверок, который
реально покрывает измененный flow.

## Финальный ответ

В конце сообщи:

- что сделано;
- какие файлы изменены;
- какие проверки запускались;
- обновлялась ли документация;
- какие риски остались.
