# Research To Delivery Workflow

## Когда использовать

Использовать, когда Вика дает идею, большую задачу, новую фичу,
архитектурное направление или AI/cloud-oriented улучшение.

Цель - сначала понять, что делать и насколько это большое, а не сразу писать код.

## Шаги

1. Orchestrator читает:
   - `profiles/vika.md`
   - `profiles/project.md`
   - `profiles/learning.md`
   - `core/decision-gates.md`
2. Orchestrator проверяет Gate 1: понятна ли задача.
3. Research Agent делает короткий research.
4. Orchestrator классифицирует результат:
   - `small` - можно implement directly;
   - `proposal` - нужно выбрать подход;
   - `task` - нужно оформить одну markdown-задачу;
   - `task_list` - нужно разбить на несколько markdown-задач;
   - `research_only` - пока оставить как идею или продолжить discovery.
5. Orchestrator возвращает Вике research result и вопрос о следующем шаге.
6. После подтверждения:
   - для `small` перейти к `multi-agent-delivery.md`;
   - для `proposal` вызвать Solution Designer;
   - для `task` вызвать Task Planner и создать задачу в `.ai-orchestration/local-tasks/`;
   - для `task_list` вызвать Task Planner и создать несколько задач в `.ai-orchestration/local-tasks/`;
   - для `research_only` зафиксировать вывод и остановиться.

## Контекст для Research Agent

Передавать:

- исходный запрос Вики;
- нужные части `profiles/`;
- список документов, которые надо проверить;
- найденные факты из кода, если они уже известны.

Не передавать:

- весь чат;
- длинные логи;
- несвязанные исторические документы;
- завершенные локальные задачи.

## Выход

Research result должен ответить:

- что это за задача;
- насколько она большая;
- какие есть варианты;
- какой вариант рекомендован;
- что спросить у Вики дальше.

## Локальные задачи

Если Вика подтверждает создание задачи, Orchestrator создает markdown-файл в
`.ai-orchestration/local-tasks/` по формату `templates/local-task-template.md`.

Файл называть коротко и понятно:

```text
YYYY-MM-DD-short-task-name.md
```

Задача должна быть на русском языке. После завершения implementation файл
удаляется, а постоянные знания переносятся в `project-docs/` через
Documentation Agent.

## Контекст для Task Planner

Передавать:

- research result;
- выбранный вариант;
- предполагаемый scope;
- зависимости и риски;
- `templates/local-task-template.md`.

Task Planner должен создать задачи с понятной целью, scope, out of scope,
планом, проверкой и заметкой по документации.
