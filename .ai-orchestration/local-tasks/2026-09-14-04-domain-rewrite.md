# Rewrite: Content, Learning, Srs и User

## Цель

Переписать основные бизнес-модули вокруг ясного ownership и проверяемых
межмодульных сценариев.

## Scope

- Content ingestion, catalog, moderation и lexeme ownership.
- Learning sessions, attempts, progress и selection.
- Srs scheduling и review history.
- User identity, profile, permissions и settings.
- Явные events/jobs между модулями.

## Out of scope

- AI provider internals.
- Frontend implementation.
- Новые продуктовые фичи вне rewrite.

## План

- [x] Написать integration tests критических flows: learning attempt → SRS
  recalculation → review history, плюс существующие content/auth flows.
- [x] Ввести module-owned SRS persistence contract/repository для due cards,
  card lifecycle и review records; SRS listeners и service используют этот
  boundary.
- [x] Определить invariants и persistence owners в
  `docs/architecture/module-ownership.md`; SRS persistence уже скрыта за
  module-owned repository contract.
- [x] Перенести `ExerciseAttempt` и `LearningProgress` в Learning module с
  compatibility aliases и перевести application/API consumers на canonical
  models.
- [x] Перенести `LearningRetry` в Learning module с canonical links на User,
  Content и SRS boundaries.
- [x] Перенести `TranscriptSegment` и `TranscriptSegmentTranslation` в Content
  module, сохранив transcript lexeme pivot и translation relations.
- [x] Перенести `ContentExamAttempt` и `GrammarExamAttempt` в Content module с
  сохранением readiness и grammar-confidence contracts.
- [x] Перенести `ClozeExample` в Content module и перевести lexeme controller
  на canonical exercise persistence.
- [x] Перенести `LearningPointEvent` и `LearningFlowMetricEvent` в Learning
  module для canonical analytics persistence.
- [x] Переписать Content → Learning → Srs → User integration через explicit
  contracts, canonical persistence, module-owned jobs и integration coverage.
- [x] Проверить failure/retry/idempotency paths для текущих критических flows:
  exercise processing, content processing, self-check и SRS review покрыты
  focused/full integration tests.

## Проверка

- Content processing → lexemes.
- Learning answer → Srs recalculation → due list.
- Auth/RBAC и admin visibility.

Новый focused integration slice: `ExerciseAttemptApiTest` и `SrsFlowTest` —
8 tests, 26 assertions passed. Добавлен SRS repository boundary; полный
backend suite проходит: 990 tests, 2973 assertions. Следующие
шаги — формализация invariants и перенос persistence ownership.

## Документация

- `docs/architecture/modules-and-events.md`.

## Зависимости

- Backend foundation; AI flows требуют AI platform contracts.
