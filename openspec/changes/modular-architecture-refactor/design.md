## Context

Мотивация: proposal.md. Это следующий этап после первоначального rewrite, а не подтверждение его полного соответствия DDD/SOLID. В рабочем дереве уже много незакоммиченных изменений; исходная точка реализации должна быть отдельно зафиксирована без их отката.

Проверенные примеры:
- `src/app/Modules/Ai/Contracts/ContentAnalysisCapability.php` принимает Eloquent AiAnalysisRun.
- `Ai/Application/AiContentAnalysisService.php` объединяет чтение Content, prompts, inference и обработку результатов.
- `Ai/Application/AiCandidateApplyService.php` меняет каталог Content.
- `Srs/Application/SrsService.php` зависит от конкретных Learning services; Learning вызывает SRS.
- `spa/domains/content/model/contentQueries.ts` импортирует API из корневого дерева; learning/index.ts переэкспортирует старые composables.
- В `/home/user/Projects/365/s365_Frontend/app/Views/assets/domains/CustomLicenses/model/` queries, mutations и keys разделены. Это пример, не нормативная копия.

## Goals / Non-Goals

**Goals:** настоящая инкапсуляция состояния, понятные сценарии, типизированные границы, безопасные события, завершённая frontend миграция и проверяемый naming.

**Non-Goals:** изоляция всего кода от Laravel, Entity-копия для каждой Eloquent-модели, CQRS infrastructure/event sourcing, универсальная plugin-система и смена UI stack. Продуктовое поведение сохраняется, кроме явно описанных исправлений надёжности событий и cache consistency.

## Decisions

### 1. Laravel-native modules

Единая структура: `Modules/<Module>/{Actions,Queries,Models,Data,Rules,Contracts,Events,Jobs,Http/{Controllers,Requests,Resources},Policies}` и `<Module>ServiceProvider.php`. Создаются только используемые каталоги. Один Action выражает один сценарий; Query не изменяет бизнес-состояние. Rules/ValueObjects содержат сложные вычисления и инварианты без HTTP/контейнера; Eloquent остаётся в Models владельца.

Альтернативы: только перемещение namespaces сохраняет связанность; полное Entity/Repository отображение для каждой таблицы увеличивает стоимость изменений без доказанной пользы. Репозитории вводятся для сложного persistence-контракта, не для дублирования Eloquent API.

### 2. Владение и зависимости

| Владелец | Ответственность |
|---|---|
| User | Identity, доступ, профиль, пользовательские настройки |
| Content | Контент, transcript, каталог лексики/грамматики, кандидаты и их принятие |
| Learning | Учебные сессии, попытки, прогресс, confidence, retries, points |
| Srs | Карточки повторения, интервалы, история review |
| Ai | Выполнение AI, prompts, provider configuration, runtime traces и usage |

Это целевое предложение переносит учебные модели из User в Learning, а продуктовые кандидаты из Ai в Content; имена таблиц и данные сохраняются. Lesson остаётся в текущем владельце на этом этапе: выделение отдельного учебного aggregate требует отдельного продуктового решения.

Межмодульные вызовы используют только Contracts и их Data; ORM-модели не пересекают публичную границу. DTO содержат необходимые значения, не lazy-loaded relations. Приватные Eloquent relations внутри владельца допустимы; cross-module чтение идёт через query contracts. Прямые SQL-записи в чужие таблицы запрещены.

Learning координирует пользовательский review через SRS contract, включая атомарно необходимые изменения. SRS возвращает ReviewOutcome и не импортирует Learning services. Уведомления и аналитика реагируют на события. Если контрактам нужны встречные зависимости, оркестрация поднимается в конкретный сценарий, а не скрывается в контейнере.

Filament и существующие admin SPA экраны сохраняются как адаптеры тех же Actions/Queries. Перераспределение экранов между ними исключено из scope.

### 3. AI capabilities и runtime

Зоны: `Capabilities/<Capability>`, `Providers/<Provider>`, `Prompts`, `Runtime/{Agents,Tools,Graphs}`, `Retrieval`, `Evaluation`, `Observability`. Внутри используются общие правила Contracts/Data/Actions; провайдерный SDK доступен только адаптерам.

Пример границы: Content создаёт AnalysisInput со snapshot transcript, языками и опциями; AI возвращает AnalysisResult с типизированными кандидатами и usage. Content валидирует продуктовые ограничения и сохраняет результат. Принятие кандидатов — отдельный Content Action. Runtime execution и business analysis имеют разные идентификаторы, связанные correlationId.

Общие request/result типы не содержат provider SDK типов и Eloquent. Capability проверяет форму результата и ошибки, адаптер сообщает поддерживаемые возможности; неподдерживаемая функция возвращает явную ошибку. Provider selection использует registry/bindings без union конкретных клиентов в публичном контракте.

Timeout, ограниченные retries, cancellation где поддерживается, schema validation и permission checks централизованы в execution pipeline. При изменении snapshot во время inference результат помечается устаревшим и не применяется автоматически. AI не вызывается внутри долгой транзакции БД. После исчерпания попыток ошибка видима оператору; автоматического бесконечного fallback нет. Смена модели допускается только явной политикой capability.

Tools с записью вызывают авторизованные Actions владельца от имени пользователя. Повторный tool call получает operationId и не повторяет уже применённый эффект. Prompts и полные пользовательские тексты не пишутся в обычные logs; trace фиксирует версию prompt, provider/model, latency, outcome и usage либо явную недоступность метрик.

### 4. Event-driven внутри монолита

Различать команду с намерением и событие с состоявшимся фактом. Event содержит eventId, occurredAt, schemaVersion, correlationId, actorId при наличии и минимальные предметные значения; Eloquent не является payload.

