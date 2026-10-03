# Adaptive learning flow: профили, персонализация и points

## Цель

Сделать `Adaptive Practice` главным оркестратором обучения. Система должна
выбирать следующую активность по слабому навыку пользователя, ошибкам, SRS,
цели и сложности слова. Администратор должен создавать несколько учебных
flow-профилей, назначать их пользователям и настраивать безопасные overrides.
Пользователь должен видеть только разрешённые настройки, а points должны
мотивировать сложные полезные действия, не подменяя реальное mastery.

## Продуктовое решение

### Основной принцип

Обучение строится как адаптивная спираль, а не как жёсткая линейная цепочка.
Типовой путь нового слова:

```text
encounter -> recognition -> recall -> production -> listening -> speaking
```

Selector может пропустить этап, вернуть предыдущий после ошибки или поднять
listening/speaking раньше, если это соответствует цели пользователя и самому
слабому confidence dimension.

### Источники истины

- `user_lexeme_confidences` — recognition, recall, production, listening,
  speaking;
- `exercise_attempts` — ответ, ошибка, подсказка, score и контекст;
- `srs_cards`/`srs_reviews` — интервалы, due state и история повторений;
- контент и transcript segments — примеры, аудио и исходный контекст.

На первом этапе learning stage вычисляется из этих данных и не дублируется
отдельным состоянием без необходимости.

### Flow profiles

Профиль содержит версионируемую конфигурацию:

- порядок и доступность learning stages;
- веса recognition/recall/production/listening/speaking;
- целевой диапазон успешности;
- cue ladder и правила hints;
- delayed retry после ошибки;
- лимиты новых слов и длительность сессии;
- правила сложности distractors;
- points rules;
- параметры для learning goals и CEFR levels.

Стартовые профили: `Balanced`, `Listening first`, `Speaking first`, `Fast
vocabulary`, `Deep mastery`.

Конфигурация хранится структурированными полями/JSON с серверной валидацией,
а не исполняемым кодом.

### Приоритет назначения

```text
system default
  -> language/level/goal profile
  -> group profile
  -> user profile
  -> allowed user overrides
```

Для одинакового приоритета действует самая новая активная версия. Назначения
имеют audit trail и возможность отключения.

### Пользовательские настройки

Пользователь может менять только разрешённые параметры: цель обучения,
длительность сессии, количество новых слов, долю listening/speaking в
допустимом диапазоне, режим подсказок и желаемую сложность.

Пользователь не может отключить spaced reviews, обойти ограничения или вручную
выставить mastery.

### Points

Points начисляются за подтверждённые learning events, а не за открытие
карточки:

| Активность | Базовые баллы |
|---|---:|
| Encounter | 1 |
| Recognition | 5 |
| Recall | 10 |
| Cloze | 15 |
| Production | 20 |
| Listening / Dictation | 15 / 20 |
| Shadowing / Speaking | 25 |

Итоговая формула учитывает активность, сложность, качество ответа и hints:

```text
points = base_activity_points
       * difficulty_multiplier
       * quality_multiplier
```

Ошибка не даёт отрицательных points. Успешное исправление ошибки может дать
небольшой recovery bonus. Баллы не изменяют confidence напрямую и не заменяют
SRS/mastery.

## Scope

- Backend Learning/SRS: adaptive selector, flow resolution, outcomes,
  delayed retry, points ledger и API contracts.
- Database: flow profiles, assignments, user overrides, point events и
  необходимые индексы/ограничения.
- Admin: CRUD профилей, версии, назначение, preview/simulation и audit log.
- Learner UI: настройки разрешённых параметров, объяснение выбора карточки,
  points за сессию и прогресс по навыкам.
- Existing exercise types: review, recognition, cloze, production, listening,
  dictation, shadowing и speaking.
- Feature/integration/unit tests, documentation и review pass.

## Out of scope первой версии

- ML/reinforcement-learning policy;
- полностью произвольный визуальный workflow editor;
- negative points и соревновательный leaderboard;
- ручное редактирование confidence без audit trail;
- автоматическое изменение flow на основе A/B-теста без явного rollout;
- замена существующего SRS новым алгоритмом.

## План реализации

## Агентская разбивка delivery

Работа выполняется последовательно главным оркестратором; каждый агентский
срез получает только свой scope и после него проходит focused verification.

1. **Agent Data & Resolver** — migrations, models, defaults, validator,
   assignments, effective flow resolver.
2. **Agent Adaptive Selector** — skill-gap selection, stages, capability
   fallback, selection metadata и training contract.
3. **Agent Outcome/SRS** — attempt context, delayed retry, confidence/SRS
   transitions и idempotency.
4. **Agent Points** — point events, difficulty/quality formula, ledger,
   aggregates и stats.
