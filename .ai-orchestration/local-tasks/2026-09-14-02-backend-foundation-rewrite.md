# Rewrite: backend foundation

## Цель

Создать единый стандарт Laravel-модулей, contracts, API boundaries и
application/domain/infrastructure ответственности.

## Scope

- Стандартизировать module structure и providers.
- Перенести ownership моделей, jobs, events, policies и resources.
- Уточнить DTO, validation, API Resources и error responses.
- Ввести явные cross-module contracts.
- Добавить dependency checks.

## Out of scope

- AI platform internals.
- Frontend rewrite.
- Microservices.

## План

- [x] Вынести AI provider construction в `AiProviderFactory`.
- [x] Перенести YouTube integration wiring из глобального provider в Content module.
- [x] Создать Content-owned queue jobs и временные compatibility wrappers.
- [x] Переключить Content orchestrator на module-owned queue jobs.
- [x] Перенести AI embedding/enrichment jobs в `Modules/Ai/Interfaces/Jobs`;
  root names оставлены только как deprecated compatibility wrappers.
- [x] Перенести exercise processing в Learning и transcript translation в
  Content; root names оставлены только как deprecated compatibility wrappers.
- [x] Перевести runtime consumers batch content analysis на
  `ContentAnalysisCapability`.
- [x] Перенести Content aggregate в module-owned canonical model с временным
  `App\\Models\\Content` compatibility alias и обновить module consumers.
- [x] Перенести User aggregate в module-owned canonical model с временным
  `App\\Models\\User` compatibility alias, сохранив Sanctum/Filament/Spatie
  guard и morph compatibility.
- [x] Перенести `UserLearningPreference` в User module с legacy alias и
  переключить Learning consumers на canonical model.
- [x] Перенести `UserLexemeProgress` в User module с legacy alias и
  переключить Content/Learning/student-AI consumers на canonical model.
- [x] Перенести `UserLexemeSkip`, `UserLexemeConfidence` и
  `UserLexemeContextCheck` в User module с legacy aliases и переключить
  Content/Learning/AI consumers.
- [x] Перенести `UserGrammarRule` в User module с legacy alias и переключить
  grammar progress/readiness и AI consumers.
- [x] Перевести module-level User type dependencies на canonical User model;
  legacy User оставлен только на compatibility edge.
- [x] Перенести `ContentLexeme` в Content module с legacy alias и перевести
  Content/Learning/AI/SRS consumers и typed test contracts на canonical model.
- [x] Перенести `Lexeme` в Content module с legacy alias и перевести
  vocabulary/AI/Learning/SRS consumers и typed test contracts на canonical model.
- [x] Перенести `LexemeExample`, `LexemeTranslation`, `LexemeSense` и
  `LexemeAssociation` в Content module с legacy aliases и обновить catalog
  consumers.
- [x] Перенести `GrammarRule` и `GrammarTopic` в Content module с legacy
  aliases и перевести grammar catalog/progress/AI consumers.
- [x] Перенести `SrsCard` и `SrsReview` в SRS module с legacy aliases и
  перевести repository, Learning и AI consumers.
- [x] Перенести `ContentTokenizer` в Content application layer с legacy alias
  и перевести AI analysis consumer на canonical service.
- [x] Перенести YouTube oEmbed title adapter в Content infrastructure
  integration boundary с сохранением `VideoTitleFetcherInterface`.
- [x] Перенести YouTube transcript providers в Content infrastructure
  integration boundary с сохранением fallback и provider contracts.
- [x] Написать contract tests для новых module boundaries через provider and
  capability binding tests.
- [x] Создать target module structure для оставшихся root jobs/services/models;
  root Jobs/Services are now compatibility wrappers around module-owned code.
- [x] Переписать foundation use cases и HTTP boundaries в module application
  и interfaces layers; shared requests/resources остаются только transport
  support.
- [x] Перенести persistence/background ownership в canonical module models,
  module-owned jobs и explicit repository boundaries.

## Проверка

- Focused feature/integration tests и `git diff --check`.

Текущий результат: полный backend suite после User aggregate, learner-state и
lesson-analysis persistence migrations — 990 passed, 2973 assertions.
focused content/AI/domain slices также проходят после AI job ownership
migration and embedding persistence migration.

## Документация

- `docs/architecture/modules-and-events.md`.

## Зависимости

- Baseline task.