| Событие | Владелец | Реакция |
|---|---|---|
| TranscriptAccepted | Content | Постановка анализа через Content orchestration |
| ContentAnalysisCompleted | Content | Обновление состояния review кандидатов/UI |
| ReviewCompleted | Srs | Некритичная аналитика, после атомарного review |
| ContentPublished | Content | Обновление поиска |

Для критичной доставки при фиксации факта сохраняется transactional outbox в той же БД-транзакции. Dispatcher публикует после commit, повторяет сбои и отслеживает доставку. Это локальная таблица и очередь, не новый broker. Обычный afterCommit допустим для восстанавливаемых вторичных эффектов; он сам не закрывает crash между commit и enqueue.

Доставка at-least-once. Consumer deduplication имеет unique (consumer,eventId); локальный эффект и отметка обработки атомарны. Для внешних действий используются idempotency key провайдера или reconciliation; exactly-once не обещается. Snapshot/version защищает от устаревшего и переставленного события. Повторная доставка не начисляет points и не применяет кандидатов снова.

Альтернатива «всё через события» скрывает control flow и ломает атомарность. Обязательные операции одного ответа выполняются синхронно; distributed transaction не вводится.

### 5. Frontend domains

`spa/{app,routes,pages,widgets,domains,shared,infrastructure}`. Router подключается в app; route definitions/names/guards живут в routes. Domain: `domains/<kebab-case>/{api,model,ui,lib,index.ts}`. API/types/queries/mutations физически переносятся из корня; barrel экспортирует реальную domain implementation.

Pages/widgets используют публичные domain entrypoints. Междоменные UI композиции живут в widgets/pages; shared не импортирует domains. Infrastructure не импортирует бизнес-модули; auth callback настраивается при bootstrap.

TanStack Query владеет remote data; Pinia и локальное состояние — текущим шагом тренировки, draft и UI preferences. Profile не дублируется в двух caches. Query keys учитывают параметры и пользователя для персональных данных; logout/account switch очищает private cache и отменяет старые requests. Mutations обновляют/инвалидируют конкретные query families. Retry исключает повтор неидемпотентных writes без operationId. Generated API client не добавляется: typed DTO/mappers сохраняются и проверяются контрактами.

Сохраняются strict TypeScript, Reka UI/Tailwind/CVA. Общие tokens и primitives закрывают loading/error/empty/retry, forms/dialogs, focus/keyboard. Repetitions, ContentDetails, GraphCanvas и PromptEditor разбиваются по ответственности без искусственного лимита строк.

### 6. Naming и словарь

| Термин | Смысл |
|---|---|
| Lexeme | Каноническая словарная единица |
| ContentLexeme | Связь лексемы с конкретным контентом |
| LexemeCandidate | Предложение, ещё не применённое к каталогу |
| ExerciseAttempt | Отдельная попытка упражнения |
| Review | Оценка воспроизведения в SRS |
| LearningSession | Состояние прохождения учебной последовательности |
| AnalysisRun | Продуктовый запуск анализа контента |
| AiExecution | Техническое выполнение AI |

PHP classes и Vue components — PascalCase, переменные/методы — camelCase, SQL и текущий wire JSON — snake_case. Между wire и frontend camelCase используются явные mappers. Acronym style: Ai, Srs, Http, Id в именах классов; aiExecutionId в переменных. PHP module directories — PascalCase, frontend domains — kebab-case.

Actions: глагол + предмет (`ApplyLexemeCandidates`), события — прошедший факт (`ReviewCompleted`), queries — `useContentListQuery`, mutations — `useSubmitReviewMutation`. Файлы: `content.api.ts`, `content.queries.ts`, `content.mutations.ts`, `content.queryKeys.ts`, `content.types.ts`; Vue — `ContentCard.vue`.

Plural для коллекций, `is/has/can` для boolean; `Id` показывает конкретный предмет. Запрещены бессодержательные новые BaseService/CommonManager/Helpers и произвольные сокращения в публичных контрактах. Это не запрет локальных `result`/`index` в очевидном контексте. Линтер проверяет форму, review — предметный смысл.

### 7. Проверяемые границы

Архитектурные тесты анализируют зависимости PHP и TS, запрещают private imports и циклы, проверяют отсутствие legacy aliases/root API consumers. Положительные и отрицательные fixtures доказывают, что checks действительно ловят нарушение. Семантика dynamic SQL требует дополнительно review; grep не считается полной гарантией.

## Risks / Trade-offs

- Большой dirty worktree → зафиксировать baseline перечня файлов, менять по bounded slices, не откатывать чужой код.
- Перенос Models → проверить factories, morph maps, policies, bindings, Filament и queued payloads; перед switch осушить локальные очереди или мигрировать формат payload.
- Outbox добавляет таблицы и эксплуатацию → узкое применение, migration/rollback и тесты crash/retry; включить это schema change в implementation review.
- Типизированные границы увеличивают mapping → только публичные DTO, без копирования всех полей моделей.
- Старые frontend specs частично пересекаются → здесь tasks — единый список для этого change, после реализации обновить прежние docs с точным scope.

## Migration Plan

1. Baseline, словарь и dependency map; зафиксировать существующие проверки и симптомы отдельно.
2. Контракты и архитектурные checks; миграция User/Content/Learning/SRS по сценариям.
3. AI execution и перенос продуктового применения; outbox и consumer protections.
4. Frontend инфраструктура, User/Content, Learning/SRS, AI/Admin, общие UI.
5. Удаление compatibility paths, полный regression и обновление completion report.

Новые schema/API изменения перечислять до соответствующего implementation slice; destructive reset требует отдельного разрешения. Сохранять существующие данные. Откат — обратно по отдельным завершённым slices и совместимым migrations, без reset рабочего дерева. Незавершённый slice и неподтверждённая проверка не дают статуса complete.