5. **Agent Admin** — Filament profiles, assignments, draft/publish, preview,
   permissions и audit.
6. **Agent Learner UI** — preferences, active profile, activity explanation,
   session/points feedback и manual mode compatibility.
7. **Agent Test & Review** — integration/feature/unit tests, simulation,
   backend/frontend review, fixes, rollout и documentation.

Текущий статус delivery отмечается в этом файле после каждого agent pass.

### Delivery status на 2026-08-21

- [x] Agent Data & Resolver — реализован и проверен.
- [x] Agent Adaptive Selector — подключён к self-check contract и UI fallback.
- [x] Agent Points — ledger, difficulty/quality rules, idempotency и stats.
- [x] Agent Admin — profiles, assignments, validation, publish/clone/preview,
  permissions и revisions.
- [x] Agent Learner UI — effective flow settings, activity reason и points UI.
- [x] Agent Test & Review — focused backend suite, route boot, frontend
  typecheck/build и diff review.
- [x] Server-side idempotency для batch self-check, retry queue, rollout
  feature flag и learning-flow metrics добавлены.
- [x] Рекомендованные профили seeded и доступны пользователю для выбора в
  настройках приложения.

### Этап 0. Архитектурная инвентаризация и контракт

- [ ] Зафиксировать текущие API, модели и точки записи learning outcomes.
- [ ] Проверить activity types и соответствие confidence dimensions.
- [ ] Определить единый словарь stages, activities, errors и point reasons.
- [ ] Описать invariants: SRS имеет приоритет, points не дублируются,
  overrides ограничены.
- [ ] Подготовить fixture-профили и synthetic learner cases.

### Этап 1. Модель flow profiles и assignments

- [ ] Добавить `learning_flow_profiles` с name, slug, status, version,
  description, config, created_by и published_at.
- [ ] Добавить `learning_flow_assignments` для user/group/level/language/goal
  scopes, priority и active date range.
- [ ] Добавить `user_learning_preferences` только для разрешённых overrides.
- [ ] Добавить unique/index constraints и безопасную миграцию.
- [ ] Создать модели, casts, relationships, policies и seed стартовых профилей.
- [ ] Реализовать `LearningFlowResolver` с effective config и источником
  назначения.

### Этап 2. Версионирование и безопасность конфигурации

- [ ] Создать schema validator для flow config.
- [ ] Запретить публикацию профиля с пустыми stages, плохими весами,
  недопустимыми activities или опасными лимитами.
- [ ] Разделить draft/published/archived состояния.
- [ ] Сделать published versions immutable; редактирование создаёт версию.
- [ ] Добавить audit log для publish, assignment и user override changes.
- [ ] Добавить preview effective config для выбранного пользователя.

### Этап 3. Adaptive activity selector

- [ ] Реализовать доменный `AdaptiveActivitySelector` в Learning module.
- [ ] Рассчитывать priority по due state, skill gap, recent errors, goal,
  context relevance и fatigue.
- [ ] Выбирать activity по минимальному confidence dimension, разрешённому
  текущим профилем.
- [ ] Реализовать stages `encounter`, `recognition`, `recall`, `production`,
  `listening`, `speaking`.
- [ ] Реализовать cue ladder: no cue, context cue, first letter, choices,
  translation, reveal.
- [ ] Реализовать delayed retry через 2–4 другие карточки.
- [ ] Ограничить повторение одного слова в сессии и не допускать однообразия.
- [ ] Заменить round-robin `chooseReinforceActivity` на selector с fallback
  для отсутствующих examples/audio/microphone.
- [ ] Возвращать `selection_reason`, `target_dimension`, `stage`, `difficulty`
  и `available_cues` в training API.

### Этап 4. Outcome loop и SRS integration

- [ ] Привязать каждый answer к activity, stage, dimension, error_type,
  hint_used, context и replay count.
- [ ] Обновлять только тот confidence dimension, который реально проверялся.
- [ ] Настроить переходы между stages по spaced evidence, а не по одной удаче.
- [ ] После ошибки уменьшать нужный skill и планировать повтор, не обнуляя
  другие навыки слова.
- [ ] Синхронизировать exercise outcome, SRS review и retry без двойной записи.
- [ ] Сделать operation id/idempotency для повторной отправки ответа.
- [ ] Добавить причину повторного появления карточки после ошибки.

### Этап 5. Points ledger и progression

- [ ] Добавить `learning_point_events` с user, lexeme, attempt/review,
  activity, difficulty, points, reason и metadata.
- [ ] Реализовать `PointsAwardService` с idempotency по source event.
- [ ] Добавить difficulty multipliers по CEFR, контексту и сложности задания.
- [ ] Учитывать hints, partial score, recovery after error и качество
  speaking/listening.
