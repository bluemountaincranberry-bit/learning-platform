# Adaptive learning flow: product rules and invariants

Устойчивые продуктовые правила из архивированной задачи «15 adaptive learning flow
profiles» (local-tasks, 2026-08; перенесено в VIK-37). Таблицы, порядок
назначения профиля, learner overrides и points ledger описаны в
[adaptive-learning-flow.md](adaptive-learning-flow.md); значения по умолчанию
хранятся в `LearningFlowDefaults`. Здесь записано, **что должно оставаться
верным**, когда flow меняется, например при переводе профилей в пресеты
Easy/Medium/Hard (VIK-29/VIK-30).

## Принцип

Обучение идёт по адаптивной спирали, а не по жёсткой цепочке. Selector выбирает
следующую activity по самому слабому confidence dimension, недавним ошибкам,
SRS due state, цели и сложности слова. Он может вернуться к предыдущему навыку
после ошибки или раньше включить listening/speaking.

## Инварианты (код их соблюдает)

- SRS имеет приоритет: due reviews не отключаются ни профилем, ни learner
  override. Learner overrides ограничены списком `allowed_user_overrides`;
  ручной mastery среди них нет.
- Для слова без attempts selector возвращает stage `encounter`. Activity
  выбирается отдельно и сейчас может быть recall/cloze; stage не гарантирует
  вводную карточку.
- Stage вычисляется из attempts и confidence и не хранится отдельно.
- Learner видит, почему появилась activity (`selection_reason`).
- Опубликованная версия профиля неизменна; правка создаёт новую версию, поэтому
  история завершённых attempts не меняется.
- Конфигурация — структурированные данные, которые проверяет
  `LearningFlowConfigValidator`, а не исполняемый код.
- Points начисляются только за подтверждённые attempt/review, один source event
  даёт points один раз. Формула `base × difficulty × quality`: hints и
  частичный ответ уменьшают quality, ошибка даёт 0, а не отрицательные points.
- Points никогда не меняют confidence, SRS intervals или mastery.
- `ADAPTIVE_FLOW_ENABLED` — kill switch к legacy quick-check recommendation.

## Целевые правила (ещё не полностью в коде)

Их нужно учитывать при следующих изменениях flow; это требования, а не
описание текущего поведения.

- Новое слово сначала получает вводный контекст/recognition, прежде чем
  production; stage и activity должны согласовываться (VIK-30).
- Ответ обновляет только тот dimension, который реально проверялся. Ошибка в
  speaking не снижает reading/recognition и не обнуляет другие навыки слова
  (VIK-32; speech-provider seam также VIK-52).
- Stage растёт по накопленным spaced evidence, а не по одной удаче (сейчас
  stage зависит только от числа attempts и порогов confidence; VIK-30).
- После ошибки слово возвращается позже, через 2–4 другие карточки или в
  следующей сессии, и причина повтора видна learner (VIK-33; интервалы и лимиты
  определяет ADR-011, а не старый поэтапный план).
- Нет example/audio/microphone/provider → capability-aware fallback, а не
  ошибка (VIK-30; provider fallback также VIK-52).
- Каждая activity, которую выдаёт selector, должна иметь свои базовые points
  (сейчас у cloze их нет, работает fallback; VIK-30).
- Recovery bonus был необязательной идеей старого плана. Не включаем его в
  пресеты: reward factor VIK-30 уже отражает сложность, а бонус за прошлую
  ошибку поощрял бы лишние ошибки (PO proxy, VIK-37).
- Ручная правка confidence допустима только с audit trail (сейчас такой правки
  нет вообще).

## Вне scope

ML/RL policy, произвольный визуальный workflow editor, leaderboard и
соревновательные points, автоматическая смена flow по A/B-тесту без явного
rollout, замена SRS-алгоритма (см. VIK-14).

## Риски и ответ на них

| Риск | Ответ |
|---|---|
| Слишком сложная админка | Формы и пресеты, без canvas |
| Обучение «ради points» | Points только за outcome events |
| Слишком много production | Веса и skill gaps |
| Дублирование состояния | Stage вычисляется, не хранится |
| Плохой профиль ломает обучение | Validator, preview, simulation, kill switch `ADAPTIVE_FLOW_ENABLED` |
