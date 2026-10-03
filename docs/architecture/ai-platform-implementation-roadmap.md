# AI Platform — Implementation Roadmap (Epics → Tasks → миграции/классы/тесты)

## 0. Как читать этот документ

Это исполняемый backlog поверх `ai-platform-vision.md` (архитектура и
принципы) и `adr/` (обоснования решений). Здесь — **что конкретно делать и
в каком порядке**: эпики → задачи → миграции БД, классы, тесты. Каждая
задача рассчитана примерно на один PR.

Документ **не изобретает новую механику** — почти всё уже спроектировано в
`agent-framework-roadmap.md` (мини-шаги 5.1-5.22) и
`docs/product/ai-engineering-learning-roadmap.md` (фичи 1-7). Задача этого
файла — свести оба списка в один сквозной, правильно упорядоченный backlog
с конкретными артефактами, без противоречий между источниками. Там, где
задача — прямое продолжение уже описанного мини-шага, в колонке "Источник"
стоит ссылка вместо повторения обоснования.

**Обозначения:** `EPIC-N.M` — задача M внутри эпика N. Таблицы читаются
сверху вниз как порядок внутри эпика; порядок между эпиками — раздел 1.

## 1. Порядок эпиков

```mermaid
flowchart LR
    E1["EPIC 1<br/>AI Core Runtime"] --> E3["EPIC 3<br/>TutorAgent MVP"]
    E1 --> E2["EPIC 2<br/>RAG & Knowledge"]
    E2 -.усиливает.-> E3
    E3 --> E4["EPIC 4<br/>Agent System & Workflow"]
    E4 --> E5["EPIC 5<br/>Production AI"]
    E3 --> E6["EPIC 6<br/>Chat UX & Observability"]
    E5 -.наблюдаемость для 6.8.-> E6
    E7["EPIC 7<br/>Word Level & Bulk Mgmt"]
    E4 --> E8["EPIC 8<br/>AiAnalysisGraph Cutover"]
    E9["EPIC 9<br/>AI-First Extraction & Review"]
    E9 --> E10["EPIC 10<br/>Lexical Sense Model"]
```

- **EPIC 9 не зависит от других AI-эпиков технически** (использует уже
  существующие `AiContentAnalysisService`/`AiCandidateApplyService` как
  есть) — но логически продолжает разговор про EPIC 7 (уровень слова) и
  меняет, кто может подтверждать AI-кандидатов (не только admin/Filament).
- **EPIC 8 требует EPIC 4** (граф `AiAnalysisGraph` и `AgentGraphRuns`-страница
  из 6.8 уже должны существовать) — только закрывает гэпы и даёт бете кнопку,
  живой путь не заменяет.
- **EPIC 7 не зависит ни от одного AI-эпика** — это доработка существующего
  экрана слов, использует только уже существующий `AiExplainLexemeService`.
  Можно вести в любой момент, даже раньше остальных — здесь идёт последним
  просто по порядку появления в разговоре, не по технической зависимости.
- **EPIC 1 обязателен первым** — всё остальное строится на `AgentLoop`/
  `AgentBlueprint`/трейсинге.
- **EPIC 6 идёт после EPIC 3** (нужен базовый чат/стриминг/квиз-тул), задача
  6.8 полноценно раскрывается только после EPIC 5 (нужны `agent_traces`/
  `cost_usd`) — но остальные задачи EPIC 6 можно вести и без него.
- **EPIC 2 можно вести параллельно с EPIC 3** — RAG-инфраструктура не
  блокирует старт TutorAgent (первые Tools работают на прямом SQL, RAG —
  апгрейд конкретных инструментов позже, без изменения их интерфейса).
- **EPIC 4 требует EPIC 3** — граф и handoff нужны специализированным
  агентам, которых ещё нет без TutorAgent MVP.
- **EPIC 5 — в конце**, кроме отдельных пунктов, которые можно делать раньше
  без риска (например, перевод кэша на Redis — независим от всего).
- **EPIC 10 требует стабильный EPIC 9** (опирается на живой auto-apply-путь
  `AiCandidateApplyService`/`AiCandidateAutoApplyService`, который 9.8 сделал
  единственным способом попадания слов в каталог) — но не требует ни одного
  из agent-эпиков (1-6, 8); можно вести параллельно с ними.

## 2. EPIC 1 — AI Core Runtime (фундамент)

Цель: превратить `ContentAgentService` из единственного жёстко зашитого
агента в переиспользуемый рантайм, на котором можно построить второго
агента, не переписывая ядро. Ничего пользователю ещё не видно — это
инфраструктурный эпик.

| # | Задача | Делаем | Миграции | Классы | Тесты | Источник |
|---|---|---|---|---|---|---|
| 1.1 | Страховочная сетка | Characterization-тесты текущего `ContentAgentService` с фейковым `AiToolCallingClient` (обычный ответ, один tool call, цепочка, ошибка инструмента, `MAX_ITERATIONS`) | — | — | `ContentAgentServiceTest` (сценарные) | `agent-framework-roadmap.md` 5.1 |
| 1.2 | Чистый `AgentLoop` | Вынести цикл из `ContentAgentService::handleTurn()`, без Eloquent, через `AgentLoopObserver` | — | `Agent/AgentLoop.php`, `Agent/Contracts/AgentLoopObserver.php` | Юнит-тесты `AgentLoop` изолированно от Eloquent | 5.2 |
| 1.3 | Безопасность как инвариант | `sideEffect` (`read_only`/`draft_only`/`publish`) в `AgentToolDefinition`, проверка на этапе wiring в `AiServiceProvider` | — | Правка `Agent/Data/AgentToolDefinition.php`, `Agent/Contracts/AgentTool.php` | Тест: попытка подключить `publish`-tool без разрешения роняет boot-процесс | 5.3 |
| 1.4 | `AgentBlueprint` | Декларативная конфигурация агента (name, systemPrompt, tools, maxIterations, allowedSideEffects) | — | `Agent/Data/AgentBlueprint.php` | Юнит-тест валидации блюпринта | 5.4 |
| 1.5 | Переключить `ContentAgentService` | На `AgentLoop` + `AgentBlueprint`, тесты из 1.1 остаются зелёными без изменений | — | Правка `ContentAgentService.php` | Тесты 1.1 без изменений | 5.5 |
| 1.6 | `TraceContext`/`SpanRecorder` | Прокинуть через `AgentLoop`, дефолт `NullSpanRecorder` (no-op, паттерн как `NullKafkaProducer`) | — | `Agent/Tracing/TraceContext.php`, `Agent/Tracing/SpanRecorder.php`, `Agent/Tracing/NullSpanRecorder.php` | Тест: `NullSpanRecorder` не падает, ничего не пишет | 5.12 |
| 1.7 | Хранение трейсов | БД-реализация `SpanRecorder` + Artisan-отчёт "дерево по trace_id" / "стоимость за неделю по agent_type" | `create_agent_trace_spans_table` | `Agent/Tracing/DatabaseSpanRecorder.php`, `Console/Commands/AgentTraceReportCommand.php` | Feature-тест: спаны пишутся с правильным `parent_span_id` | 5.13 |
| 1.8 | Guardrails рантайма | Границы `<tool_output>` в system prompt для инструментов, читающих внешний текст; rate limit на постановку `RunAgentTurnJob` в очередь на границе контроллера | — | Правка system prompt builder'ов, правка контроллера/форм-реквеста | Тест: превышение лимита не ставит job в очередь | 5.9 |
| 1.9 | `agent_type` + обобщённый job | Дискриминатор на `agent_conversations`, `RunAgentTurnJob` резолвит сервис по `agent_type` (реестр в конфиге) | `add_agent_type_to_agent_conversations_table` | Правка `RunAgentTurnJob.php`, запись в `config/ai.php` (`agent.registry`) | Тест: job вызывает правильный сервис по `agent_type` | 5.6 (часть), 5.7 |
| 1.10 | Скелет второго агента | `StudentTutorAgentService` + пустой/минимальный `AgentBlueprint`, один read-only tool (`GetUserLevelTool`) как доказательство, что рантайм работает на втором агенте | — | `Agent/StudentTutorAgentService.php`, `Agent/Tools/Student/GetUserLevelTool.php` | Сценарный тест: TutorAgent отвечает на простой вопрос через один tool call | 5.6 (proof) |

**Definition of Done эпика**: оба агента (`ContentAgentService`,
`StudentTutorAgentService`) работают через один `AgentLoop`, трейсинг пишется
для обоих, `sideEffect` проверяется на этапе wiring, а не в рантайме.

## 3. EPIC 2 — RAG & Knowledge

Цель: перевести поиск по учебным материалам с плоского SQL/`LIKE` на
семантический поиск, не трогая уже существующий каталоговый matching
(`CandidateMatchingService` остаётся как есть — см. `ai-platform-vision.md`
9.3, п.1).

| # | Задача | Делаем | Миграции | Классы | Тесты | Источник |
|---|---|---|---|---|---|---|
| 2.1 | Определить корпус | Стартовый RAG-корпус — `GrammarRule.body` (markdown-объяснения) + `lexeme_examples`; новый контент-тип не заводить, пока не появится реальная нехватка материала | — | — | — | vision 9.3 |
| 2.2 | ES-индекс | Индекс с `dense_vector`-полем под эмбеддинги корпуса, отдельный от индекса semantic memory (2.6) | — | `config/elasticsearch.php` (маппинг индекса) | — | vision 9.3 |
| 2.3 | Индексация корпуса | Artisan-команда, по аналогии с `ComputeCanonicalLexemeEmbeddingsJob`: посчитать эмбеддинги `GrammarRule.body`/`lexeme_examples`, записать в ES | — | `Console/Commands/IndexRagCorpusCommand.php`, `Modules/Ai/Application/RagIndexingService.php` | Feature-тест: после команды документ находится по векторному запросу | — |
| 2.4 | Retrieval-сервис | `embed(query) → ES kNN → top-K документов`, с обёрткой `<tool_output>` границ (та же митигация prompt injection, что в 1.8) | — | `Modules/Ai/Application/RagRetrievalService.php` | Тест: нерелевантный документ не попадает в top-K на fixture-корпусе | vision 9.3 |
| 2.5 | Апгрейд существующих Tools | `ExplainGrammarTool`/`FindExamplesTool` (EPIC 3) используют `RagRetrievalService` вместо голого SQL — интерфейс `AgentTool` не меняется, меняется только реализация `execute()` | — | Правка `Agent/Tools/Student/ExplainGrammarTool.php`, `FindExamplesTool.php` | Существующие тесты Tools остаются зелёными | — |
| 2.6 | Semantic memory индекс | Отдельный ES-индекс под наблюдения об ученике ("постоянно путает Present Perfect") — **зависит от EPIC 4.13** (Kafka-событие, которое его наполняет) | — | `Modules/Ai/Application/SemanticMemoryService.php` | — | vision 9.1 |

**Definition of Done эпика**: `FindExamplesTool`/`ExplainGrammarTool` отвечают
через RAG, а не через `LIKE`-поиск; каталоговый matching не тронут.

## 4. EPIC 3 — TutorAgent MVP

Цель: первый реально полезный сценарий для ученика — TutorAgent, которому
можно задать вопрос и получить ответ, заземлённый на его собственных данных.

