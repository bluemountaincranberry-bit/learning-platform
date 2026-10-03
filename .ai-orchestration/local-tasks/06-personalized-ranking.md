# Персонализированный отбор и curriculum ranking

## Investigation

Текущий selector уже использует CEFR, frequency, phrase type и known state,
но не использует цель, confidence, ошибки и due state. Рекомендуемый путь —
объяснимый weighted ranking поверх существующей модели: сначала данные и
контракт, затем замена весов на ML только после накопления outcomes.

## План

- [x] Добавить нормализованный learner goal.
- [x] Собрать ranking signals: level fit, content repetition, confidence gap, mistake count, due status, goal fit.
- [x] Возвращать score/reasons без изменения legacy learned.
- [x] Добавить endpoint/тесты рекомендаций и документацию.

## Out of scope

- Обучение ML-модели и внешний frequency provider.

## Проверка

- Feature tests selector/recommendations; regression API tests.
