# Закрытие learning feedback loop

## Цель

Довести уже созданные backend-контракты до пользовательского flow: learner
видит автоматическую классификацию и confidence, а trainer передаёт в SRS
точный тип упражнения и характер ошибки.

## Scope

- Добавить category filter в общий `WordListToolbar`.
- Показать category и confidence dimensions в `WordListItem`.
- Расширить SPA self-check/SRS payload types.
- Передавать activity/error metadata из adaptive trainer.
- Добавить regression tests/build/review.

## Out of scope

- ML-ranking и внешние frequency datasets.
- Новый SRS algorithm.
- Отдельный speaking scoring pipeline.

## План

- [x] Реализовать UI category filter.
- [x] Добавить отображение category/confidence.
- [x] Передать metadata exercise → API → SRS.
- [x] Обновить документацию и tests.

## Реализовано

- Общий `WordListToolbar` фильтрует по learning category и показывает counts.
- `WordListItem` показывает category и доступные skill confidence.
- Adaptive trainer передаёт exercise type, error type и hint flag.

## Проверка

- Focused Laravel tests и frontend production build.

## Документация

- Дополнить `docs/architecture/learning-review-outcomes.md`.
