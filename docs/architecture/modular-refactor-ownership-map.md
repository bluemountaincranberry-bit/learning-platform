# Modular refactor ownership map

Рабочая карта change `modular-architecture-refactor`. Она описывает перенос
владения и потребителей без изменения имён таблиц или данных.

## Зависимости до переноса

Статический подсчёт PHP `use App\Modules\...` показывает двусторонние связи:
Content↔Ai, Learning↔Srs, User↔Learning и Content↔Learning. Это ориентир для
миграции; количество imports само по себе не доказывает архитектурную ошибку.

| Текущее направление | Основная причина | Целевая граница |
|---|---|---|
| Content → Ai | analysis runs/candidates, запуск AI | typed analysis capability и результат |
| Ai → Content | models, catalog services, retrieval | Content read contracts и Actions |
| Learning → Srs | due/review state | Srs contract с DTO |
| Srs → Learning | points, retry, metrics | Learning review orchestration и events |
| User → Learning | relations к progress/flows | identity reference по userId |
| Learning → User | identity и учебные таблицы | User identity contract; учебные models принадлежат Learning |

## Models и таблицы

| Current owner/models | Таблицы/состояние | Target owner | Основные потребители | Проверка переноса |
|---|---|---|---|---|
| User: `User`, `UserLearningPreference` | users, user_learning_preferences | User | auth, profile, policies, Filament | auth/RBAC/profile tests, factories, policies |
| User: `UserLexemeProgress`, `UserLexemeConfidence`, `UserLexemeContextCheck`, `UserLexemeSkip`, `UserGrammarRule` | существующие user_* learning tables | Learning | Learning services/pages, recommendations, AI read tools | progress/self-check/recommendation tests; таблицы и данные неизменны |
| Content: `Content`, `ContentLexeme`, transcript models | contents, content_lexemes, transcript_* | Content | catalog, ingestion, Learning reads, AI input | transcript review, processing, public visibility, API contract tests |
| Content: `Lexeme*`, `Grammar*`, `ContentRuleLink`, exams | lexeme/grammar/catalog tables | Content | admin catalog, Learning, AI retrieval | grammar/lexeme CRUD, associations, permissions, catalog tests |
| Ai: `ContentLexemeCandidate`, `ContentGrammarCandidate` | content_*_candidates | Content | review UI, matching/apply workflow | candidate state, apply idempotency/rollback tests |
| Ai: `AiAnalysisRun` | ai_analysis_runs | Content for product lifecycle; AiExecution is separate technical record | processing, review UI, AI capability | snapshot/stale/partial-failure tests |
| Learning: attempts, progress, retry, flow, points, self-check | learning_*, exercise_attempts, self_check_* | Learning | learner API, SRS orchestration, analytics | integration tests for session/idempotency/rollback |
| Srs: `SrsCard`, `SrsReview` | srs_cards, srs_reviews | Srs | Learning review Action, due API | interval rules, ownership, review contract tests |
| Ai: conversations, messages, graphs, prompts, traces, pricing | текущие ai/agent/graph/prompt tables | Ai | tutor/admin/runtime | permission, graph resume, prompt version, tracing tests |
| Ai: embeddings/cache | embedding/cache indexes and tables | Ai Retrieval | Content/Learning read consumers | adapter contract, retrieval permission, indexing tests |
| Ai: `Lesson*` | lesson tables | Ai временно | lesson API/agent | existing lesson tests; будущий owner решается отдельно |
| Infrastructure: `EntityRevision`, `EventLog` | audit/integration log | Infrastructure | all module adapters | audit/serialization tests |
| New outbox/consumer receipt | новые additive tables | Infrastructure | critical event producers/consumers | forward/rollback migration, crash/retry/concurrency tests |

## API и интерфейсы

| Surface | Owner | Target consumer path | Проверка |
|---|---|---|---|
| `/auth/*`, `/profile` | User | User Actions/Queries/Resources | ApiAuth/profile/RBAC |
| `/content/*`, dictionary, grammar | Content | Content Actions/Queries/Resources | catalog, visibility, transcript, grammar/lexeme tests |
| `/learning/*`, `/self-check/*`, `/me/*` | Learning | Learning Actions/Queries | attempts, self-check, progress tests |
| `/srs/due` | Srs | Srs Query returning DTO | due/ownership/resource tests |
| `/srs/review` | Learning orchestration over Srs contract | Learning Action; route compatibility retained | atomic update, foreign card, duplicate operation test |
| `/ai/*`, tutor, lessons, practice | Ai plus owner capabilities | typed inputs/results; no ORM across boundary | permission, validation, timeout and controller tests |
| `/admin/ai-builder/*` | Ai admin adapter | public runtime/prompt contracts | graph/prompt permission and lifecycle tests |

`/srs/review` currently lacks an operation identifier; two identical requests
create two reviews. Adding idempotency therefore requires an explicit API
contract update and corresponding client migration.

## Jobs и события

| Current producer | Job/event | Target owner/delivery | Consumers | Проверка |
|---|---|---|---|---|
| Content | Fetch/Process/Translate jobs | Content Jobs; ids in payload | Content Actions, AI capability | retry, stale version, processing integration |
| Content | ContentSubmitted/ProcessingRequested | Content facts/commands separated | processing dispatcher, optional Kafka adapter | rollback/after-commit, serialization |
| Content | LexemeLearningStarted/Stopped | переименовать в Learning facts | Srs card listeners | duplicate delivery, ownership, card state |
| Learning/Srs | ExerciseCompleted | ReviewCompleted owned by Srs plus Learning outcome where applicable | metrics/Kafka as secondary consumers | atomic review, duplicate event, no double points |
| Ai | analysis/graph/embedding jobs | Ai Runtime/Capabilities; scalar ids/version | provider adapters and Content result Action | retry/timeout/stale/correlation tests |
| New Infrastructure | outbox dispatcher | at-least-once after commit | registered idempotent consumers | commit→queue failure, concurrent duplicate, exhausted retry |

## Dependency-ordered moves

1. Добавить executable boundary checks и characterization tests.
2. Стабилизировать User identity и Content read/write contracts.
3. Перенести учебные user_* models в Learning namespace без schema changes.
4. Сделать Learning владельцем review orchestration; Srs возвращает DTO и
   перестаёт импортировать Learning services.
5. Разделить AnalysisRun и AiExecution; перенести candidates/application в
   Content, сохранив таблицы.
6. Ввести outbox только для классифицированных critical events.
7. Перевести frontend consumers после стабилизации API; затем удалить aliases.

Каждый шаг обязан обновить перечисленных consumers, factories, policies,
service-provider bindings, queued payloads и тесты в одном завершённом slice.
Наличие класса в новом namespace без удаления старого consumer не считается
завершённым переносом.

## Неразрешённые факты

Текущий frontend mapping после переноса: 1 infrastructure API file в
`spa/api` (`client.ts`) и domain-owned API files в `domains/*/api`; imports
проверяются `vue-tsc` и frontend boundary checker.

- Окончательное владение Lesson оставлено вне этого change.
- Required `operation_id` для review требует отдельного подтверждения API.
- Critical/noncritical классификация каждого существующего события должна
  быть утверждена в event catalog до добавления outbox.
- Dynamic SQL, Filament runtime resolution и serialized queued jobs требуют
  runtime tests; статический import graph их полностью не видит.
