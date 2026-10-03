# Adaptive curriculum

## Investigation

Сейчас adaptive queue смешивает due/new/reinforcement, но выбор content для
global practice опирается в основном на число непройденных лексем. Минимальный
безопасный шаг — recommendation service с due/mistake/level/goal signals,
который объясняет следующую активность; порядок SRS остаётся владельцем SRS.

## План

- [x] Добавить curriculum recommendation signals в существующий RecommendationService.
- [x] Ранжировать content и skill activities.
- [x] Вернуть reasons/next action в recommendations API.
- [ ] Подключить отдельный dashboard/repetitions activity entrypoint.

## Out of scope

- Полноценная mastery graph и ML policy.

## Проверка

- Recommendation feature tests и frontend build.