| # | Задача | Делаем | Миграции | Классы | Тесты | Источник |
|---|---|---|---|---|---|---|
| 3.1 | `TutorAgent` blueprint | System prompt, `maxIterations`, `allowedSideEffects = [read_only, draft_only]` | — | Правка `AgentBlueprint`-регистрации в `AiServiceProvider` | — | vision 3 |
| 3.2 | Memory tools | `GetUserMistakesTool` (из `srs_reviews`), `GetLearningHistoryTool` (`user_lexeme_progress`), `GetWeakTopicsTool` (агрегат) | — | `Agent/Tools/Student/{GetUserMistakesTool,GetLearningHistoryTool,GetWeakTopicsTool}.php` | По тесту на инструмент (моки данных, проверка `sideEffect=read_only`) | vision 4 |
| 3.3 | Progress tools | `GetVocabularySizeTool`, `GetReviewScheduleTool` (`GetUserLevelTool` уже есть из 1.10) | — | `Agent/Tools/Student/{GetVocabularySizeTool,GetReviewScheduleTool}.php` | Аналогично 3.2 | vision 4 |
| 3.4 | Learning tools | `SearchVocabularyTool` (SQL сначала), `ExplainGrammarTool`, `FindExamplesTool` (последние два — апгрейд на RAG в 2.5, когда готово) | — | `Agent/Tools/Student/{SearchVocabularyTool,ExplainGrammarTool,FindExamplesTool}.php` | Аналогично 3.2 | vision 4 |
| 3.5 | HTTP + SPA | Новый эндпоинт `POST /api/tutor/conversations` (переиспользует `agent_conversations`/`agent_messages` с `agent_type=student_tutor` из 1.9), новая страница/переиспользование `ChatPage.vue` под новый agent_type | — | `Modules/Ai/Interfaces/Http/Controllers/TutorConversationController.php`, правка `ChatPage.vue`/`chatApi.ts` | Feature-тест эндпоинта, access control (только `role=student`) | vision 3, 9.1 |
| 3.6 | Streaming | SSE для ответа TutorAgent (не трогаем старый `ChatContextAiService` — он остаётся как есть, стриминг строится сразу в новом пути) | — | Правка `OpenAiClient` (`stream: true` вариант), `StreamedResponse` в контроллере | Feature-тест: эндпоинт отдаёт `text/event-stream` | `ai-engineering-learning-roadmap.md` шаг 1 |
| 3.7 | `GenerateQuizTool` | Structured output (`AiJsonClient::completeJson`, готовый паттерн из `AiContentAnalysisService`) — как Tool, не Agent (см. ADR-002); черновик квиза, не пишет в `user_lexeme_progress` напрямую | — | `Agent/Tools/Student/GenerateQuizTool.php` | Тест: JSON-схема ответа валидна, `sideEffect=draft_only` | `ai-engineering-learning-roadmap.md` шаг 3 |
| 3.8 | Access control | Явная проверка роли на границе контроллера (не размазывать по tools) — кто может говорить с `TutorAgent` | — | Правка `TutorConversationController`/middleware | Тест: не-ученик получает 403 | vision 5.6 |

**Definition of Done эпика**: ученик может открыть чат, спросить про свои
слова/ошибки/грамматику, получить стримящийся ответ, заземлённый на своих
данных, и попросить квиз.

## 5. EPIC 4 — Agent System & Workflow Engine

Цель: специализированные агенты, граф, handoff, параллелизм — для сценариев,
которые TutorAgent один не закрывает.

| # | Задача | Делаем | Миграции | Классы | Тесты | Источник |
|---|---|---|---|---|---|---|
| 4.1 | Ядро графа | `GraphNode`/`GraphState`/`GraphRunner`, `agent_graph_runs` | `create_agent_graph_runs_table` | `Agent/Graph/{GraphNode.php,GraphState.php,GraphRunner.php}` | Юнит-тесты `GraphRunner` на фейковых узлах | `agent-framework-roadmap.md` раздел 7 |
| 4.2 | Первый граф — известный процесс | `AiAnalysisRun` (`analyze → match → review checkpoint → apply`) как `GraphDefinition` | — | `Agent/Graph/Definitions/AiAnalysisGraph.php`, `Agent/Graph/Nodes/{AnalyzeNode,MatchNode,ApplyNode}.php` | Feature-тест: граф проходит все стадии на fixture-контенте | 5.15 |
| 4.3 | `HandoffTool` | Механизм + guardrail'ы: макс. глубина 2 (ADR-006), проверка `sideEffect` на wiring | — | `Agent/Tools/HandoffTool.php` (базовый класс) | Тест: попытка глубины 3 отклоняется; handoff с более широким `sideEffect`, чем у цели, не собирается | 5.14, ADR-006 |
| 4.4 | `GrammarAgent` | Полноценный агент (не Tool — см. ADR-002): `GrammarSearchTool`, `GrammarExplanationTool`, `ErrorAnalysisTool` | — | `Agent/GrammarAgentService.php`, `Agent/Tools/Grammar/*.php` | Сценарные тесты по образцу 1.1 | vision 5 |
| 4.5 | `ExerciseAgent` | Только если `GenerateQuizTool` (3.7) на практике перерастёт один LLM-вызов (адаптация сложности, самопроверка) — проверить на реальном использовании перед тем, как заводить | — | `Agent/ExerciseAgentService.php` (если оправдано) | — | ADR-002 |
| 4.6 | `ReviewAgent` | План повторения: `GetWeakWordsTool`, `CreateReviewPlanTool`, `ScheduleReviewTool` | — | `Agent/ReviewAgentService.php`, `Agent/Tools/Review/*.php` | Сценарные тесты | vision 5 |
| 4.7 | `TutorAgent`-граф | `RouterNode` (детерминированный, по явным сигналам) → `AgentNode`(`GrammarAgent`/`ReviewAgent`/`ExerciseAgent`) → merge | — | `Agent/Graph/Definitions/TutorRoutingGraph.php` | Feature-тест роутинга по фиксированным сигналам | 5.16 |
| 4.8 | `HumanCheckpointNode` | Обобщить паузу графа на клике админа ("Apply approved AI candidates") в явный узел | — | `Agent/Graph/Nodes/HumanCheckpointNode.php` | Тест: граф встаёт на паузу, `resume()` продолжает с того же узла | 5.17 |
| 4.9 | Дешёвый fan-out | `Http::pool()` в `CandidateMatchingService::matchRun()` вместо последовательного `foreach` | — | Правка `CandidateMatchingService.php` | Существующие тесты остаются зелёными + тест на конкурентность (замеры) | 5.19 |
| 4.10 | Реестр узлов + данные | `GraphNodeRegistry`, `GraphDefinition` как массив/JSON вместо PHP-класса (перевести 4.2 на новый формат) | — | `Agent/Graph/GraphNodeRegistry.php` | Тест: неизвестный `node_key` в определении графа — ошибка на этапе загрузки | 5.20 |
| 4.11 | `ParallelNode` | Fan-out/fan-in через `Bus::batch()`, `agent_graph_branch_results`, fail-fast политика | `create_agent_graph_branch_results_table` | `Agent/Graph/Nodes/ParallelNode.php`, `Jobs/{GraphBranchJob,ResumeGraphJob}.php` | Feature-тест: все ветки завершились → fan-in продолжает; одна упала → весь run failed | 5.21 |
| 4.12 | `PlanningAgent` | Композитный сценарий (IELTS-стиль) поверх `ParallelNode` (4.11), а не цепочки handoff — см. ADR-006 | — | `Agent/PlanningAgentService.php`, `Agent/Graph/Definitions/StudyPlanGraph.php` | Feature-тест на fixture-запросе | vision 6 |
| 4.13 | Kafka-событие `exercise.completed` | Публикация события + консьюмер, консолидирующий в semantic memory (наполняет EPIC 2.6) | — | `Modules/Learning/Interfaces/Listeners/PublishExerciseCompletedToKafka.php`, `Console/Commands/ConsumeExerciseEventsCommand` (расширение существующего `ConsumeKafkaCommand`) | Тест листенера (событие публикуется с правильным payload) | vision 9.2 |
| 4.14 | Рекомендации на эмбеддингах | `RecommendationService` — ранжирование по похожести на выученные слова + давность повторения вместо `inRandomOrder()` | — | Правка `RecommendationService.php`, `Modules/Ai/Application/VectorMath.php` (обобщённый `cosineSimilarity`, вынесенный из `CandidateMatchingService`) | Тест: ранжирование предпочитает похожие/просроченные слова на fixture | `ai-engineering-learning-roadmap.md` шаг 4 |

**Definition of Done эпика**: `TutorAgent` умеет передавать управление
`GrammarAgent`/`ReviewAgent` по делу, известный композитный сценарий (план
подготовки) идёт через граф с параллельными ветками, а не через
непредсказуемую цепочку handoff'ов.

**Статус на конец эпика**: оба пункта DoD выполнены. Model-directed handoff
`StudentTutorAgentService → {GrammarAgentService, ReviewAgentService}` —
`HandoffToGrammarAgentTool`/`HandoffToReviewAgentTool`, зарегистрированы в
`StudentTutorAgentService::blueprint()->tools` и wiring в
`AiServiceProvider::bindHandoffTool()` — реально достижим из чата ученика,
не только протестирован изолированно (см. сценарный тест в
`StudentTutorAgentServiceTest`). Композитный сценарий (`StudyPlanGraph` +
`ParallelNode`) — задача 4.12. `TutorRoutingGraph` (граф-направленный,
детерминированный роутинг для явных сигналов вроде UI-кнопки) и
`AiAnalysisGraph` (переописание уже существующего пайплайна) — оба
намеренно **не** подключены к живым точкам входа
(`TutorConversationController`, `RunAiContentAnalysisJob`/
`ApplyAiCandidatesAction`) — additive, полностью протестированы отдельно;
см. финальный отчёт эпика за обоснование и что нужно ревьюеру.

## 6. EPIC 5 — Production AI

Цель: то, что имеет смысл только при реальной нагрузке — не делать раньше.

| # | Задача | Делаем | Миграции | Классы | Тесты | Источник |
|---|---|---|---|---|---|---|
| 5.1 | Evals | Golden dataset для `AiContentAnalysisService` и 2-3 сценариев `TutorAgent`, простая метрика точных/частичных совпадений | — | `Console/Commands/AiEvalCommand.php`, fixtures в `tests/Fixtures/Evals/` | Сам eval — не unit-тест, а отдельный прогон с отчётом | `ai-engineering-learning-roadmap.md` шаг 6 |
| 5.2 | Стоимость трейсов | `cost_usd` в `agent_trace_spans` (расчёт по прайсу провайдера из токенов), `agent_traces` — денормализованная сводка | `add_cost_usd_to_agent_trace_spans_table`, `create_agent_traces_table` | Правка `DatabaseSpanRecorder.php` | Тест расчёта `cost_usd` по фиксированному прайсу | vision 10 |
| 5.3 | OpenTelemetry / ES-экспорт | Tier 2 трейсинга — экспорт `agent_trace_spans` в уже поднятый Elasticsearch (не Jaeger/Tempo) | — | `Modules/Ai/Infrastructure/ElasticSpanExporter.php` | — | 5.18 |
| 5.4 | Semantic cache | Кэш ответов чата по похожести эмбеддинга вопроса, не по точному тексту | — | Правка `ChatContextAiService`/`TutorAgent`-tools, использующих кэш | Тест: похожий (не идентичный) вопрос возвращает кэш | `ai-engineering-learning-roadmap.md` шаг 7 |
| 5.5 | Графы в БД | `GraphDefinition` как JSON в БД + admin UI сборки пайплайна из зарегистрированных узлов — только когда накопится 2-3 реальных вариации | миграция под хранение JSON-определений | Filament-ресурс | — | 5.22 (осознанно поздний) |
| 5.6 | Redis-кэш | `CACHE_STORE=redis` вместо `database`, перевод горячих кэшей (`AiExplainLexemeService`, эмбеддинги) — независимая, можно раньше при желании | — | Конфигурация, без новых классов | — | vision 9.1 |

