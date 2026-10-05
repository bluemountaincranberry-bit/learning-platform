# Adaptive learning flow

Продуктовые правила и инварианты: [adaptive-learning-flow-rules.md](adaptive-learning-flow-rules.md).

## Решение

`Adaptive Practice` является оркестратором учебных активностей. Он выбирает
следующую activity по effective flow profile, SRS due state, confidence
dimensions, recent errors, learner goal и доступности контекста.

Типовой путь нового слова:

```text
encounter -> recognition -> recall -> production -> listening -> speaking
```

Это не жёсткая последовательность: selector может вернуть навык назад после
ошибки или раньше включить listening/speaking.

## Flow profiles

`learning_flow_profiles` хранит версионируемую конфигурацию профиля. Профиль
назначается через `learning_flow_assignments` по user, language, CEFR level или
learning goal. `LearningFlowResolver` выбирает published assignment с
наибольшим приоритетом; user assignment имеет приоритет над scope assignment.

`user_learning_preferences` содержит только разрешённые learner overrides:
размер сессии, новые слова, долю listening/speaking, hints и difficulty.

Пользователь также может выбрать опубликованный рекомендуемый профиль:
`Balanced`, `Listening first`, `Speaking first`, `Fast vocabulary` или `Deep
mastery`. Приоритет effective flow: user assignment, выбранный пользователем
опубликованный профиль, scoped assignment, затем `Balanced` default. Разрешённые
overrides применяются поверх выбранного профиля.

Published flow configuration проходит `LearningFlowConfigValidator`.
Конфигурация — структурированный JSON, не исполняемый код.

## Learning state

Источниками истины остаются:

- `user_lexeme_confidences` — уровень отдельных навыков;
- `exercise_attempts` — ответы, ошибки, hints и контекст;
- `srs_cards`/`srs_reviews` — интервалы и due state.

Learning stage пока вычисляется из этих источников, чтобы не дублировать
состояние. `AdaptiveActivitySelector` возвращает activity, target dimension,
stage, difficulty и selection reason.

## Points

`learning_point_events` — append-only ledger начислений. Source key делает
начисление idempotent для одного `exercise_attempt` или `srs_review`.
Points зависят от activity, score, hints и difficulty multiplier из effective
flow profile. Points не изменяют confidence и SRS intervals.

## Надёжность outcome loop

Batch self-check принимает `operation_id` и сохраняет результат в
`self_check_submissions`, поэтому повтор сетевого запроса не создаёт второй
outcome и не начисляет points повторно. Ошибочные ответы попадают в
`learning_retries`; due retries выдаются первыми в следующей сессии, а
успешный повтор закрывает retry.

Для безопасного rollout adaptive flow управляется `ADAPTIVE_FLOW_ENABLED`.
При выключенном флаге API возвращает legacy quick-check recommendation.

`learning_flow_metric_events` записывает selection/outcome события. Filament
страница Learning Flow Metrics показывает success rate по профилям и запускает
synthetic simulation для новых слов, слабого listening, speaking и recall.

## Admin and learner surfaces

Администратор управляет профилями и назначениями через Filament; доступ
ограничен `manage-learning-flows`. Learner получает effective flow через
`GET /api/learning/flow` и меняет безопасные preferences через
`PUT /api/learning/flow/preferences`.

## Следующие улучшения

Отдельных тикетов нет (решение VIK-37): audit records для
publish/assignment/override и графики/cohort filters в metrics dashboard
пересматриваются, когда профили станут пресетами Easy/Medium/Hard (VIK-30);
pronunciation provider integration tests с Azure Speech входят в scope
провайдера Speaking Coach (VIK-52), после одобрения бюджета/провайдера
в VIK-49. Остальные незавершённые правила имеют адресатов VIK-30/32/33/52
в [adaptive-learning-flow-rules.md](adaptive-learning-flow-rules.md).
