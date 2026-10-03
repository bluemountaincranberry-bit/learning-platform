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

## Product owner

Продуктовые вопросы (что делать, UX, scope, приоритет) решай через skill
`product-owner` (`.agents/skills/product-owner/`): он отвечает за Вику по её
видению и логирует решения. К Вике идут только пункты из его раздела
Escalate. Видение там важнее старых формулировок в этом файле и в
`.ai-orchestration/profiles/vika.md`.

Цикл работы: research → тикеты в Linear (`/to-tickets`) → выполнение тикета
скиллом `linear-work` (`/linear-work VIK-N | next | status`) → Вика ревьюит
(статус In Review) и переводит в Done. Параллельно: отдельная сессия в
worktree на тикет, проверки через `make wt-test` / `make wt-build`.

## Agent skills

Skills лежат в `.agents/skills/` (Codex), в `.claude/skills/` — симлинки (Claude).
Внешние ставятся через `npx skills` и фиксируются в `skills-lock.json`:
`make skills-update` — обновить, `make skills-restore` — восстановить по lock.
Внешние skills не редактируй — изменения затрёт update; свои пиши отдельным skill.

### Issue tracker

Тикеты в Linear через Linear MCP. См. `docs/agents/issue-tracker.md`.

### Triage labels

Дефолтные роли: `needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`. См. `docs/agents/triage-labels.md`.

### Domain docs

Single-context: `GLOSSARY.md` в корне, ADR в `docs/architecture/adr/`. См. `docs/agents/domain.md`.

## Финальный ответ

В конце сообщи:

- что сделано;
- какие файлы изменены;
- какие проверки запускались;
- обновлялась ли документация;
- какие риски остались.
