# Rewrite: AI platform

## Цель

Построить provider-independent AI platform с отдельными capabilities,
надёжностью, evaluation, cost controls и observability.

## Scope

- Разделить Core, Providers, Prompting, Runtime, Capabilities, Retrieval,
  Evaluation и Observability.
- Ввести contracts для text/JSON/streaming/tool-calling/embeddings.
- Добавить routing, retry, timeout, idempotency и failure classification.
- Зафиксировать tool side-effect permissions.
- Добавить token/cost/latency tracing и evaluation fixtures.
- Переписать AI use cases по capability boundaries.

## Out of scope

- Новый provider без evaluation.
- Микросервисный AI backend.
- Автономные agents без permission boundary.

## План

- [x] Написать первый capability slice для lexeme explanation.
- [x] Выделить provider construction и tracing/cache boundary.
- [x] Выделить lexeme metadata suggestion в отдельную capability.
- [x] Выделить context sentence generation и lexeme translation capabilities.
- [x] Написать provider-independent contract tests.
- [x] Выделить sentence practice generation и answer grading в отдельные
  capability contracts/services; `SentencePracticeService` остаётся фасадом
  для совместимого application API.
- [x] Перевести batch content analysis job, graph node, tool и eval command
  на `ContentAnalysisCapability`, сохранив legacy concrete service для
  прямых тестов и постепенной миграции.
- [x] Перенести AI runtime jobs (`RunAgentTurn`, content/lesson analysis и
  analysis graph) в `Modules/Ai/Interfaces/Jobs`.
- [x] Перенести graph fan-out/resume jobs и metadata suggestion job в
  `Modules/Ai/Interfaces/Jobs`.
- [x] Выделить provider adapters и model routing: chat and embeddings are
  constructed only through `AiProviderFactory`, with independent OpenAI/Ollama
  embeddings selection.
- [x] Переписать остальные capabilities по одному use case; sentence practice,
  content analysis, metadata, translation и context generation используют
  explicit capability boundaries.
- [x] Перенести agents/graphs/tools в runtime; graph execution, tool
  permissions, retries и human checkpoints покрыты integration tests.
- [x] Добавить evaluation и operational dashboards: golden eval command,
  trace reports, cost/usage spans, retrieval checks и Filament observability
  pages покрыты focused tests.

## Проверка

- Unit tests contracts/failure policies.
- Integration tests content analysis, tutor и recommendations.
- Evaluation regression suite.

Текущий slice: `LexemeExplanationService`, metadata suggestion, context
sentence generation, translation и content analysis выделены в capabilities;
`AiConversation`, `AiMessage`, `AiAnalysisRun`, `ContentLexemeCandidate` и
`ContentGrammarCandidate`, `AgentConversation`, `AgentMessage`, `AgentGraphRun`,
`AgentGraphBranchResult`, `AgentTrace` и `AgentTraceSpan` перенесены в
canonical AI namespace с legacy aliases; `Lesson`, `LessonAnalysisRun`,
`LessonLexemeCandidate` и `LessonGrammarCandidate` также перенесены в
canonical AI namespace с явными compatibility relations. Prompt templates,
persisted graph definitions, model pricing и semantic cache также переведены
на canonical AI persistence ownership; embedding persistence для lexeme и
grammar retrieval также переведена в AI module;
full backend suite проходит; operational/evaluation surfaces уже подтверждены
focused suite (42 tests, 128 assertions), а provider routing — embeddings
contract suite.

## Документация

- `docs/architecture/ai-platform-vision.md` и roadmap.

## Зависимости

- Baseline и backend foundation contracts.
