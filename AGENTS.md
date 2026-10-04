# AGENTS.md

Learning App: изучение языка через реальный контент (YouTube, групповые уроки,
позже субтитры и книги). Laravel 12 и Filament в `src/`, Vue 3 SPA
(TypeScript, Pinia, Vite), Postgres, Redis, Docker.

- Что за продукт, модули, где что лежит: `PROJECT_CONTEXT.md`.
- Видение и продуктовые решения: skill `product-owner`.
- Словарь домена: `GLOSSARY.md`; архитектурные решения: `docs/architecture/adr/`.
- Стандарты кода и тестов: `engineering/coding-standards.md`, `engineering/naming-conventions.md`.

## Принципы

- **Enterprise-режим.** Строим расширяемо, с запасом на рост, по best practice и ADR.
- **Продумываем до конца.** Все сценарии и нужды пользователя, граничные случаи,
  следующий шаг продукта. Найденное за рамками тикета идёт в тикет или в новый тикет.
- **Полнее, а не минимально.** Абстракции, модули, события, расширение API и
  модели данных — норма, когда служат продукту и modular monolith.
- **Качество встроено.** Тесты на швах модулей, ясные границы модулей, ADR и
  короткие docs для устойчивых решений.
- **Агент решает сам и отчитывается.** Продуктовые вопросы решает `product-owner`,
  к Вике идут только пункты из его § Escalate.

## Стоп-линии

Это единственные жёсткие правила; всё остальное агент решает сам, с отчётом.

- В живой каталог публикует человек. Агент готовит предложение.
- Агент работает в ветке тикета и сливает в `main` через merge gate
  `linear-work`: нет открытых вопросов, критерии проверены, проверки зелёные,
  нет нерешённых пунктов `product-owner` § Escalate. Иначе — In Review.
- Данные пользователей сохраняются. Миграции переносят данные, а не удаляют их.

## Какой скилл для чего

| Ситуация | Скилл |
|---|---|
| Тикет Linear от начала до конца, статус доски, следующий тикет | `linear-work` |
| Продуктовый, UX, scope или приоритет вопрос | `product-owner` |
| Не знаю, какой скилл подходит | `ask-matt` |
| Обсудить и заострить идею или план | `grilling`, `grill-me`; с ADR и глоссарием по ходу — `grill-with-docs` |
| Идея → спека → тикеты | `to-spec` → `to-tickets` |
| Работа больше одной сессии | `wayfinder` |
| Разобрать входящие тикеты | `triage` |
| Реализовать тикеты или спеку | `implement`, `implement-spec`; внутри — `tdd` |
| Баг, падение, регрессия скорости | `diagnosing-bugs`, затем `tdd` |
| Границы модулей, швы, интерфейсы | `codebase-design`; поиск улучшений — `improve-codebase-architecture` |
| ADR, глоссарий, термины домена | `domain-modeling` |
| Исследование по первоисточникам | `research` |
| Проверить логику или UI до реализации | `prototype` |
| Ревью ветки или PR | `code-review`; текст PR — `pr` |
| Шаги, которые может сделать только человек (ключи, дашборды) | `wizard` |
| Вопрос, на который отвечает Вика | `to-questionnaire` |
| Передать работу другой сессии | `handoff`, `claude-handoff` |
| Объяснить Вике новую тему | `teach` |
| Ретро сессии; непонятный ответ агента | `retro`; `wait-what` |
| Спроектировать агентный workflow | `loop-me` |
| Скиллы, AGENTS.md | `writing-for-agents` |
| Статьи и тексты | `writing-fragments` → `writing-shape` или `writing-beats` |
| Разовая настройка репо | `setup-matt-pocock-skills`, `git-guardrails-claude-code`, `setup-pre-commit`, `setup-ts-deep-modules` |
| TypeScript-тесты без `as` | `migrate-to-shoehorn` |
| Учебные упражнения в формате курса | `scaffold-exercises` |

Скиллы лежат в `.agents/skills/` (Codex), в `.claude/skills/` — симлинки (Claude).
Библиотека `mattpocock/skills` зафиксирована в `skills-lock.json` и обновляется
только через `make skills-update` / `make skills-restore`; при конфликте с нашими
правилами побеждает она. Свои скиллы — отдельными папками рядом.

## Команды

Laravel и SPA живут в `src/`, PHP и тесты запускаются в Docker:

```bash
make up
make test ARGS="--filter=ProcessContent"
make npm ARGS="run build"
make wt-test ARGS="--filter=…"   # из git worktree
make wt-build                     # из git worktree
make workers-restart              # после изменений в jobs, очередях, AI-провайдерах
```

## Agent skills

### Issue tracker

Тикеты в Linear через Linear MCP. См. `docs/agents/issue-tracker.md`.

### Triage labels

Дефолтные роли: `needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`. См. `docs/agents/triage-labels.md`.

### Domain docs

Single-context: `GLOSSARY.md` в корне, ADR в `docs/architecture/adr/`. См. `docs/agents/domain.md`.

## Финальный отчёт

Что сделано; какие файлы и области изменены; какие проверки запускались и с
каким результатом; какие docs обновлены; принятые допущения; риски и следующий шаг.