## 7. EPIC 6 — Chat UX & Observability

Цель: то, что реально видит и трогает пользователь. Пять готовых backend-эпиков
дали работающий движок, но живой UI использует из него только стриминг-чат и
рекомендации контента — остальное (квиз, прозрачность агента, роли,
видимость для админа) не долетело до интерфейса. Этот эпик закрывает разрыв.

### Дизайн-решение 6.1 — вход в чат "из разных частей приложения"

Ключевой вопрос: как пользователь попадает в TutorAgent-чат со страницы
контента/грамматики/слова так, чтобы бот сразу понимал, о чём речь, — без
отдельного conversation-менеджера (его в проекте нет и заводить его для
этого одного сценария — лишняя сложность, см. `engineering-principles.md`).

Решение: **один переиспользуемый компонент входа + контекст через query-параметры
+ подсадка контекста в существующий персистентный диалог**, а не новая
сущность "тред на каждую страницу":

1. Новый `AskAiButton.vue` (`shared/ui/`) принимает `context: { type: 'content'|'grammar'|'lexeme', id, title }`. Ставится на `ContentDetailsPage.vue` ("Discuss with AI" — уже есть кнопка-заглушка на роут `chat`, см. `ai-engineering-learning-roadmap.md`), `StudyPage.vue`, страницу деталей грамматического правила и `WordDetailsPage`/страницу деталей слова.
2. Клик — переход на `{ name: 'chat', query: { context_type, context_id, context_title } }`. Компактно, читаемо, можно будет использовать в диплинках позже.
3. `ChatPage.vue` при наличии query-параметров: показывает несбиваемый чип "Обсуждаем: {title}" над полем ввода и **один раз** подмешивает контекст в текст первого следующего сообщения пользователя (не создаёт новый диалог — продолжает тот же персистентный `agent_conversations` для этого пользователя, как сейчас и работает `ensureConversation()` — проверить его текущее поведение перед изменением, не менять его логику "один активный диалог", если она уже такая).
4. Для наблюдаемости (не для логики модели) — на `agent_messages` добавить nullable `context_type`/`context_ref_id`/`context_label` (та же роль, что уже играют `tool_args`/`tool_result` на этой же таблице) — админ потом видит в трейсах, из какого контекста пришёл вопрос (готовит почву для 6.8).

Осознанно **не** делаем на этом шаге: отдельный диалог на каждый контент,
серверный "context builder" с подтягиванием слабых слов/прогресса (это
`TutorContextService` из `ai-chat-tutor-plan.md`, Stage 2 — отдельный, более
крупный шаг, когда станет понятно, что текстовой пометки недостаточно).

| # | Задача | Делаем | Миграции | Классы | Тесты | Источник |
|---|---|---|---|---|---|---|
| 6.1 | Контекстный вход в чат | `AskAiButton.vue`, query-параметры, чип контекста в `ChatPage.vue`, подмешивание в первое сообщение, кнопки на 4 страницах | `add_context_columns_to_agent_messages_table` | `shared/ui/AskAiButton.vue`, правка `ChatPage.vue`, `ContentDetailsPage.vue`, `StudyPage.vue`, страниц грамматики/слова | Feature-тест: сообщение с контекстом сохраняет `context_type`/`context_ref_id` | design 6.1 выше |
| 6.2 | Прозрачность агента | Новые SSE-события `tool_start`/`tool_end`/`handoff` из `TutorConversationController`, парсинг в `tutorApi.ts`, транзиентная строка статуса в `ChatPage.vue` ("Ищу твой прогресс...", человекочитаемые лейблы по имени tool/агента) | — | Правка `TutorConversationController.php`, `tutorApi.ts`, `ChatPage.vue` | Feature-тест: SSE-поток содержит `tool_start` до `delta` при вызове инструмента | vision 3, EPIC-3.6 |
| 6.3 | Интерактивная карточка квиза | `GenerateQuizTool`'s JSON во финальном SSE `done`-событии (не только текст модели) → `QuizCard.vue` рендерит вопросы/варианты вместо голого текста в чате | — | Правка `TutorConversationController.php` (приложить `toolResults` к `done`), `shared/ui/QuizCard.vue` | Feature-тест: `done`-событие содержит `toolResults`, если `GenerateQuizTool` был вызван | EPIC-3.7 |
| 6.4 | Ответ на квиз-карточку | MVP: мгновенная проверка на клиенте (правильно/неправильно), без записи в прогресс/SRS — сознательно, черновик остаётся черновиком (тот же принцип `draft_only`, что и у самого tool). Интеграция с SRS — отдельная будущая задача, не эта | — | Логика внутри `QuizCard.vue` | — | ADR "draft-only" принцип (agent_human_in_the_loop) |
| 6.5 | Роли на фронте | `authStore` сохраняет `roles` из `/api/auth/me`; nav-пункт "AI chat" скрыт для admin/editor/moderator; `ChatPage.vue` показывает понятный экран "недоступно для вашей роли" на 403 вместо сырой ошибки | — | Правка `authStore.ts`, `SpaShell.vue`, `ChatPage.vue` | Тест authStore: `roles` сохраняются и читаются | находка из обследования SPA |
| 6.6 | Рекомендации слов | Подключить `recommendedApi.getLexemes()` — секция "Words to learn next" на Dashboard или `StudyPage.vue`, по образцу уже работающей секции рекомендованного контента | — | Правка `DashboardPage.vue` или `StudyPage.vue`, возможно новый composable по образцу `useRecommendedContents` | — | находка из обследования SPA |
| 6.7 | Судьба старого `/chat` | Убрать осиротевший `chatApi.ts` и мёртвые импорты из SPA (уже не в nav); backend (`AiConversationController`/`ChatContextAiService`/`AiChatOverviewWidget`) оставить как есть, если что-то ещё на него ссылается (проверить перед удалением) — задокументировать решение явно, не удалять втихую | — | Возможное удаление `chatApi.ts` | Существующие тесты остаются зелёными | находка из обследования SPA |
| 6.8 | Минимальная видимость для админа | Read-only Filament-страница: последние N `agent_traces` (agent_type, cost, длительность, статус) с переходом в дерево `agent_trace_spans` одного трейса; отдельный простой список `agent_graph_runs`. Не дашборд с графиками — просто таблицы поверх уже существующих данных | — | Новый Filament Page/Resource, например `App\Filament\Pages\AgentTraces` | Тест: страница рендерится, показывает существующие тестовые трейсы | находка из обследования SPA |

**Definition of Done эпика**: с любой страницы контента/грамматики/слова можно
одним кликом перейти в чат с понятным контекстом; во время ответа видно, что
именно делает агент; квиз от `GenerateQuizTool` можно реально пройти, а не
прочитать как JSON; админ/редактор не видят чат ученика как рабочую кнопку;
у админа есть куда посмотреть трейсы без похода в БД руками.

## 8. EPIC 7 — Word Level & Bulk Management

Цель: слово в списке под видео должно нести свой CEFR-уровень, список должен
фильтроваться по нему, известные слова не должны мешаться под ногами по
умолчанию, а массовые действия («это я всё уже знаю», «это всё учу») не
должны требовать клика по каждому слову отдельно. Плюс — табы вместо
скролла на странице контента. Это не про AI-агентов конкретно (в отличие от
EPIC 1-6) — это базовая UX-доработка существующего экрана слов, просто
уровень слова исторически приходит из AI-анализа, поэтому автопроставление
уровня тоже опирается на существующий AI-клиент.

### Что уже есть, а что нет (проверено по коду перед постановкой задач)

- `lexemes.level` (A1-C2, nullable) — колонка уже есть в БД
  (`2026_03_23_180000_create_grammar_catalog_tables.php`), но **не отдаётся**
  в `ContentService::getLexemesWithLearnedFlags()` — фронт о ней не знает.
- `level` реально проставляется только когда админ вручную принимает
  AI-кандидата при анализе контента. Слова, попавшие в каталог через обычную
  токенизацию (`ProcessContentJob` → `CanonicalLexemeSyncService::sync()`),
  уровня не получают вообще — для них нужен отдельный механизм.
- `AiExplainLexemeService::suggestLevel()` уже существует и умеет определить
  CEFR-уровень слова через LLM — это готовый строительный блок для
  автопроставления, не нужно изобретать новый промпт.
- «Mark learned» и «Start learning» — оба уже реализованы, но **только
  по одному слову** (`POST /content/lexemes/{lexeme}/mark-learned`,
  `.../start-learning`, оба принимают `ContentLexeme.id` — один и тот же
  ID-space, значит один механизм выбора обслуживает оба массовых действия).
- Компонента табов (`UiTabs.vue` или аналог) в `shared/ui/` не существует —
  `ContentDetailsPage.vue` сейчас просто ставит секции Words/Grammar/Full
  text одну под другой (`UiCard`, строки ~205, ~242, ~285 на момент
  постановки задачи).

