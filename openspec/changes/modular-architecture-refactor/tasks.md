## 1. Baseline и контракты завершения

- [x] 1.1 Зафиксировать текущий diff, карту затронутых файлов и baseline Laravel/typecheck/build; проверка: отчёт с командами, exit codes и отдельно существующими сбоями, без отката чужих изменений.
- [x] 1.2 Составить dependency/ownership map моделей, таблиц, API, jobs и событий; проверка: каждый перенос из design имеет владельца, потребителей и тестовый сценарий.
- [x] 1.3 Опубликовать словарь и naming guide из design; проверка: примеры PHP/TS/API различают Lexeme, ContentLexeme, candidate, attempt, review и execution.
- [x] 1.4 Подготовить characterization tests для принятия transcript, применения кандидатов и review; проверка: текущий результат основного, отказного и повторного сценария зафиксирован, найденные дефекты отделены от ожидаемого поведения.

## 2. Архитектурные ограничения (после 1)

- [x] 2.1 Добавить PHP dependency checks и точечный временный baseline нарушений; проверка: positive/negative fixtures распознают private import и цикл, новые нарушения отклоняются.
- [x] 2.2 Добавить TS import checks для pages/domains/shared/infrastructure; проверка: отрицательные fixtures ловят HTTP из page и business import из shared.
- [x] 2.3 Добавить naming/file conventions checks с документированными framework exceptions; проверка: fixtures ловят неверный публичный символ, корректные исключения проходят.

## 3. User и Content (после 2)

- [x] 3.1 Перенести User identity/profile модели и адаптеры в целевую структуру; проверка: auth/RBAC/profile tests и корректные factories/policies.
- [x] 3.2 Разделить Content ingestion/readiness на Actions/Queries/Rules; проверка: transcript acceptance, публикация и запрет доступа к непубличному контенту.
- [x] 3.3 Разделить управление grammar topics/rules на предметные сценарии; проверка: CRUD, permissions и сохранение связей покрыты feature tests.
- [x] 3.4 Разделить lexeme CRUD/associations/examples и query coverage; проверка: catalog tests и запрет cross-module persistence writes.
- [x] 3.5 Перенести владельца кандидатов и их применения в Content с сохранением таблиц; проверка: Content-owned candidate models/contract, contract-only consumers, boundary guard и 33 candidate tests (accepted/rejected/applied, idempotency, rollback).

## 4. Learning и SRS (после 3)

- [x] 4.1 Перенести progress/confidence/context-check state из User в Learning, обновить relations/factories; проверка: таблицы и существующие данные сохраняются, progress tests проходят.
- [x] 4.2 Выделить SRS Contracts/Data и чистые interval/grade rules; проверка: contract tests и граничные оценки/интервалы, публичные DTO не содержат Eloquent.
- [x] 4.3 Перенести координацию review в Learning Action и удалить обратные SRS→Learning services; проверка: dependency check и атомарный rollback review/history/обязательных outcomes.
- [x] 4.4 Разделить self-check, training session и retry сценарии; проверка: повтор submission, чужой ресурс и завершение сессии покрыты focused integration tests.
- [x] 4.5 Обновить контроллеры, jobs и Filament consumers новых контрактов; проверка: relevant route tests и container resolution без legacy model aliases.

## 5. AI execution (после 3–4)

- [x] 5.1 Ввести типизированные capability inputs/results/errors вместо передачи Eloquent; проверка: consumer contract tests и rejection невалидного результата.
- [x] 5.2 Перенести OpenAI/Ollama adapters и provider bindings; проверка: общий adapter contract suite на fake transport, неподдерживаемая capability даёт явную ошибку.
- [x] 5.3 Выделить prompt rendering/versioning из content analysis; проверка: fixtures сохраняют языки, schema и обязательные product constraints.
- [x] 5.4 Выделить анализ snapshot и сохранение результатов владельцем Content; проверка: stale transcript и partial failure не применяют устаревшие/частичные данные.
- [x] 5.5 Унифицировать execution timeout/retry/error/usage tracing; проверка: timeout, rate-limit, unavailable usage и отсутствие секретов в logs.
- [x] 5.6 Привести agents/tools к авторизованным Actions владельцев; проверка: denied tool, повтор operationId и отсутствие прямых записей AI в Content/Learning/SRS.
- [x] 5.7 Разделить graph runtime, retrieval и embeddings по целевым зонам; проверка: resume/failed branch, retrieval permissions и смена embeddings adapter без изменения consumers.
- [x] 5.8 Подготовить воспроизводимые capability evaluation fixtures и критерии качества; проверка: отчёт по существующим eval cases, изменения качества отделены от инфраструктурных tests, live paid eval отдельно согласуется.

