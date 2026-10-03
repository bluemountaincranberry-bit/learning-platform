# Quick Start

Готовые формулировки для запуска orchestration.

## Research first

```text
Используй ai-orchestration. Research first: <идея или задача>
```

Ожидаемый результат:

- короткое исследование;
- оценка размера: `small`, `proposal`, `task`, `task_list`, `research_only`;
- варианты решения;
- вопрос, переходить ли к задаче или implementation.

## Создать локальную задачу

```text
Используй ai-orchestration. На основе research создай локальную задачу.
```

Ожидаемый результат:

- markdown-файл в `.ai-orchestration/local-tasks/`;
- задача на русском;
- scope, out of scope, план, проверка, документация.

## Реализовать локальную задачу

```text
Используй ai-orchestration. Реализуй задачу из `.ai-orchestration/local-tasks/<file>.md`.
```

Ожидаемый результат:

- scoped implementation;
- focused verification;
- review pass;
- documentation update, если нужно;
- удаление завершенной задачи.

## Сделать маленькую правку сразу

```text
Используй ai-orchestration. Implement directly: <маленькая понятная задача>
```

Ожидаемый результат:

- реализация без отдельной локальной задачи;
- проверка;
- короткий итог.

## Обновить документацию после изменения

```text
Используй ai-orchestration. Documentation pass для последних изменений.
```

Ожидаемый результат:

- обновление `.ai-orchestration/project-docs/` или объяснение, почему не нужно.

## Review изменений

```text
Используй ai-orchestration. Review текущий diff.
```

Ожидаемый результат:

- findings;
- verification gaps;
- recommendation: `ready`, `needs fixes`, `needs decision`.
