# Аудит скиллов и правил агентов (VIK-44, 2026-10-03)

Основа: вся библиотека `mattpocock/skills` (37 скиллов в `skills-lock.json`).
Её не правим и не удаляем; при конфликте побеждает она, а наше переписывается
или удаляется. Проект работает в enterprise-режиме (принципы — в `AGENTS.md` и
`PROJECT_CONTEXT.md`). Удаления лежат отдельным коммитом на ветке тикета, их можно
откатить одним `git revert`.

## Итог

**Оставили без изменений (Matt, 37):** ask-matt, claude-handoff, code-review,
codebase-design, diagnosing-bugs, domain-modeling, git-guardrails-claude-code,
grill-me, grill-with-docs, grilling, handoff, implement, implement-spec,
improve-codebase-architecture, loop-me, migrate-to-shoehorn, pr, prototype,
research, retro, scaffold-exercises, setup-matt-pocock-skills, setup-pre-commit,
setup-ts-deep-modules, tdd, teach, to-questionnaire, to-spec, to-tickets, triage,
wait-what, wayfinder, wizard, writing-beats, writing-for-agents,
writing-fragments, writing-shape. Таблица «какой скилл для чего» — в `AGENTS.md`.

**Переписали:**
- `AGENTS.md` — коротко: принципы, три стоп-линии, таблица скиллов, команды, блок `## Agent skills` в формате Matt.
- `PROJECT_CONTEXT.md` — единое актуальное описание проекта (вобрало `project-overview.md`, `profiles/project.md` и принципы из `engineering-principles.md`).
- `docs/architecture/engineering-principles.md` — сведён к указателю на действующие принципы и заметке о смене курса: на него ссылаются ADR-004, AI-roadmap и комментарии в `src/`.
- `engineering/coding-standards.md` (новый) — стандарты Laravel, SPA и тестов в позитивной форме; их читает `code-review`. Собран из `engineering/skills/*` и `testing-policy.md`.
- `engineering/naming-conventions.md` — убрана привязка к завершённому OpenSpec change.
- `product-owner` — «самый простой вариант» заменён на «самый полный вариант для core loop»; Escalate приведён к стоп-линиям; в `VISION.md` раздел «How we build»; в `DECISIONS.md` два решения Вики от 2026-10-03.
- `linear-work` — сохранён раздел Skill routing; шаги «читай только нужное», «маленькие шаги», «по существующим паттернам», «минимальный набор проверок» заменены на «продумай тикет до конца», «строй на рост», «проверь затронутые потоки».
- `.ai-orchestration/local-tasks/README.md` — помечен как legacy; перенос задач в Linear — тикет VIK-37.
- `README.md`, `docs/architecture/README.md` — ссылки на актуальные документы.

**Удалили (отдельный коммит):** 6 openspec-скиллов и `.openspec-target`,
`learning-app-improvement-audit`, весь процесс `.ai-orchestration/` кроме
`local-tasks/`, `engineering/` кроме стандартов,
`docs/product/improvement-skills.md`, `.codex/`, `.cursorrules`, `.cursor/commands/`,
`openspec/`. Подробности — в таблице ниже.

## Таблица