## 6. Бизнес-события (после 4–5)

- [x] 6.1 Зафиксировать event catalog, payload versions и critical/noncritical delivery для каждого producer/consumer; проверка: нет ORM payload и неописанных обязательных побочных эффектов.
- [x] 6.2 Подготовить и проверить outbox migration без удаления данных; проверка: forward/rollback на disposable БД, constraints и сохранение payload вместе с фактом.
- [x] 6.3 Реализовать dispatcher/retry и видимость failed delivery; проверка: commit→queue failure и crash после enqueue восстанавливаются без потери события.
- [x] 6.4 Добавить atomic consumer deduplication и version checks; проверка: concurrent duplicate, rollback handler и out-of-order delivery.
- [x] 6.5 Подключить TranscriptAccepted/ContentAnalysisCompleted/ContentPublished и review secondary effects; проверка: end-to-end обработка после commit, отсутствие события при rollback и двойных points/candidates.

## 7. Frontend infrastructure и Content/User (после 2, затем стабильных API 3–6)

- [x] 7.1 Завершить app/routes composition, HTTP error mapping и QueryClient lifecycle; проверка: bootstrap/auth guard tests, logout и late response не раскрывают private data.
- [x] 7.2 Перенести Content API/types/queries/mutations внутрь domain; проверка: catalog/detail/filter/cache invalidation tests и отсутствие root imports.
- [x] 7.3 Перенести User profile/auth adapters с единственным источником remote profile; проверка: update profile/account switch и удаление дублирующего server state.
- [x] 7.4 Вынести общие async/form/dialog primitives и tokens; проверка: `AsyncState`, `UiInput`, `UiButton`, `UiDialog`, `shared-ui.test.mjs`, representative Study/Content Details/My Words screens и 8-flow browser smoke.

## 8. Frontend Learning/SRS и AI/Admin (после 7)

- [x] 8.1 Перенести training/self-check/progress API и model в Learning; проверка: session transitions сохраняют локальный шаг и не дублируют remote cache.
- [x] 8.2 Выделить SRS domain и review mutations; проверка: повтор submit и обновление due queue в нескольких представлениях.
- [x] 8.3 Перенести tutor/lessons/recommendations в AI domain; проверка: ошибки, streaming/cancellation где поддерживаются, смена пользователя и permissions.
- [x] 8.4 Привести admin AI builder API/model/UI к общему domain pattern; проверка: graph save/run/status и prompt edit/version permissions.
- [x] 8.5 Разбить RepetitionsPage и ContentDetailsPage на page shells и предметные компоненты; проверка: E2E catalog→study→review без изменения пользовательских возможностей.
- [x] 8.6 Разбить GraphCanvasPage и PromptEditorPage; проверка: domain components (`GraphCanvasNode`, `DraftSaveBar`, `PromptMetadataFields`), 8-flow E2E для prompt/graph save и unsaved state, frontend typecheck/build.

## 9. Финальная миграция и доказательства (после 3–8)

- [x] 9.1 Удалить временные aliases, корневые API/composable re-exports и migration baseline исключения; проверка: ноль запрещённых зависимостей, provider/container/factory/morph checks проходят.
- [x] 9.2 Проверить полный mapping SQL/API/TS и naming review; проверка: контрактные fixtures и словарь согласованы, identifiers не подменяются.
- [x] 9.3 Запустить make test, frontend typecheck, make npm ARGS="run build" и расширенный browser smoke; проверка: записать фактические результаты и незакрытые failures, не переносить старые counts как новые.
- [x] 9.4 Проверить перенос на disposable БД с существующими данными и queued jobs; проверка: нет потери данных, описаны queue drain и rollback.
- [x] 9.5 Обновить module ownership, ADR, frontend spec, event/AI operations docs и rewrite completion report; проверка: каждый requirement из пяти specs связан с tests/review evidence, все задачи реально завершены перед архивированием.
