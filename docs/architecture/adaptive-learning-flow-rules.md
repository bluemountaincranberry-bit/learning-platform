# Adaptive learning flow: product rules and invariants

Устойчивые продуктовые правила из выполненной задачи «15 adaptive learning flow
profiles» (local-tasks, 2026-08; перенесено в VIK-37). Архитектура и таблицы описаны в
[adaptive-learning-flow.md](adaptive-learning-flow.md). Здесь записано, **что
должно оставаться верным**, когда flow меняется, например при переводе
профилей в пресеты Easy/Medium/Hard (VIK-29/VIK-30).

## Принцип

Обучение идёт по адаптивной спирали, а не по жёсткой цепочке. Selector выбирает
следующую activity по самому слабому confidence dimension, недавним ошибкам,
SRS due state, цели и сложности слова. Он может пропустить этап, вернуться к
предыдущему после ошибки или раньше включить listening/speaking.

## Инварианты

- SRS имеет приоритет: due reviews не отключаются ни профилем, ни learner
  override.
- Новый пользователь сначала получает encounter/recognition, а не production
  без контекста.
- Ответ обновляет только тот dimension, который реально проверялся. Ошибка в
  speaking не снижает reading/recognition и не обнуляет другие навыки слова.
- Stage меняется по накопленным spaced evidence, а не по одной удаче. Stage
  вычисляется из confidence/attempts/SRS и не хранится отдельно без
  необходимости.
- После ошибки слово возвращается позже (через 2–4 другие карточки или в
  следующей сессии), и причина повтора видна learner.
- Learner понимает, почему появилась activity (`selection_reason`).
- Нет example/audio/microphone/provider → capability-aware fallback, а не
  ошибка.
- Изменение или публикация профиля не меняет историю завершённых attempts.
  Опубликованная версия неизменна; правка создаёт новую версию.
- Конфигурация — структурированные данные с серверной валидацией, не
  исполняемый код. Профиль с пустыми stages, плохими весами или опасными
  лимитами не публикуется.

## Назначение профиля и overrides

Порядок от общего к частному: system default → language/level/goal → group →
user, затем разрешённые learner overrides. Для равного приоритета действует
самая новая активная версия. Текущий порядок реализации описан в
[adaptive-learning-flow.md](adaptive-learning-flow.md#flow-profiles).

Learner может менять только цель, длительность сессии, число новых слов, долю
listening/speaking в допустимом диапазоне, режим подсказок и сложность. Он не
может отключить spaced reviews, обойти лимиты или вручную выставить mastery.
Ручная правка confidence возможна только с audit trail.

## Points

Points начисляются только за подтверждённые learning events (attempt/review), а
не за открытие карточки. Один attempt не начисляет points дважды (idempotency
по source event).

```text
points = base_activity_points × difficulty_multiplier × quality_multiplier
```

Базовые баллы в исходном решении: encounter 1, recognition 5, recall 10,
cloze 15, production 20, listening 15, dictation 20, shadowing/speaking 25.
Текущие значения хранятся в `LearningFlowDefaults` и конфигурации профиля;
источник истины — код.

- Ошибка не даёт отрицательных points; успешное исправление ошибки может дать
  небольшой recovery bonus.
- Hints и частичный ответ уменьшают quality multiplier.
- Points никогда не меняют confidence, SRS intervals или mastery.
- Без leaderboard и соревновательных механик в первой версии.

## Вне scope

ML/RL policy, произвольный визуальный workflow editor, автоматическая смена
flow по A/B-тесту без явного rollout, замена SRS-алгоритма (см. VIK-14).

## Риски и ответ на них

| Риск | Ответ |
|---|---|
| Слишком сложная админка | Формы и пресеты, без canvas |
| Обучение «ради points» | Points только за outcome events |
| Слишком много production | Веса и skill gaps |
| Дублирование состояния | Stage вычисляется, не хранится |
| Плохой профиль ломает обучение | Validator, preview, simulation, feature flag `ADAPTIVE_FLOW_ENABLED` как kill switch |