| Пункт | Источник | Что делает | Ограничения, противоречащие принципам | Дубль с | Решение |
|---|---|---|---|---|---|
| 37 скиллов `mattpocock/skills` | `skills-lock.json` | Весь цикл: grilling → spec → tickets → tdd → review | — | — | Оставить, не трогать |
| `product-owner` | свой | Прокси Вики по продукту, лог решений | «Pick the simplest option»; «Simple over clever» и «never slow down… for hypothetical users» в VISION | — (Matt не покрывает) | Переписать |
| `linear-work` | свой | Тикет Linear от начала до конца, статусы | «Read only the docs…», «small steps», «existing patterns», «smallest set» проверок | Оркестрирует Matt-скиллы, не дублирует | Переписать |
| `learning-app-improvement-audit` | свой | Аудит UX/исследований/AI-тьютора → OpenSpec | Зависит от удалённых OpenSpec и `.ai-orchestration`, внешних скиллов, которых нет в репо; «smallest useful next slice», «audit does not authorize changes» | `research`, `prototype`, `to-spec`, `to-tickets` | Удалить |
| `openspec-apply-change`, `-archive-change`, `-explore`, `-propose`, `-sync-specs`, `-update-change` + `.openspec-target` | OpenSpec CLI 1.13 | Спека → задачи → реализация через OpenSpec | «Planning boundary», «never write code in explore»; требуют CLI; не слинкованы в `.claude/skills` | `to-spec`, `to-tickets`, `implement-spec`, `grilling` | Удалить |
| `openspec/` | OpenSpec | Единственный change `modular-architecture-refactor`, 45/45 задач выполнено, не архивирован | config: «Keep implementation scope narrow», «.ai-orchestration — primary workflow» | Итоги — в `docs/architecture/modular-refactor-*.md`, `module-ownership.md`, `event-catalog.md` | Удалить |
| `AGENTS.md` | свой | Входная точка агентов | «Держи scope узким», «не добавляй абстракции», «не расширяй API/модель без approval», «самый простой способ» | `.ai-orchestration/README`, `AGENT_BEHAVIOR.md` | Переписать |
| `PROJECT_CONTEXT.md` | свой | Контекст проекта | «не тащит сложность раньше времени», ссылки на Notion/Jira | `project-overview.md`, `profiles/project.md`, `engineering-principles.md` | Переписать (единый источник) |
| `engineering/AGENT_BEHAVIOR.md` | свой | Таблица «какой skill» на `.ai-orchestration` | «не плодить skills» | `AGENTS.md` | Удалить |
| `engineering/README.md`, `engineering/skills/README.md` | свой | Оглавления | «если skill не используется — не держим» | — | Удалить |
| `engineering/skills/laravel_architecture.md`, `vue_spa_architecture.md`, `async_and_integrations.md` | свой | Чек-листы ревью backend/SPA/async | «архитектура проще проблемы», «не овер-инженерить» | `code-review` (ось Standards) | Слить в `coding-standards.md`, удалить |
| `engineering/testing-policy.md` | свой | Integration-first, команды | — | `tdd`, `AGENTS.md` | Слить в `coding-standards.md`, удалить |
| `engineering/naming-conventions.md` | свой | Словарь имён, правила PHP/TS/API, проверяется `npm run architecture:naming` | — | — | Оставить (поправлено вступление) |
| `.ai-orchestration/README.md`, `QUICK_START.md` | свой | Процесс «оркестратор + роли» | approval перед реализацией | `linear-work`, `ask-matt` | Удалить |
| `.ai-orchestration/workflows/*` (3) | свой | research→local task→delivery | «implementation не расширяет scope», gates с approval | `wayfinder`, `to-spec`, `to-tickets`, `implement`, `linear-work` | Удалить |
| `.ai-orchestration/core/*` (3) | свой | Decision gates, context packet, форматы отчётов | Gate 2: спросить Вику при изменении API/данных/модулей | `linear-work` шаги 5 и 9, `handoff` | Удалить |
| `.ai-orchestration/agents/*` (8) | свой | Роли: orchestrator, research, planner, designer, implementation, test, review, docs | «implementation only approved scope» | `research`, `to-tickets`, `codebase-design`, `tdd`, `code-review` | Удалить |
| `.ai-orchestration/adapters/*` (2) | свой | Роли для Claude/Codex | «implementation не расширяет scope» | — | Удалить |
| `.ai-orchestration/templates/*` (4) | свой | Шаблоны задач и отчётов | «out of scope» | шаблон тикета в `docs/agents/issue-tracker.md` | Удалить |
| `.ai-orchestration/profiles/vika.md` | свой | Как общаться с Викой, когда нужен approval | «Избегать абстракций», список approval | `product-owner` (VISION, Escalate) | Удалить |
| `.ai-orchestration/profiles/project.md`, `learning.md` | свой | Контекст проекта, учебные цели | «Не расширять scope», «всегда указывать минимальный вариант» | `PROJECT_CONTEXT.md`, VISION § Engineering and learning goals, `teach` | Удалить |
| `.ai-orchestration/project-docs/*` (2) | свой | Короткий обзор проекта | «самый простой способ взаимодействия» | `PROJECT_CONTEXT.md` | Слить, удалить |
| `.ai-orchestration/local-tasks/*` | свой | Старый backlog в markdown | README: «только после approval» | Linear | Оставить задачи до VIK-37, README переписан |
| `docs/agents/issue-tracker.md`, `triage-labels.md`, `domain.md` | Matt setup + наш Linear | Конфиг для Matt-скиллов | — | — | Оставить |
| `docs/architecture/engineering-principles.md` | свой | Инженерные принципы | «не тащит сложность раньше времени» | `PROJECT_CONTEXT.md` (почти дословно) | Переписать в указатель (на файл ссылаются ADR и код) |
| `docs/product/improvement-skills.md` | свой | Инструкция к audit-скиллу и OpenSpec | — | — | Удалить вместе со скиллом |
| `.codex/README.md`, `.codex/skills/ai-orchestration`, `spec-driven-rewrite` | свой (Codex) | Вход в `.ai-orchestration`; план большого переписывания | «Do not start coding until…» | `.agents/skills` (Codex читает их), `wayfinder`, `to-spec` | Удалить |
| `.cursorrules`, `.cursor/commands/*` (12) | свой (Cursor) | Правила и команды Cursor | Ссылаются на несуществующие `workflow/`, `engineering/skills/roles/`, Jira | Matt-скиллы | Удалить; `.cursor/mcp.json` (Notion MCP) оставлен |

