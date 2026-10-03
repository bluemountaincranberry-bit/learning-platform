# Learning analytics и измерение результата

## Investigation

В системе уже есть progress, weak words и language/level aggregates. Не
хватает skill-level outcomes, retention windows и learning-gain read model.
Рекомендуемый путь — append-only outcome data уже в `srs_reviews`, затем
агрегированный API без тяжёлой realtime аналитики.

## План

- [x] Добавить analytics aggregate: attempts, accuracy, skill accuracy, retention 1/7/30 days.
- [ ] Добавить learning-gain baseline/post metrics per content.
- [x] Расширить progress API и типы.
- [x] Покрыть queries тестами и документировать ограничения.

## Out of scope

- A/B experimentation platform и causal claims.

## Проверка

- Feature tests aggregate API.