| # | Задача | Делаем | Миграции | Классы | Тесты | Источник |
|---|---|---|---|---|---|---|
| 7.1 | Уровень слова в API | Добавить `'level' => $canonicalLexeme?->level` в `ContentService::getLexemesWithLearnedFlags()`, тип `LexemeWithLearned.level` на фронте | — | Правка `ContentService.php`, SPA-типов | Тест: ответ API содержит `level` | найдено при обследовании |
| 7.2 | Автопроставление уровня | Очередь-джоб на основе `AiExplainLexemeService::suggestLevel()`, срабатывает при создании канонической леммы без уровня (в `CanonicalLexemeSyncService::sync()`), асинхронно — не блокирует токенизацию. Плюс artisan-команда backfill для уже существующих слов без уровня | — | Новый Job (по образцу существующих `Compute*EmbeddingsJob`), `Console/Commands/BackfillLexemeLevelsCommand.php` | Тест: слово без уровня получает уровень после обработки job'а; backfill-команда не трогает слова, у которых уровень уже есть | AiExplainLexemeService |
| 7.3 | Фильтр по уровню | Чипы/селектор A1-C2 + "без уровня" на `StudyPage.vue`, рядом с уже существующими all/not-learned/learned | — | Правка `StudyPage.vue` | — | 7.1 |
| 7.4 | Массовый выбор | Чекбоксы на `WordListItem.vue`, "выбрать все под текущим фильтром", состояние выбора в `StudyPage.vue` | — | Правка `WordListItem.vue`, `StudyPage.vue` | — | — |
| 7.5 | Массовые действия | Два bulk-эндпоинта по образцу одиночных (`bulk-mark-learned`, `bulk-start-learning`, оба принимают массив `ContentLexeme.id`); панель над списком при активном выборе — две кнопки: "Отметить как известные" / "Добавить к изучению" | — | Новые методы в `LexemeController.php`, правка `useLexemes.ts` | Тест: bulk-эндпоинт отмечает N слов, ошибка одного не роняет остальные | 7.4 |
| 7.6 | Известные слова скрыты по умолчанию | Дефолт `filterStatus` на `StudyPage.vue` меняется с `'all'` на `'not-learned'`, переключение на "все"/"выученные" остаётся доступным | — | Правка `StudyPage.vue` (один default-value) | — | находка из обследования |
| 7.7 | Табы вместо скролла | Новый `UiTabs.vue` в `shared/ui/`; `ContentDetailsPage.vue` оборачивает секции Words/Grammar/Full text в табы вместо трёх последовательных карточек; шапка с источником/метаданными остаётся сверху как есть | — | `shared/ui/UiTabs.vue`, правка `ContentDetailsPage.vue` | — | находка из обследования |
| 7.8 | Уровень/выбор/массовые действия — везде | **Исправление**: 7.3-7.6 были реализованы только на `StudyPage.vue`, а исходный запрос был про страницу с видео (`ContentDetailsPage.vue`). Вынести фильтр по уровню + status-фильтр + чекбоксы + "выбрать все" + панель массовых действий в общий компонент (например `shared/ui/WordListToolbar.vue`, использует уже существующие `useLexemes.ts` bulk-методы из 7.5), подключить на обеих страницах — не дублировать разметку/логику. На `ContentDetailsPage.vue` — внутри таба "Words" (7.7), после существующих `AskAiButton`/чипов | — | `shared/ui/WordListToolbar.vue`, правка `StudyPage.vue` (переключить на общий компонент, поведение не меняется), `ContentDetailsPage.vue` | Тест: тот же bulk-эндпоинт из 7.5 достижим с обеих страниц | 7.3, 7.4, 7.5, 7.6 |

**Definition of Done эпика**: под видео (не только на отдельной странице
"Study") виден уровень каждого слова, список фильтруется по уровню,
известные слова скрыты по умолчанию, можно выбрать группу слов и одним
кликом отметить как известные или добавить к изучению, а секции
Words/Grammar/Full text — табы, не бесконечный скролл.

## 9. EPIC 8 — AiAnalysisGraph Cutover (Beta)

Цель: `AiAnalysisGraph` (EPIC 4.2) — тонкая обёртка над теми же сервисами,
что и живой `RunAiContentAnalysisJob` (`AiContentAnalysisService`,
`CandidateMatchingService`, `AiCandidateApplyService` — никакой
продублированной логики), с честно протестированной паузой/резюме на
`HumanCheckpointNode`. Но переключать её на замену живого пути сейчас нельзя
"одним свитчем" — два реальных гэпа и отсутствие способа вообще её
запустить на настоящем контенте. Этот эпик закрывает оба гэпа и добавляет
**опциональную**, параллельную живому пути кнопку в админке — не замену, а
возможность прогнать обе версии на одном контенте и сравнить руками, прежде
чем доверять графу по умолчанию.

### Два гэпа, которые делают текущий граф небезопасным для прямой замены

1. **`MatchNode` не guard'ит матчинг.** Живой джоб (`RunAiContentAnalysisJob.php`,
   комментарий "Matching is best-effort") оборачивает `matchRun()` в свой
   `try/catch` — сбой матчинга (например, эмбеддинги недоступны) логируется
   и не мешает завершить прогон с уже найденными кандидатами. `MatchNode`
   такого guard'а не имеет — исключение там прямо роняет весь `GraphRunner`,
   на шаге `match`, до того как `apply` вообще получит шанс.
2. **Граф не трогает `AiAnalysisRun.status`.** Живой джоб явно ведёт статус
   `pending → running → completed/failed`. Ни один узел графа этот статус
   не обновляет — только отдельная строка `AgentGraphRun.status` (через
   `EloquentGraphRunObserver`). Бейдж статуса анализа в админке
   (`ContentInfolist.php`) навечно застынет на "pending", если граф заменит
   живой путь без этого исправления.

`AiCandidateApplyService::apply()` **не трогает** `AiAnalysisRun.status`
вообще (только статусы кандидатов, идемпотентно) — ни в живом пути, ни в
графовом это не нужно менять, только `start()`/сама пауза должны отражать
`running`/`completed`/`failed`, то есть ровно то же самое, что уже делает
живой джоб.

| # | Задача | Делаем | Миграции | Классы | Тесты | Источник |
|---|---|---|---|---|---|---|
| 8.1 | Guard на matching | `MatchNode::run()` оборачивает `CandidateMatchingService::matchRun()` в try/catch с логированием, той же семантикой "best-effort", что и в живом джобе | — | Правка `Graph/Nodes/MatchNode.php` | Тест: исключение в `matchRun()` не роняет граф, `apply` всё равно выполняется | найдено при обследовании (см. предыдущий ответ) |
| 8.2 | Статус `AiAnalysisRun` из графа | `AiAnalysisGraphService::start()` ведёт `AiAnalysisRun.status`: `running`+`started_at` перед запуском узлов; `completed`+`completed_at`, когда граф встал на паузу на `review_checkpoint` (это и есть "готово к ревью", как в живом пути) или полностью завершился; `failed`+`failure_reason`, если `analyze` бросил исключение | — | Правка `Graph/AiAnalysisGraphService.php` | Тест: после `start()` статус `AiAnalysisRun` меняется идентично тому, что проверяет `RunAiContentAnalysisJobTest` для живого пути | 8.1 выше |
| 8.3 | Асинхронный запуск | Новый `RunAiAnalysisGraphJob` (тот же стиль, что `RunAiContentAnalysisJob`: `ShouldQueue`, `tags()`, `tries`/`backoff()`) вызывает `AiAnalysisGraphService::start($runId)` — граф не должен блокировать HTTP-запрос админа | — | `Jobs/RunAiAnalysisGraphJob.php` | Тест: job вызывает `start()` с правильным `runId` | — |
| 8.4 | Кнопка в админке (бета) | Новый Filament-экшен "Analyze via graph engine (beta)" рядом с существующим "Analyze with AI" на странице Content — создаёт **новый** `AiAnalysisRun` (тот же паттерн, что `AnalyzeWithAiAction`, не конфликтует с живым прогоном) и диспатчит `RunAiAnalysisGraphJob` | — | `Filament/Resources/Contents/Actions/AnalyzeWithAiGraphAction.php` | Feature-тест: клик создаёт run и ставит job в очередь | 8.3 |
| 8.5 | Кнопка резюме после ревью | На уже существующей read-only странице `AgentGraphRuns` (EPIC 6.8) — action "Approve & Apply", видимый только для строк `graph_name` = ai-анализа и `current_node = review_checkpoint`/`status = paused`; вызывает `AiAnalysisGraphService::approveAndResume($graphRunId)` | — | Правка `Filament/Pages/AgentGraphRuns.php` | Feature-тест: клик резюмирует граф, `ApplyNode` выполняется, кандидаты получают `status = applied` | 8.2, EPIC-6.8 |

**Definition of Done эпика**: админ может нажать "Analyze via graph engine
(beta)" на любом контенте с транскриптом, увидеть корректный статус
анализа, отревьюить кандидатов в уже существующем UI (та же таблица, что и
для живого пути — сервисы общие), нажать "Approve & Apply" и получить
ровно тот же результат, что и живой путь. **Не входит в DoD**: замена
дефолтной кнопки или удаление живого джоба — это отдельное, более позднее
решение после того, как графовый путь реально попробуют на нескольких
настоящих кусках контента и сравнят вручную.

## 10. EPIC 9 — AI-First Word Extraction & User-Facing Review

Цель: слова "под видео" по умолчанию — результат AI-анализа (типизированные
`word/phrase/phrasal_verb/idiom/collocation` с переводом/примером/уровнем),
не плоская разбивка `ContentTokenizer`. Ревью/применение кандидатов
переезжает **со стороны обычного приложения**, доступное автору контента,
а не только через Filament-админку.

### Ключевое решение: оставить `ContentTokenizer` только для аналитики

Пользовательский запрос был "удалить" разбивку по словам. Разобрал и не
рекомендую буквальное удаление — по трём причинам:
1. AI-анализ асинхронный (реальный LLM-вызов, занимает время) — если
   контент показывать только после его завершения, несколько
   минут/при сбое пользователь видит пустой список вместо хоть чего-то.
2. Квота (`ai.rate_limits.user_submission_analysis_per_day`) или
   `ai.enabled=false` могут не дать анализу вообще запуститься — без
   fallback контент навсегда остаётся без единого слова.
3. У AI-анализа уже есть родная метрика "не всё покрыл" (раздел ниже) — это
   ровно тот "чек, все ли слова использовал", который просил пользователь,
   и он логически требует, чтобы было с чем сравнивать (токенайзерный
   список уникальных слов транскрипта).

`ContentTokenizer` продолжает работать как мгновенный дешёвый список только
для coverage и списка слов, которые AI не покрыл. Он больше не создаёт
`ContentLexeme` и не является fallback-источником учебных карточек:
learner-facing записи создаются только после серверной валидации AI-кандидатов.

### Ключевое решение (исходное, 9.2-9.4): ревью остаётся, но переезжает к автору контента, а не в админку

Пользователь спросил и "чтобы применялось автоматически", и "чтобы это было
со стороны приложения для пользователя". Полностью тихое авто-применение
кандидатов без чьего-либо взгляда — это прямой откат уже принятого раньше в
проекте правила "агент только предлагает, публикует человек"
(`docs/architecture/adr/` + память проекта `agent_human_in_the_loop_apply`).
Не отменяю это правило молча — **переношу, кто может быть этим человеком**:
не только staff в Filament, а автор конкретного контента (`Content.created_by`)
в обычном приложении. Ревью при этом делается быстрым (всё по умолчанию
выбрано, один клик "Добавить всё"), а не тяжёлым чек-листом — то есть на
практике ощущается почти как "автоматически", но остаётся осознанным
действием, а не тихой фоновой записью в общий каталог.

**Это решение реализовано (9.2-9.4, ниже) и затем прямо отменено** — см.
следующий блок.

### Отмена вышеуказанного решения (9.8): полное авто-применение, без клика человека

Явное, осознанное решение пользователя, принятое **после** реализации
9.1-9.7: AI должен добавлять кандидатов сразу, без подтверждения — ни
автором контента, ни админом. Это прямо отменяет и переносит дальше
компромисс из блока выше (человек-в-цикле не убирается тихо — а **явно, по
запросу пользователя**, задокументировано здесь, чтобы не потерялось в
истории чата). Правило "агент только предлагает, публикует человек" из
`agent_human_in_the_loop_apply` для **этого конкретного пайплайна**
(извлечение слов/грамматики из контента) больше не действует — см. 9.8
ниже за детали и compensating control (confidence floor + `rejected` вместо
тихого дропа, чтобы не терять трассируемость).

Что это означает для уже сделанного:
- **9.2/9.3 (API)** — код не удалён, остаётся рабочим и идемпотентным
  fallback-путём (например, для прогона до 9.8 или ручного повторного
  триггера), но перестаёт быть основным путём — кандидаты обычно уже не
  застревают в `pending` к моменту, когда его можно было бы вызвать.
