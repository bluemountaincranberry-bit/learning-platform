# Module ownership map

This is the current ownership contract for the modular monolith. Runtime
models, jobs and integration services use their canonical module namespaces;
the temporary `App\\Models`, `App\\Jobs` and `App\\Services` wrappers have
been removed. The persisted User morph discriminator remains the stable string
`App\\Models\\User` for existing Spatie rows, without requiring an alias class.

## Persistence owners

| Module | Models and durable state | Public boundary |
|---|---|---|
| User | `User`, `UserLearningPreference` | Auth, profile and learner identity contracts |
| Content | `Content`, `ContentLexeme`, `Lexeme`, `LexemeSense`, `LexemeTranslation`, `LexemeExample`, `LexemeAssociation`, `TranscriptSegment`, `TranscriptSegmentTranslation`, `GrammarTopic`, `GrammarRule`, `GrammarRuleExample`, `GrammarRuleExercise`, `ContentRuleLink`, `ContentExamAttempt`, `GrammarExamAttempt`, `ContentLexemeCandidate`, `ContentGrammarCandidate`, `ClozeExample` | Published catalog, transcript, candidate application and grammar contracts |
| Learning | `ExerciseAttempt`, `LearningProgress`, `LearningRetry`, `SelfCheckSubmission`, `LearningFlowProfile`, `LearningFlowAssignment`, `LearningPointEvent`, `LearningFlowMetricEvent`, `UserGrammarRule`, `UserLexemeProgress`, `UserLexemeSkip`, `UserLexemeConfidence`, `UserLexemeContextCheck` | Study, attempts, learner state, progress and learning-flow contracts |
| SRS | `SrsCard`, `SrsReview` | Due queue and review contracts; accessed through `SrsRepositoryInterface`, always scoped by acting user |
| AI | `AiAnalysisRun`, `AiConversation`, `AiMessage`, `AgentConversation`, `AgentMessage`, `AgentGraphRun`, `AgentGraphBranchResult`, `AgentTrace`, `AgentTraceSpan`, `AiSemanticCacheEntry`, `LexemeEmbedding`, `CanonicalLexemeEmbedding`, `GrammarRuleEmbedding`, `Lesson`, `LessonAnalysisRun`, `LessonLexemeCandidate`, `LessonGrammarCandidate`, `PromptTemplate`, `PromptTemplateVersion`, `PersistedGraphDefinition`, `PersistedGraphDefinitionVersion`, `ModelPricing` | Provider-independent capabilities, agents, retrieval, prompts and observability |
| Infrastructure / audit | `EntityRevision`, `EventLog`, `OutboxEvent`, `EventConsumption` | Cross-cutting audit, outbox and integration event contracts; canonical persistence lives in `Modules/Infrastructure` |

## Invariants

1. User-owned state is always scoped by the acting user; an unknown or foreign
   resource is returned as not found at an HTTP boundary where disclosure is
   not useful.
2. Content visibility is a Content decision: only `ready` content is public.
   Learner-facing Content, Learning and AI flows must use that decision rather
   than duplicating status checks.
3. Learning owns attempts and progress outcomes. SRS owns card/review
   persistence and accepts review commands through its contract.
4. AI may read published Content and learner signals through explicit
   application contracts, but it must not write learning progress, SRS state or
   content visibility directly.
5. Redis, queues and Kafka are infrastructure mechanisms. Durable business
   truth remains in Postgres transactions.

## Migration rule

New code must import a public module boundary or an application-wide contract.
AI provider capabilities shared by several modules live in `App\\Contracts\\Ai`.
Consumer-owned ports keep dependency direction one-way; implementations are
bound by the provider of the module that owns the data or behavior.
