# Автоматический отбор учебных лексем

## Цель

Показывать пользователю учебные элементы с объяснимой категорией и приоритетом, а не плоский список extracted lexemes.

## Scope

- Добавить серверный selector для категорий `essential`, `useful_phrase`, `grammar_pattern`, `known`, `noise`, `rare`, `recommended`.
- Учитывать уровень пользователя, частотность, повторяемость в контенте, тип, известность и статус.
- Вернуть category/score/reasons в content lexemes API.
- Сохранить ручные действия пользователя и обратную совместимость.

## Out of scope

- Полная ML-модель и внешние frequency datasets.
- Автоматическое удаление лексем из базы.

## План

- [x] Согласовать правила ранжирования с текущей схемой.
- [x] Реализовать selector и API-поля.
- [x] Добавить feature tests.
- [x] Обновить документацию и API types.
- [x] Подключить общий UI-фильтр категорий и labels.

## Реализовано

- `LexemeLearningSelector` использует type, frequency, CEFR, learner level и known state.
- `/api/content/{content}/lexemes` возвращает category, score, reasons, frequency и confidence.
- Добавлены `LearningKnowledgeModelTest` и `docs/architecture/learning-selection.md`.

## Проверка

- Focused Content API tests и frontend build.

## Документация

- `docs/architecture/learning-selection.md`.