- **9.4 (UI карточка)** — превращена из чек-листа с кнопкой "Добавить" в
  пассивное, read-only уведомление "AI добавил: N слов, M грамматических
  конструкций" (прозрачность, не запрос разрешения). Старый интерактивный
  UI оставлен в том же компоненте как fallback на случай, если что-то
  всё же осталось `pending`.
- **9.6 (счётчик в "My submissions")** — оставлен как есть technически
  корректным (считает `pending`, что теперь почти всегда 0), но по факту
  малополезен в новом режиме; не переделывался в рамках 9.8/9.9 (не было
  явного запроса), возможное следующее шаги — считать вместо этого
  "только что применено", как в 9.4.

| # | Задача | Делаем | Миграции | Классы | Тесты | Источник |
|---|---|---|---|---|---|---|
| 9.1 | Coverage-метрика | После `AiContentAnalysisService::analyze()` посчитать % уникальных слов транскрипта (через существующий `ContentTokenizer::tokenize()`, используется только как метрика, не как источник отображаемых слов), покрытых текстом хотя бы одного кандидата (вхождение как отдельное слово или часть фразы). Если ниже порога (`config('ai.analysis.min_coverage_pct', 0.7)`) и текущий прогон не был `thoroughness=thorough` — **один** авто-перезапуск анализа с `thoroughness=thorough` (guard от бесконечного цикла — не более одного авто-ретрая на run). **Уточнение**: помимо агрегированного `coverage_pct`, персистится и сам список непокрытых слов (`uncovered_words`, JSON на `AiAnalysisRun`) — не только число ради числа, а конкретный "что AI не покрыл", переиспользуемый напрямую в 9.5, а не пересчитываемый там заново другой эвристикой | `add_coverage_pct_to_ai_analysis_runs_table` (добавляет `coverage_pct`, `uncovered_words`, `retried_for_coverage`) | Правка `AiContentAnalysisService.php` | Тест: низкое покрытие триггерит ровно один авто-ретрай, не два | находка из ресёрча |
| 9.2 | Мои pending-предложения (API) | `GET /api/content/{content}/ai-suggestions` — pending лексемные/грамматические кандидаты последнего `AiAnalysisRun` этого контента; авторизация: `content.created_by === auth()->id()` (не admin-гейт) | — | Новый контроллер/метод, например `ContentAiSuggestionsController.php` | Тест: чужой контент — 403; свой — отдаёт pending-кандидатов | 9.1 |
| 9.3 | Принять предложения (API) | `POST /api/content/{content}/ai-suggestions/accept` — тело: `candidate_ids[]` или `accept_all`; переиспользует `AiCandidateApplyService`/существующую логику accept→apply один в один, только новый гейт авторизации вместо admin-only | — | Правка/новый метод в `ContentAiSuggestionsController.php` | Тест: автор контента может принять и применить кандидатов через это API; не-автор — не может | 9.2, `AiCandidateApplyService` (не менять) |
| 9.4 | UI ревью в приложении | Карточка на `ContentDetailsPage.vue` (видна только автору контента, когда есть pending-предложения): "AI нашёл N новых слов/фраз/конструкций" — список с чекбоксами (по умолчанию все выбраны), "Добавить выбранное" | — | Новый компонент, например `shared/ui/AiSuggestionsReviewCard.vue`, правка `ContentDetailsPage.vue` | — | 9.2, 9.3 |
| 9.5 | Токенайзерные слова — только незакрытый остаток | В `getLexemesWithLearnedFlags()` — если у контента есть завершённый анализ с персистнутым `uncovered_words` (9.1), токенайзерные слова (`type=word`), чей нормализованный текст входит в этот список, помечаются явным флагом "не проанализировано" вместо смешивания без разметки с AI-словами. Переиспользует ровно список из 9.1, не отдельную проверку "нет translation/level" | — | Правка `ContentService.php`, SPA-типов/рендера | — | найдено при обследовании ("give"/"up" остаются рядом с "give up") |
| 9.6 | Напоминание о pending-ревью | Индикатор/счётчик в "My submissions" (уже существующий `getMySubmissions()`) — сколько своих видео ждут ревью AI-предложений, чтобы не забывать про 9.4 | — | Правка соответствующей SPA-страницы | — | реакция на "чтобы не забывать" из предыдущего разговора |
| 9.7 | Автоподстановка part_of_speech | `SuggestLexemeLevelJob` (7.2) расширен: тот же единственный LLM-вызов (через новый `AiExplainLexemeService::suggestMetadata()`) теперь возвращает и `level`, и `part_of_speech` для только что созданной канонической `Lexeme` — не второй job/вызов, ровно та же схема триггера (создание лексемы без поля) и тот же guard "не перезаписывать вручную выставленное". `part_of_speech` валидируется по enum, как `level` — по `Content::CEFR_LEVELS`. **Отклонение от исходной формулировки**: enum перенесён из `LexemeForm::PARTS_OF_SPEECH` (Filament) в `Lexeme::PARTS_OF_SPEECH` (модель) — `SuggestLexemeLevelJob` не должен зависеть от Filament-слоя админки для валидации (нарушение модульных границ, engineering-principles.md), а `Content::CEFR_LEVELS`, на который валидируется `level`, уже живёт на модели, не на форме — та же логика применена и к `part_of_speech`. `LexemeForm::PARTS_OF_SPEECH` оставлен как алиас (`= Lexeme::PARTS_OF_SPEECH`) для обратной совместимости внешних ссылок. Флаг переименован `ai.lexeme_level_suggestion` → `ai.lexeme_metadata_suggestion` (job теперь покрывает оба поля, имя класса `SuggestLexemeLevelJob` оставлено как есть — минимальный blast radius, переименование не даёт функциональной пользы) | — | Правка `AiExplainLexemeService.php`, `SuggestLexemeLevelJob.php`, `config/ai.php`, `Lexeme.php` (новый `PARTS_OF_SPEECH`), `LexemeForm.php`/`LexemesTable.php` (ссылка на модель) | Тест: job проставляет оба поля одним вызовом; не перезаписывает уже выставленное ни для одного из полей независимо | EPIC-7.2 (та же механика), реакция на уточнение пользователя |
| 9.8 | **Отмена ревью**: полное авто-применение | Новый `AiCandidateAutoApplyService::autoApply()`, вызывается из `RunAiContentAnalysisJob` сразу после `CandidateMatchingService::matchRun()` (best-effort, в своём try/catch — сбой здесь не должен превращать успешную экстракцию в `failed` run, кандидаты остаются доступны через существующий Filament "Apply approved AI candidates" как ручной recovery-путь). Кандидаты с `confidence >= config('ai.analysis.auto_apply_min_confidence', 0.5)` — принимаются и применяются через немодифицированный `AiCandidateApplyService::apply()`; всё остальное (включая `confidence = null`, т.к. `NULL >= x` всегда ложно в SQL) — `rejected`, не остаётся вечно `pending` и не дропается молча (трассируемость: видно, что было отфильтровано). Дедуп между независимыми прогонами (два разных контента предлагают одно и то же слово) уже гарантирован существующим `CandidateMatchingService` (exact-match по `normalized_lemma` раньше эмбеддингов) — не новый код, только проверено регрессионным тестом. Область: только «живой» путь (`RunAiContentAnalysisJob`, реальные пользовательские сабмишены); EPIC 8's graph-путь (`AiAnalysisGraphService`/`HumanCheckpointNode`) — отдельный, явно бета/admin-triggered механизм, не трогается | — | Новый `AiCandidateAutoApplyService.php`, правка `RunAiContentAnalysisJob.php`, `config/ai.php` (`auto_apply_min_confidence`) | Тест: кандидат выше порога — применяется; ниже порога (включая `null`) — `rejected`, не `pending`; регрессия — два разных контента, одно и то же слово → одна каноническая `Lexeme`, не дубликат | явный запрос пользователя после 9.1-9.7, отменяет блок "ревью переезжает к автору" выше |
| 9.9 | Мультипример на кандидата | Расширена схема JSON-ответа `AiContentAnalysisService`: вместо одного `example`/`example_translation` — массив `examples` (2-3 шт.) с полем `source: "context"\|"generated"` на каждом. Минимум один пример должен быть `source="context"` — реальное (или близкое к дословному) предложение из транскрипта, не выдумка. Новая колонка `examples` (JSON) на `content_lexeme_candidates`; старые `example`/`example_translation` **не удалены** — используются как backward-compat зеркало главного (предпочтительно `context`) примера (их читают `LexemeCandidatesRelationManager` и `AiCandidateApplyService`, менять оба ради удаления двух колонок не оправдано). `AiCandidateApplyService::attachLexemeExample()` создаёт по одной `LexemeExample` на каждый элемент массива, `context`-пример — `is_primary`, если у леммы ещё нет primary. Полный backward-compat: ответ по старой одиночной схеме (или уже существующий кандидат без `examples`) — парсится в массив из одного `context`-примера, ни один существующий тест/фикстура не менялся | `add_examples_to_content_lexeme_candidates_table` | Правка `AiContentAnalysisService.php` (промпт + схема + парсинг), `AiCandidateApplyService.php` (создание нескольких `LexemeExample`), `ContentLexemeCandidate.php` (cast) | Тест: несколько примеров с разными `source` персистятся; `context`-пример становится primary; старая одиночная схема даёт тот же результат, что раньше | явный запрос пользователя после 9.1-9.8 |

**Definition of Done эпика (обновлено после 9.8/9.9)**: после добавления
YouTube-видео обычным пользователем (не админом) — как только анализ
готов, слова/фразы/грамматика выше порога уверенности уже в общем
каталоге, без единого клика; в приложении (не только в `/admin`) видно
пассивное уведомление "AI добавил: N слов, M грамматических конструкций";
`ContentTokenizer` остаётся только для слов, которые AI не покрыл, и это
видно пользователю; кандидат с несколькими примерами (минимум один —
реальная цитата из транскрипта) корректно раскладывается в несколько
`LexemeExample`; ни одно слово не дублируется в каталоге между разными
контентами благодаря существующему matching.

## 11. EPIC 10 — Lexical Sense Model & Auto-Enrichment

Цель: пользователь изучает не отдельную словоформу, а полноценную
лексическую единицу — лемму с частью речи, несколькими значениями
(senses), у каждого из которых свои переводы и примеры, — а конкретное
употребление в контенте (форма + грамматика + предложение) хранится
отдельным, явно связанным слоем. Не переизобретаем модель с нуля: уже
существующая `Lexeme → LexemeTranslation/LexemeExample/LexemeAssociation`,
`ContentLexeme` как per-content occurrence — процентов на 60 соответствует
целевой структуре. Достраиваем её до `Lexeme → LexemeSense → переводы/
примеры`, при этом старые sense-less строки остаются валидными (`NULL` на
`lexeme_sense_id` = "значение ещё не размечено", не ошибка, бэкофилл не
нужен). Сложный граф знаний и автоматическая идеальная разметка связей —
осознанно не в этом эпике: берём именно тот MVP-набор, который сам запрос
называет первым шагом — лемма, формы, часть речи, несколько значений,
несколько переводов, общие и контентные примеры, простые типы связей
(синонимы/антонимы/однокоренные/фразовые глаголы/коллокации).

### Три конкретных гэпа в текущей модели

