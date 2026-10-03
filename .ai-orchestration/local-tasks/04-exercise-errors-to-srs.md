# Связь ошибок упражнений с SRS

## Цель

Сохранять ошибку, упражнение, контекст и подсказку как единый review outcome, чтобы SRS мог правильно назначать повторение.

## Scope

- Добавить структурированный error metadata к SRS review.
- Связать review с content lexeme и transcript segment, если они известны.
- Зафиксировать hint usage и error type.
- Использовать grade для существующего SRS interval calculation.

## Out of scope

- Новый алгоритм SRS.
- Миграция старых review без достоверного контекста.

## План

- [x] Расширить таблицу reviews nullable metadata.
- [x] Передать metadata из self-check/review endpoints.
- [x] Добавить feature tests и индексы.
- [x] Документировать контракт.

## Реализовано

- Review сохраняет упражнение, error type, hint, content lexeme и transcript segment.
- Контекст проверяется на принадлежность content карточки.
- Interval algorithm не изменён; сохранена совместимость со старыми вызовами.

## Проверка

- Self-check submit, SRS review и regression tests.

## Документация

- `docs/architecture/learning-review-outcomes.md`.
