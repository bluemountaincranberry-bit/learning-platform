# Многомерное знание слова

## Цель

Хранить не только факт learned, но и прогресс навыков recognition, recall, production, listening и speaking.

## Scope

- Добавить отдельную user-scoped таблицу confidence dimensions для content lexeme.
- Описать безопасные диапазоны и обновление отдельных dimensions.
- Возвращать dimensions в учебных API.
- Не ломать текущие learned/in_review/SRS сценарии.

## Out of scope

- Полная психометрическая модель и автоматическая сертификация уровня.
- Переписывание всех существующих study screens.

## План

- [x] Создать schema/model с defaults.
- [x] Добавить сервис обновления confidence.
- [x] Подключить к self-check; SRS context готов для следующих skill-specific exercises.
- [x] Добавить tests и документацию.

## Реализовано

- `user_lexeme_confidences` хранит пять dimensions в диапазоне 0–100.
- Self-check обновляет recognition, recall и listening.
- API возвращает confidence для существующей записи без изменения legacy learned.

## Проверка

- Integration tests self-check/SRS/confidence.

## Документация

- `docs/architecture/learning-knowledge-model.md`.