1. **Лемма не отделена от словоформы на этапе AI-извлечения.**
   `AiContentAnalysisService::responseSchema()` просит у LLM одно поле
   `text` без уточнения "дай начальную/словарную форму" — `CandidateMatchingService::exactLexemeMatch()`
   и `CanonicalLexemeSyncService::sync()` матчат/создают каноническую
   `Lexeme` прямо по этому полю. Слово, встретившееся в форме "ran", рискует
   осесть в каталоге отдельной леммой от "run" вместо того, чтобы стать её
   словоформой — конкретный сценарий, из-за которого пользователь прогресс
   по "run" не увидит, выучив "ran".
2. **Часть речи появляется только постфактум.** `part_of_speech` сегодня
   приходит не из самого извлечения, а отдельным асинхронным
   `SuggestLexemeLevelJob`/`AiExplainLexemeService::suggestMetadata()` —
   одно плоское значение на лемму, после того как лемма уже создана.
3. **Значения (senses) не существуют как сущность.** Переводы и примеры
   висят прямо на `Lexeme`, поэтому два разных значения одного слова
   (bank-банк vs bank-берег) молча схлопываются в одну запись: последний
   применённый кандидат просто перезаписывает primary-перевод/добавляет
   пример к тому же ряду, без разделения по смыслу.

Плюс два соседних гэпа, которые сам запрос выделил отдельно:

4. **Автообогащение связей — не автоматическое.** `LexemeEnrichmentService`
   уже умеет предлагать синонимы через AI, но (a) только тип `synonym`
   захардкожен, (b) запускается только вручную кнопкой "AI: Enrich" в
   Filament — не срабатывает сам, когда в каталоге появляется новая лемма.
5. **Ручное добавление слова из транскрипта — отдельный, бедный путь.**
   Клик по слову в `TranscriptViewer.vue` → `TranscriptController::createLexeme`
   → `TranscriptLexemeLookupService::lookup()` создаёт `ContentLexeme`+
   `Lexeme`, но только с одним голым переводом (`AiExplainLexemeService::translate()`),
   без леммы/части речи/значения/примеров, и не умеет работать с выделением
   нескольких слов (только один токен) — гораздо более бедный результат,
   чем то же слово, извлечённое AI-пайплайном.

| # | Задача | Делаем | Миграции | Классы | Тесты | Источник |
|---|---|---|---|---|---|---|
| 10.1 | Лемма ≠ словоформа в AI-извлечении | `AiContentAnalysisService::buildSystemPrompt()`/`responseSchema()` просит у LLM отдельно `lemma` (словарная форма, "run") и `text` (форма как встретилась, "ran") на каждый lexeme-candidate, плюс `grammar` (краткий объект: `tense`/`number`/`person`/`degree`/`case`/`aspect`/`is_irregular`, LLM опускает нерелевантные поля). Матчинг (`CandidateMatchingService::exactLexemeMatch`) и создание канонической леммы (`CanonicalLexemeSyncService::sync`) переключаются на `lemma`/`normalized_lemma` кандидата вместо `text`. `ContentLexeme.text` остаётся тем, что показывается пользователю (форма из контента), не переименовывается | `add_lemma_and_grammar_to_content_lexeme_candidates_table` (`lemma`, `normalized_lemma` индекс, `grammar_features` json), `add_grammar_features_to_content_lexemes_table` (`grammar_features` json) | Правка `AiContentAnalysisService.php`, `CandidateMatchingService.php`, `CanonicalLexemeSyncService.php`, `AiCandidateApplyService.php` (копирует `grammar_features` на `ContentLexeme`) | Тест: транскрипт со словом "ran" → `Lexeme.lemma = "run"`, `ContentLexeme.text = "ran"`, одна каноническая лемма на оба варианта в разных прогонах ("ran" и "runs" не плодят два `Lexeme`) | user request; конкретный баг из примера пользователя |
| 10.2 | Часть речи на этапе извлечения | Добавить `part_of_speech` прямо в `responseSchema()` (сейчас появляется только позже, асинхронно, через `SuggestLexemeLevelJob`/`AiExplainLexemeService::suggestMetadata()`). `CanonicalLexemeSyncService::sync()` проставляет `part_of_speech` сразу при создании леммы, если кандидат его дал уверенно; `SuggestLexemeLevelJob` остаётся как fallback только для леммы, созданной без него (токенайзерный/ручной путь, см. 10.6) | — | Правка `AiContentAnalysisService.php` (схема+промпт), `CanonicalLexemeSyncService.php` | Тест: новый кандидат с `part_of_speech` не запускает лишний `SuggestLexemeLevelJob` для этого поля; кандидат без него — запускает, как сейчас | 10.1 |
| 10.3 | Sense как отдельная сущность | Новая таблица `lexeme_senses` (`lexeme_id`, `part_of_speech` nullable — может отличаться от sense к sense, `gloss` — короткое значение типа "двигаться бегом", `sort_order`). `LexemeTranslation`/`LexemeExample`/`ContentLexeme` получают nullable `lexeme_sense_id` (обратная совместимость: `NULL` = слово ещё не размечено по значениям, старые данные не трогаем). AI-схема получает поле `sense` (короткий глосс) на кандидата; `AiCandidateApplyService` резолвит-или-создаёт `LexemeSense` внутри уже известной леммы по точному совпадению нормализованного глосса (дешёвый дедup в рамках одной уже disambiguated леммы — не embedding-инфраструктура, сознательно просто на этом этапе), затем вешает перевод/примеры/`ContentLexeme.lexeme_sense_id` на него вместо самой леммы напрямую | `create_lexeme_senses_table`, `add_lexeme_sense_id_to_lexeme_translations_table`, `add_lexeme_sense_id_to_lexeme_examples_table`, `add_lexeme_sense_id_to_content_lexemes_table` | `Lexeme/LexemeSense.php`, правка `Lexeme.php` (`senses(): HasMany`), `LexemeTranslation.php`, `LexemeExample.php`, `ContentLexeme.php`, `AiContentAnalysisService.php`, `AiCandidateApplyService.php` | Тест: два кандидата с одной леммой, разными `sense` → два `LexemeSense`, каждый со своими переводом/примером; один и тот же `sense` дважды → переиспользуется, не дублируется | 10.1, 10.2; предложенная структура `Lexeme → senses → translations/examples` |
| 10.4 | Расширение типов связей + авто-обогащение при появлении новой леммы | `LexemeAssociation` получает `TYPES` константу (synonym, antonym, near_synonym, cognate, word_family, phrasal_verb, collocation, grammatical, thematic, homograph) — валидация без изменения схемы БД (поле и так свободная строка). `LexemeEnrichmentPromptBuilder`/`LexemeEnrichmentService::propose()` возвращают типизированный `related[]` (`{lemma, type, gloss}`) вместо захардкоженного `synonyms`. Новый `EnrichLexemeAssociationsJob` (по образцу `SuggestLexemeLevelJob`) диспатчится из `AiCandidateApplyService`/`AiCandidateAutoApplyService`, когда каноническая `Lexeme` только что создана (`wasRecentlyCreated`) — вызывает `LexemeEnrichmentService::propose()` и сам сохраняет топ-N связей без участия админа (это и есть "всё связанное добавляется сразу", по аналогии с уже принятым в EPIC 9.8 решением про auto-apply). Гейт своим конфиг-флагом (`ai.lexeme_relations_enrichment.enabled`, по образцу `ai.lexeme_level_suggestion`) | — | `Jobs/EnrichLexemeAssociationsJob.php`, правка `LexemeEnrichmentService.php`, `LexemeEnrichmentPromptBuilder.php`, `LexemeAssociation.php`, `config/ai.php` | Тест: создание новой леммы через AI-пайплайн ставит в очередь job; job создаёт связи с правильными типами, не дублирует существующие (`firstOrCreate` по `[lexeme_id, related_lexeme_id, type]`, уже есть unique-индекс) | 10.1-10.3; существующий `LexemeEnrichmentService`/admin "AI: Enrich" |
| 10.5 | Фронтенд: значения / формы / связи | `GET /api/dictionary/{id}` отдаёт `senses: [{ id, part_of_speech, gloss, translations[], examples[] }]` рядом со старыми плоскими `translations`/`examples` (fallback для sense-less записей). `LexemeDetailPage.vue` рендерит каждое значение отдельной карточкой (нумерованные значения). Новая секция "Формы" — список различных `ContentLexeme.text`, реально встретившихся у этой леммы в контенте, сгруппированных по `grammar_features` (без отдельной таблицы спряжений — только то, что реально видели, в духе уже принятой в проекте философии "слово из твоего контента"). `WordListItem.vue`/`LexemeWithLearned` получают `grammar_features`-бейдж ("прош. время") и `sense_gloss` под переводом. `shared/lexemeAssociations.ts` — новые лейблы/тона для типов из 10.4 | — | Правка `dictionaryApi`/сериализатора на бэке, `types/lexeme/LexemeDetail.ts`, `LexemeDetailPage.vue`, `types/lexeme/LexemeWithLearned.ts`, `WordListItem.vue`, `shared/lexemeAssociations.ts` | Feature-тест: ответ API содержит `senses[]` с вложенными переводами/примерами | 10.3, 10.4 |
| 10.6 | Ручное добавление слова из транскрипта — та же логика полного обогащения | Сегодня клик по слову в транскрипте (`TranscriptController::createLexeme` → `TranscriptLexemeLookupService::lookup`) создаёт `ContentLexeme`+`Lexeme`, но только с одним голым переводом (`AiExplainLexemeService::translate()`), без леммы/части речи/значения/примеров — отдельный, гораздо более бедный путь, чем AI-пайплайн. Переделать: `TranscriptLexemeLookupService` вызывает новый синхронный метод `AiExplainLexemeService::analyzeForManualAdd(text, sentenceContext, language, translationLanguage)` — один JSON-вызов (как остальные методы этого сервиса), возвращающий кандидатский набор полей (`lemma`, `part_of_speech`, `sense` gloss, `translation`, `examples[]` с `source: "context"` из окружающего предложения сегмента, `level`, `grammar_features`) в той же форме, что схема `AiContentAnalysisService`. Результат прогоняется через **тот же** `AiCandidateApplyService`-путь (создать/переиспользовать временный `ContentLexemeCandidate` со `status = accepted` и применить его существующей логикой `applyLexemeCandidate()`), а не через отдельный урезанный код — гарантирует, что лемма/значение/переводы/примеры/связи (10.1-10.4) применяются одинаково независимо от того, как слово попало в каталог. Также снимается сегодняшнее ограничение "только один токен": `end_offset > start_offset`, спанящий несколько токенов, даёт `type = TYPE_PHRASE` вместо `TYPE_WORD` | — (переиспользует миграции 10.1-10.3) | Правка `TranscriptLexemeLookupService.php`, `AiExplainLexemeService.php` (новый метод), `TranscriptController::createLexeme` (валидация под многословный диапазон) | Тест: клик по неразмеченному слову создаёт `ContentLexeme` с тем же набором полей (лемма/POS/sense/примеры), что и слово из AI-пайплайна; выделение двух слов создаёт `type=phrase` | 10.1-10.4; явный запрос пользователя |
| 10.7 | Фронтенд: единая карточка при ручном добавлении | Выделение слова/фразы в `TranscriptViewer.vue` (сейчас — только клик по одному размеченному токену; нужно добавить обработку выделения через `mouseup`/`window.getSelection()` для произвольного диапазона токенов) вызывает 10.6 и рендерит результат **тем же** компонентом `WordListItem.vue`, что и вкладка "Words" — с теми же кнопками (start/stop review, mark learned, skip) через уже существующий `useLexemes()` — вместо сегодняшней отдельной бедной инлайн-карточки (только слово+перевод+кнопки навигации) в `ContentDetailsPage.vue` | — | Правка `TranscriptViewer.vue` (выделение диапазона токенов, не только клик), `ContentDetailsPage.vue` (рендер `WordListItem` вместо текущей карточки на месте `transcriptWord`) | — (ручная проверка в браузере: выделить фразу в транскрипте → появляется та же карточка, что в списке слов, с рабочими кнопками) | 10.6 |