## Конфликты с библиотекой Matt

- `product-owner` отвечает за Вику, когда `grilling`/`to-spec` спрашивают «пользователя». Это композиция, а не правка Matt: сам скилл не меняется.
- `linear-work` — оркестратор поверх Matt (`tdd`, `diagnosing-bugs`, `research`, `prototype`, `code-review`, `pr`), свою логику ревью и тестов не дублирует.
- Стоп-линия «живой каталог публикует человек» касается агентов. Продуктовый auto-apply AI-кандидатов в каталог (решение 2026-08-08) — поведение приложения, оно не меняется.

## Скиллы уровня аккаунта: отключить в claude.ai

Лежат в аккаунте Вики (локальная копия — `~/.codex/skills-archive-2026-10-02/`),
в git их нет. Рекомендация: отключить все девять.

| Скилл | Почему отключить |
|---|---|
| `implement-simply` | Прямо противоречит принципам: «smallest correct change», «no future-proofing», «no unnecessary abstraction» |
| `simple-refactor` | «Avoid altering public interfaces», для junior; дубль `improve-codebase-architecture`, `codebase-design` |
| `refactor-roadmap` | Дубль `improve-codebase-architecture` + `wayfinder` |
| `create-plan` | Дубль `to-spec`, `wayfinder`; «minimal workflow», read-only |
| `investigate-approach` | Дубль `research`, `grilling`, `codebase-design` (design it twice); тянет к «do nothing/simple change» |
| `investigate-s365-flow` | Другой проект (S365), здесь только шум |
| `pr-writing` | Дубль Matt `pr` |
| `release-writing` | Дубль `pr`; формат коммитов задаёт `linear-work` |
| `be-task-writing` | Хэндофф FE→BE между командами; здесь тикеты пишет `to-tickets` |

Встроенные скиллы Anthropic (`pdf`, `docx`, `xlsx`, `pptx`, `skill-creator` и т.п.)
не конфликтуют, их можно оставить.
