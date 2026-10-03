# Agent Behavior

Короткая operating-инструкция для работы в этом проекте.

## Главный принцип

- `.ai-orchestration/` — agent workflow, временные задачи, короткая проектная документация
- `repo` — код, техническая документация, engineering skills

Агент должен использовать `.ai-orchestration/` как основной источник процесса.

## С чего начинать

Перед началом работы агент определяет тип запроса:
- идея / discovery
- локальная задача или набор задач
- backend implementation or review
- SPA implementation or review
- async / integrations / observability
- технический вопрос по существующему коду

## Какие skills использовать

| Ситуация | Skill |
|----------|-------|
| Любая agent-driven задача | `.ai-orchestration/README.md` |
| Идея или большая задача | `.ai-orchestration/workflows/research-to-delivery.md` |
| Реализация | `.ai-orchestration/workflows/multi-agent-delivery.md` |
| Backend scope | `engineering/skills/laravel_architecture.md` |
| SPA scope | `engineering/skills/vue_spa_architecture.md` |
| Jobs / events / integrations | `engineering/skills/async_and_integrations.md` |
| Проверки | `engineering/testing-policy.md` |

Если задача затрагивает несколько зон, агент комбинирует skills, но не открывает всё подряд.

## Что читать в репозитории

Смотреть только то, что помогает ответить на конкретный запрос:
- код и маршруты
- технические docs в `docs/`
- `.ai-orchestration/`
- текущие engineering skills
- минимальные engineering-файлы

Не опираться на удаленные старые role skills и client adapters.

## Что предлагать пользователю

Агент может предлагать:
- создать локальную markdown-задачу
- разбить большую работу на локальные задачи
- вынести техническое знание в repo docs
- упростить структуру, если документация или skills снова начинают разрастаться

## Чего избегать

- не плодить новые skills без регулярной пользы
- не превращать skills в длинные трактаты
- не хранить завершенные задачи как документацию