**Definition of Done эпика**: слово, встретившееся в форме "ran", попадает в
каталог под леммой "run" с частью речи "verb" и явным значением "двигаться
бегом", а не отдельной записью; у леммы есть хотя бы одно значение с
переводом и примером; при появлении новой леммы через AI-пайплайн для неё
без участия админа подбираются несколько типизированных связанных слов; на
странице слова видно несколько пронумерованных значений (если их несколько)
и список реально встретившихся форм; выделение слова или фразы прямо в
транскрипте даёт результат неотличимый по глубине данных от AI-извлечения
и показывается той же карточкой, что и в общем списке слов под видео.

## 12. Сквозной Definition of Done (на каждую задачу)

- Тесты зелёные, включая уже существующие (ничего не сломано).
- Новый `AgentTool`/`GraphNode` объявляет `sideEffect` явно — не остаётся
  без значения по умолчанию.
- Модель не получает прямой доступ к БД — только через `Tool → Service →
  Database` (ADR-002).
- Если задача добавляет узел, читающий внешний/сгенерированный текст —
  явные `<tool_output>` границы в промпте (1.8).
- Handoff/граф-переходы не превышают согласованных ограничений (глубина 2,
  fail-fast fan-in) без отдельного пересмотра ADR-006/раздела 12
  `agent-framework-roadmap.md`.

## 13. Как исполнять: по одной задаче в отдельном контексте

Каждая строка таблиц выше — отдельный PR и, как правило, отдельная сессия
(новый чат): контекст не тратится на историю предыдущих задач, а нужные
факты — в этом файле и в самом коде.

- **Внутри эпика — последовательно**, задачи явно зависят друг от друга
  (особенно EPIC 1 и EPIC 4). Не начинать 1.5 в отдельном контексте, пока
  не смёржены 1.2-1.4.
- **Между EPIC 2 и EPIC 3 — можно параллельно** (см. диаграмму в разделе 1,
  они не пересекаются): разумно вести как две отдельные ветки/контекста.
- **Формат новой сессии**: указать конкретный ID задачи (например
  `EPIC-1.2`) — этого достаточно, чтобы прочитать нужный контекст (эту
  таблицу + при необходимости `agent-framework-roadmap.md`/ADR по ссылке из
  колонки "Источник") без пересказа предыдущих сессий.
- Отмечать задачи в чек-листе ниже по мере готовности — он в git, значит
  виден в любом новом контексте, в отличие от истории чата.

## 14. Чек-лист прогресса

### EPIC 1 — AI Core Runtime
- [x] 1.1 Характеризационные тесты `ContentAgentService`
- [x] 1.2 Чистый `AgentLoop`
- [x] 1.3 `sideEffect` как инвариант на этапе wiring
- [x] 1.4 `AgentBlueprint`
- [x] 1.5 `ContentAgentService` на `AgentLoop`/`AgentBlueprint`
- [x] 1.6 `TraceContext`/`SpanRecorder`
- [x] 1.7 Хранение трейсов + отчёт
- [x] 1.8 Guardrails рантайма (prompt injection, rate limit)
- [x] 1.9 `agent_type` + обобщённый `RunAgentTurnJob`
- [x] 1.10 Скелет `StudentTutorAgentService`

### EPIC 2 — RAG & Knowledge
- [x] 2.1 Определить корпус
- [x] 2.2 ES-индекс
- [x] 2.3 Индексация корпуса
- [x] 2.4 Retrieval-сервис
- [x] 2.5 Апгрейд `ExplainGrammarTool`/`FindExamplesTool` на RAG
- [ ] 2.6 Semantic memory индекс (после 4.13) — 4.13 done (event publishes + is captured to event_log), but the ES semantic-memory index/consumer write itself is still not built; this is now the next unblocked step, not a hard blocker

### EPIC 3 — TutorAgent MVP
- [x] 3.1 `TutorAgent` blueprint
- [x] 3.2 Memory tools
- [x] 3.3 Progress tools
- [x] 3.4 Learning tools
- [x] 3.5 HTTP + SPA
- [x] 3.6 Streaming
- [x] 3.7 `GenerateQuizTool`
- [x] 3.8 Access control

### EPIC 4 — Agent System & Workflow Engine
- [x] 4.1 Ядро графа
- [x] 4.2 `AiAnalysisRun` как граф (additive proof — production job/Filament action unchanged, see AiAnalysisGraphService docblock)
- [x] 4.3 `HandoffTool`
- [x] 4.4 `GrammarAgent`
- [ ] 4.5 `ExerciseAgent` — **decided: skip, not built.** `GenerateQuizTool` (3.7) still satisfies ADR-002's Tool boundary: it takes ready inputs (explicit `words[]`, or a deterministic non-LLM default pulled from `SrsCard` due queue — no LLM reasoning needed to pick words), makes exactly one `AiJsonClient::completeJson()` call, sanitizes structured output, and never loops/retries/adapts on its own result. There is no self-correction step ("is the difficulty right? if not, regenerate" from the vision doc's `ExerciseAgent` diagram) implemented or requested anywhere in the codebase today, and no production usage data (fresh implementation, no real traffic) suggesting one call is insufficient — the roadmap explicitly says to verify on real usage before building this, not build it "just in case" (that would contradict ADR-002 and engineering-principles.md directly). Concrete trigger to revisit: real usage showing quizzes ignoring student level/history in a way a single call can't fix, or a genuine need for a generate→self-check→regenerate loop.
- [x] 4.6 `ReviewAgent`
- [x] 4.7 `TutorAgent`-граф (additive — not yet wired into TutorConversationController, see final report)
- [x] 4.8 `HumanCheckpointNode`
- [x] 4.9 Дешёвый fan-out — implemented as one batched `embedBatch()` request instead of `Http::pool()`, see final report for reasoning
- [x] 4.10 Реестр узлов + данные
- [x] 4.11 `ParallelNode`
- [x] 4.12 `PlanningAgent`
- [x] 4.13 Kafka `exercise.completed` (publish + capture; ES semantic-memory write itself is EPIC 2.6, not yet built — see final report)
- [x] 4.14 Рекомендации на эмбеддингах