- [ ] Добавить read model или агрегаты для total, daily points и streak stats.
- [ ] Вернуть points summary в ответ завершения exercise/session.
- [ ] Не использовать points для изменения SRS intervals или confidence.

### Этап 6. Admin panel

- [ ] Создать Filament `LearningFlowProfileResource`.
- [ ] Добавить формы stages, weights, thresholds, retry rules, limits и
  points rules с helper texts.
- [ ] Добавить publish/clone/archive actions.
- [ ] Создать assignments с фильтрами user, level, language и goal.
- [ ] Добавить preview effective flow для выбранного пользователя.
- [ ] Добавить dry-run/simulation на synthetic learner profiles перед publish.
- [ ] Показать audit history и автора изменений.
- [ ] Ограничить доступ policies/permissions; learner API не должен видеть
  admin endpoints.

### Этап 7. Learner settings и Adaptive UI

- [ ] Добавить экран пользовательских разрешённых overrides.
- [ ] Показывать активный flow profile и объяснение его поведения.
- [ ] В карточке показывать `Why this activity?`.
- [ ] Показывать points за ответ, points за сессию и progress по skills.
- [ ] Добавить loading, empty, fallback и retry states.
- [ ] Оставить ручные Recall/Cloze/Listening/Dictation/Shadowing режимы.
- [ ] Сделать Adaptive Practice режимом по умолчанию.

### Этап 8. Tests, review и simulation

- [ ] Unit tests для config validation, resolver, selector, cue ladder и
  points formula.
- [ ] Integration tests для profile assignment → queue → answer →
  confidence/SRS/points.
- [ ] Feature tests для admin CRUD, publish, assignment, preview и permissions.
- [ ] Feature tests для learner preferences и forbidden overrides.
- [ ] Проверить idempotency повторной отправки attempt.
- [ ] Запустить synthetic simulation для слабого listening, speaking,
  recognition и production.
- [ ] Провести backend architecture review и frontend SPA review.
- [ ] Исправить findings и повторить focused verification.

### Этап 9. Rollout и документация

- [ ] Добавить feature flag для нового selector.
- [ ] Включить новый flow сначала для внутренних пользователей/малой группы.
- [ ] Сравнить completion rate, error rate, retries, retention, activity mix
  и points inflation со старым flow.
- [ ] Добавить kill switch к старому selector.
- [ ] Обновить `docs/architecture/` и `.ai-orchestration/project-docs/`.
- [ ] После завершения удалить эту задачу и перенести устойчивые решения в
  постоянную документацию.

## Product acceptance criteria

- Новый пользователь получает Encounter/Recognition, а не production без
  контекста.
- Пользователь с низким listening получает больше listening/dictation.
- Ошибка speaking не снижает reading/recognition confidence.
- После ошибки слово возвращается позже с объяснимой причиной.
- Один attempt не начисляет points дважды.
- Администратор создаёт и публикует новый flow без deploy.
- Изменение профиля не меняет историю завершённых attempts.
- User override не отключает SRS и не выставляет mastery вручную.
- При отсутствии example/audio/provider используется безопасный fallback.
- Для выбранного упражнения пользователь понимает, почему оно появилось.

## Review gates

1. **Data model review** — нет дублирования источников истины и опасных
   cascade/delete сценариев.
2. **Domain review** — selector не смешивает SRS, UI и admin responsibilities.
3. **Admin review** — конфигурация имеет validator, draft/publish/audit.
4. **Learning review** — points не подменяют mastery, ошибки привязаны к
   правильному skill dimension.
5. **UX review** — Adaptive mode объясним и не выглядит случайным quiz.
6. **Rollout review** — есть feature flag, metrics и rollback path.

## Риски и решения

- **Слишком сложная админка:** начать с форм и preset-ов, не делать canvas.
- **Переобучение на points:** выдавать points только за outcome events.
- **Слишком много production:** использовать weights и skill gaps.
- **Дублирование состояния:** stage сначала вычислять из confidence/attempts.
- **Плохой профиль ломает обучение:** validator, preview, simulation и kill
  switch обязательны.
- **Нет example/audio:** selector обязан иметь capability-aware fallback.

## Проверка

- `make test ARGS="<focused learning and admin tests>"`
- `make npm ARGS="run build"`
- `git diff --check`
- миграции на чистой SQLite/Postgres схеме;
- integration simulation нескольких learner profiles;
- ручной admin preview и learner Adaptive session.

## Документация

- Обновить `docs/architecture/` описанием profiles, resolver, selector и
  points ledger.
- Обновить `.ai-orchestration/project-docs/project-overview.md`, если новые
  границы станут устойчивыми.

## Завершение

После полного implementation провести implementation, test, review и
documentation passes, исправить findings, удалить этот файл из `local-tasks/`
и в финальном отчёте указать миграции, тесты, файлы, риски и rollout.