### EPIC 5 — Production AI
- [x] 5.1 Evals
- [x] 5.2 Стоимость трейсов
- [x] 5.3 OpenTelemetry / ES-экспорт
- [x] 5.4 Semantic cache
- [x] 5.5 Графы в БД — **reversed 2026-08-20, built.** Originally skipped
  (see the "decided: skip" reasoning kept below for the historical
  record) — the actual reason it got built was a direct product decision
  (user explicitly asked for a visual graph/prompt builder), not the
  original trigger condition firing; that trigger ("2-3 real variations of
  the same graph") was still never met. `graph_definitions` +
  `graph_definition_versions` tables, `GraphDefinitionAdminService`
  (draft/publish, publish re-validates via `GraphNodeRegistry::buildDefinition()`
  before activating), `GraphDefinitionResolver` checks the DB before
  falling back to the hand-built PHP class — additive, the three existing
  hand-built graphs are unaffected until an admin explicitly publishes an
  override for one. Paired with a DB-backed prompt registry
  (`prompt_templates`/`prompt_template_versions`, `PromptRegistryService`)
  covering every direct-call AI service's prompt too, not just graph
  nodes — see `App\Modules\Ai\Application\PromptCatalogService` for the
  full inventory grouped by real user-facing flow. Admin UI:
  `/admin/ai-builder` (Vue Flow canvas + prompt editor), gated by
  `manage-ai-builder` (admin-only). Original reasoning, superseded but
  kept for context:

  > The roadmap text itself gates this on "когда накопится 2-3 реальных
  > вариации графа" (once 2-3 real graph variations accumulate). As of this
  > epic there is exactly one `GraphDefinition` genuinely in data form:
  > `AiAnalysisGraph` (`config('ai.graph.definitions')`, task 4.10).
  > `TutorRoutingGraph` and `StudyPlanGraph` also exist as `GraphDefinition`
  > classes, so by a loose count there are three — but neither is a
  > *variation* of the same pipeline the way the roadmap's phrasing implies
  > (the trigger is meant to be "the same kind of graph keeps getting
  > hand-edited/rebuilt slightly differently," motivating a data-driven
  > builder); they are three structurally different graphs
  > (content-analysis pipeline, tutor routing, study-plan fan-out) built
  > once each and not yet modified since. There is no evidence yet of the
  > actual pain a DB-backed graph + admin UI would solve (nobody has needed
  > to tweak a graph's shape without a deploy). Same reasoning EPIC 4's
  > agent applied to 4.5 (`ExerciseAgent`): building this "for
  > completeness" would contradict engineering-principles.md's "не тащить
  > сложность раньше времени" directly.
- [x] 5.6 Redis-кэш

### EPIC 6 — Chat UX & Observability
- [x] 6.1 Контекстный вход в чат (AskAiButton + query-контекст) — verified
  `ensureConversation()` does not persist a conversation across page loads
  today (no server-side "reuse active conversation" lookup exists); left
  that behavior unchanged, context rides on top of it via query params +
  one-time message prefix, per design
- [x] 6.2 Прозрачность агента (SSE tool_start/tool_end/handoff) — extended
  `AgentLoopObserver` with `onToolCallStarted()`, reused for both `run()`
  and `runStreaming()`; handoff tools emit `handoff` instead of
  `tool_start`/`tool_end` (no separate "handoff_end" — see
  `StudentTutorAgentService::observerFor()`'s docblock)
- [x] 6.3 Интерактивная карточка квиза — mechanism is DB-mediated, not a new
  AgentLoop pipe: `onToolCallCompleted()` already persists `tool_result`
  before the turn ends, so `streamTurn()` just re-reads `generate_quiz`
  tool messages created after the turn's own user message and attaches
  them to `done` as `toolResults` (documented in that method's docblock)
- [x] 6.4 Ответ на квиз-карточку (клиентская проверка, без записи прогресса) —
  `QuizCard.vue`, entirely client-side string-compare grading, no API call
- [x] 6.5 Роли на фронте (authStore, скрытие nav, дружелюбный 403) — no
  authStore unit test added: this environment has no frontend test runner
  at all (no vitest, no lint script — see task brief), so "Тест authStore"
  from the table isn't achievable without introducing a new test framework,
  out of scope for this task; verified via `npm run build` (clean) instead
- [x] 6.6 Рекомендации слов на фронте — `useRecommendedLexemes()` composable
  (same shape as `useRecommendedContents()`), "Words to learn next" section
  on `DashboardPage.vue`, linking to the recommended word's content
  (the endpoint returns a `content_lexeme` id + `content_id`, not a
  canonical lexeme id, so `word.details` isn't the right link target here)
- [x] 6.7 Судьба старого `/chat` — investigated before removing anything:
  backend (`AiConversationController`/`ChatContextAiService`/
  `AiChatOverviewWidget`) is still genuinely used (Filament admin panel,
  and `ChatContextAiService` wraps `SemanticCacheService` from 5.4) — left
  alone. Frontend `api/chatApi.ts` had zero callers besides its own
  re-export in `domains/ai/index.ts` — both removed as dead code
- [x] 6.8 Минимальная видимость трейсов/graph runs для админа —
  `AgentTraces`/`AgentTraceSpans`/`AgentGraphRuns` Filament pages, plain
  Blade tables (not the Livewire table-builder) since a raw
  `LengthAwarePaginator` isn't Livewire-syncable as a public property —
  computed as a plain method on each render instead

### EPIC 7 — Word Level & Bulk Management
- [x] 7.1 Уровень слова в API
- [x] 7.2 Автопроставление уровня — gated behind its own
      `ai.lexeme_level_suggestion.enabled` flag (independent of the general
      `ai.enabled`), off by default; see config/ai.php docblock for why
      (avoids QUEUE_CONNECTION=sync-in-tests side effects on unrelated
      tests that flip `ai.enabled` and create ContentLexeme rows)
- [x] 7.3 Фильтр по уровню — also added the level badge to
      `WordListItem.vue` itself (beyond the roadmap's StudyPage.vue-only
      scope), since that's the only place the fetched `level` field can
      become visible to a user in the first place
- [x] 7.4 Массовый выбор (чекбоксы) — "select all" = all rows under the
      current filter (StudyPage renders the full word list at once, no
      pagination, confirmed in code); selection persists across filter
      changes (not auto-cleared), a "Clear" action is offered instead
- [x] 7.5 Массовые действия (известные / добавить к изучению) — response
      reports per-id `{id, ok, message?}` plus `succeeded`/`failed` counts
      (not all-or-nothing); a batch with an invalid/foreign id is rejected
      at validation (422, existing `exists:` convention), a per-id runtime
      failure after validation is caught and reported without losing the
      rest of the batch (see LexemeBulkActionsTest)
- [x] 7.6 Известные слова скрыты по умолчанию
- [x] 7.7 Табы вместо скролла (Words/Grammar/Full text) — `UiTabs.vue`
      keeps every panel mounted (v-show, not v-if), so switching tabs never
      resets the Words tab's own state; both `AskAiButton` usages from 6.1
      stay outside the tabbed area, untouched
- [x] 7.8 Уровень/выбор/массовые действия — везде (исправление: изначально
      сделано только на StudyPage.vue, запрос был про страницу с видео) —
      extracted `shared/ui/WordListToolbar.vue`; owns the status filter,
      level chips, "select all filtered"/clear, and the bulk-action bar,
      calling the bulk-mark/bulk-start functions passed in from the host
      page's own `useLexemes()`. Deliberately does not own the search box or
      `<WordListItem>` rendering (those stay page-specific) — a `#search`
      slot lets each page keep its search input in the same visual spot, and
      `searchQuery` is accepted as a prop so "select all" still respects an
      active search term. `StudyPage.vue` switched to it with no visible
      behavior change; `ContentDetailsPage.vue`'s Words tab now gets the same
      toolbar wired to its own `useLexemes()` instance for that content.

### EPIC 8 — AiAnalysisGraph Cutover (Beta)
- [x] 8.1 Guard на matching (best-effort, как в живом джобе)
- [x] 8.2 Статус `AiAnalysisRun` из графа (running/completed/failed) —
  `completed` is set both when the run pauses at `review_checkpoint`
  ("ready for review", same meaning as on the live path) and if it ever
  fully completes; `approveAndResume()` deliberately does not touch
  `AiAnalysisRun.status` again, matching `AiCandidateApplyService::apply()`
- [x] 8.3 Асинхронный запуск (`RunAiAnalysisGraphJob`) — note: unlike the
  live job, an `analyze` failure does not make this job throw (`GraphRunner`
  already catches node exceptions into a `failed` `GraphRunResult`), so
  `tries`/`backoff()` only cover infra-level failures, not analysis/LLM
  ones — see that job's docblock
- [x] 8.4 Кнопка в админке (бета), параллельно живой кнопке —
  `AnalyzeWithAiGraphAction`, same `AiAnalysisRunConfig` schema as the live
  action for a fair side-by-side comparison; creates its own
  `AiAnalysisRun` row, verified no unique constraint on `content_id` so it
  coexists with the live path's run on the same content
- [x] 8.5 Кнопка "Approve & Apply" на странице `AgentGraphRuns` — shown
  only for `AiAnalysisGraph` runs paused at `review_checkpoint`; calls
  `AiAnalysisGraphService::approveAndResume()` as-is, no second review UI

### EPIC 9 — AI-First Word Extraction & User-Facing Review
- [x] 9.1 Coverage-метрика + один авто-ретрай с thorough — персистит и сам
  список непокрытых слов (`uncovered_words`), не только `coverage_pct`
- [x] 9.2 Мои pending-предложения (API, авторизация по created_by)
- [x] 9.3 Принять предложения (API, тот же гейт)
- [x] 9.4 UI ревью в приложении (не админка)
- [x] 9.5 Токенайзерные слова — только незакрытый остаток
- [x] 9.6 Напоминание о pending-ревью в "My submissions"
- [x] 9.7 Автоподстановка part_of_speech вместе с level (расширение `SuggestLexemeLevelJob`) — enum перенесён в `Lexeme::PARTS_OF_SPEECH` (не `LexemeForm`), см. таблицу выше
- [x] 9.8 Отмена ревью — полное авто-применение (`AiCandidateAutoApplyService`, confidence floor 0.5 по умолчанию) — явно отменяет решение "ревью переезжает к автору" из 9.2-9.4, см. блок выше
- [x] 9.9 Мультипример на кандидата (`examples` JSON, context+generated) — старые `example`/`example_translation` оставлены как backward-compat зеркало

### EPIC 10 — Lexical Sense Model & Auto-Enrichment
- [x] 10.1 Лемма ≠ словоформа в AI-извлечении — `CanonicalLexemeSyncService`
  разделён на `sync(ContentLexeme)` (токенайзерный/ручной путь, поведение не
  изменилось) и новый `syncLemma(language, lemma, ?partOfSpeech)`; при apply
  неотматченного AI-кандидата каноническая `Lexeme` резолвится по
  `candidate->lemma` **до** создания `ContentLexeme`-occurrence, и occurrence
  создаётся сразу с готовым `lexeme_id` — так `ContentLexeme::booted()`'s
  auto-sync hook (который использовал бы `text`, т.е. "ran", как лемму)
  вообще не срабатывает. Матчинг (`CandidateMatchingService`) тоже переведён
  на `normalized_lemma`/`lemma` вместо `normalized_text`/`text`, с фоллбэком
  на старые поля для кандидатов, созданных до этой миграции
- [x] 10.2 Часть речи на этапе извлечения — `part_of_speech` теперь в
  `responseSchema()`/промпте (валидируется по `Lexeme::PARTS_OF_SPEECH`),
  проставляется на новую каноническую `Lexeme` через `syncLemma()` при
  создании и бэкфиллится (`whereNull`-гвард, тот же паттерн, что и у
  `level`) для уже существующей/сматченной леммы, если та ещё не размечена;
  `SuggestLexemeLevelJob` остаётся фоллбэком для лексем без него
  (tokenizer/manual путь)
- [x] 10.3 Sense как отдельная сущность — новая `lexeme_senses`
  (`lexeme_id`, `part_of_speech`, `gloss`, `normalized_gloss` уникален в
  рамках леммы), `LexemeSenseSyncService::sync()` — дешёвый exact-match
  дедup по нормализованному глоссу **внутри уже disambiguated леммы**, без
  embedding-инфраструктуры. `lexeme_sense_id` (nullable) добавлен на
  `LexemeTranslation`/`LexemeExample`/`ContentLexeme` — `NULL` означает
  "значение ещё не размечено", старые записи не тронуты, обратная
  совместимость полная. `AiCandidateApplyService` резолвит sense сразу
  после леммы и скоупит "primary"-проверку translation/example по
  `(lexeme_sense_id ?? IS NULL)`, так что разные значения одного слова
  (bank-банк vs bank-берег) получают каждое свой primary перевод/пример,
  а не перезаписывают друг друга
- [x] 10.4 Расширение типов связей + авто-обогащение при появлении новой
  леммы — `LexemeAssociation::TYPES` (synonym, near_synonym, antonym,
  cognate, word_family, phrasal_verb, collocation, grammatical, thematic,
  homograph, related). `LexemeEnrichmentPromptBuilder`/`LexemeEnrichmentService`
  переведены с захардкоженного `synonyms` на типизированный `related[]`
  (обратная совместимость не сохранялась — форма жила только внутри одного
  запрос-ответ цикла Filament-формы, персистентных данных старой формы не
  существует); Filament-экшен "AI: Enrich" обновлён под новую форму. Новый
  `EnrichLexemeAssociationsJob` (по образцу `SuggestLexemeLevelJob`)
  диспатчится из `CanonicalLexemeSyncService::syncLemma()` при
  `wasRecentlyCreated` — работает для любого пути создания леммы (AI,
  токенайзер, ручное добавление), не только AI-кандидатов. Гейт
  `ai.lexeme_relations_enrichment.enabled` (по умолчанию `false`, та же
  причина изоляции, что у `lexeme_metadata_suggestion` — фильтр не даёт
  тестам с `QUEUE_CONNECTION=sync` ловить побочные реальные AI-вызовы)
- [ ] 10.5 Фронтенд: значения / формы / связи
- [x] 10.6 Ручное добавление из транскрипта — та же логика полного
  обогащения — новый `AiExplainLexemeService::analyzeForManualAdd()`
  (один JSON-вызов, та же схема полей, что у `AiContentAnalysisService`:
  lemma/part_of_speech/sense/grammar/translation/level/example_translation;
  общая валидация вынесена в `LexemeCandidateFields`, переиспользуется
  обоими сервисами). `TranscriptLexemeLookupService` для нового слова
  создаёт временный `AiAnalysisRun` с одним accepted-кандидатом, прогоняет
  через `CandidateMatchingService::matchRun()` (дедуп по существующей
  лемме) и **немодифицированный** `AiCandidateApplyService::apply()` — без
  confidence-гейта, т.к. осознанный выбор пользователя сам по себе
  подтверждение. `origin` откатывается на `manual` после apply (apply()
  общий с батч-пайплайном и всегда проставляет `ai`) — обогащение теперь
  идентично, происхождение остаётся различимым. `type` определяется
  детерминированно по наличию пробела в выделении (`phrase`/`word`), снимая
  прежнее ограничение "только один токен"
- [ ] 10.7 Фронтенд: единая карточка при ручном добавлении

## 15. Что намеренно не входит в этот roadmap

- Визуальный редактор графов — не планируется (см. `agent-framework-roadmap.md`,
  раздел 7, "Что осталось вне рамок v1").
- Мультипровайдерный tool calling (Anthropic и др.) — до появления реальной
  потребности во втором провайдере с function calling.
- `SpeakingAgent` — требует TTS/STT, которых в проекте нет; отдельная
  предварительная задача вне этого roadmap, если/когда будет решено делать.
