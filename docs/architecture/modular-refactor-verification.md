# Modular architecture refactor verification

Последний полный Laravel suite: **1003 passed, 13 failed, 3045 assertions**.

Все 13 failures относятся к интеграционным RAG/Elasticsearch сценариям и
завершаются `cURL error 28` при обращении к `http://elasticsearch:9200`.
Они не являются ошибками PHP domain refactor и требуют доступного/готового
Elasticsearch test index.

Frontend verification:

- Vue project typecheck — passed;
- Vite production build — passed;
- Playwright rewrite smoke — **8 flows passed**, including prompt save/unsaved state and graph save/unsaved state checks; one profile request aborts during auth transition but does not fail the smoke.
- frontend boundary checker — passed.
- PHP/frontend/naming architecture self-tests — passed;
- AI Builder shared `useDraftState` is used by Prompt Editor and Graph Canvas
  to expose unsaved changes and avoid redundant draft saves.
- Prompt draft validation now accepts an intentionally empty `user_template`
  for agent prompts while requiring the field to be present; focused prompt
  API/admin tests pass (35 tests, 71 assertions), with a regression test for
  null-to-empty normalization.
- Shared UI now includes `UiDialog` with modal semantics, Escape/backdrop close,
  focus-visible close control, and a busy state that prevents accidental close.
- Study explanation modal now consumes `UiDialog`, providing a representative
  learner screen for the shared keyboard/focus behavior.
- AI Builder save orchestration is shared by `DraftSaveBar`, used by both the
  prompt editor and graph canvas pages.
- `content-candidate-boundary.test.php` verifies that Content application and
  interface code do not import the concrete AI candidate service.
- `shared-ui.test.mjs` verifies dialog ARIA/Escape/focus/busy invariants and
  AsyncState retry wiring.

Disposable database verification: `DB_CONNECTION=sqlite DB_DATABASE=:memory:
php artisan migrate:fresh --force` — all migrations, including outbox and
event consumption tables, passed.

Все implementation-задачи OpenSpec выполнены. Архивирование change остаётся
отдельным действием после review.

Review points are protected by the unique `learning_point_events.source_key`
constraint (`srs_review:<id>`), so repeated review outcome handling cannot
create duplicate point awards.

Candidate application ownership is contract-first: Content owns candidate
state and the accepted-candidate writer; AI passes the run identifier through
the public port and dispatches the embedding job for newly created rules.
The focused candidate and graph-node tests pass (35 tests, 101 assertions).

The AI student tools now read vocabulary summary and history through
`StudentVocabularyReaderInterface`; AI content reset checks whether learned
lexemes are protected through `LearningProgressReferencesInterface`. Both
queries are implemented in Learning. Content readiness now receives learned
word/rule counts through `ContentReadinessProgressInterface`. Focused
AI/Learning/Content tests: **31 passed, 73 assertions**. The strict PHP
boundary checker passes with zero private imports and zero module cycles.

Sentence practice now reads recent grammar, lexeme confidence averages and
grammar progress through `SentencePracticeLearnerContextInterface`; its 10
feature tests pass (29 assertions). Content computes grammar confidence from
exam/SRS signals, then records it through Learning's
`GrammarConfidenceRecorderInterface`; its 5 feature tests pass (10 assertions).
The temporary PHP boundary baseline has been removed. Both the default and
strict checker now pass with **0 private cross-module imports and 0 module
cycles** across 470 module files.

`ContentService` now obtains learner flags, confidence and learned counts
through `ContentLearnerStateReaderInterface`; catalog API tests pass. An
expanded API run exposed a stale `ReviewGradeRules` reference in
`ProgressStatsService`; replacing it with `ReviewGradePolicyInterface` restored
the stats endpoint (7 focused tests, 26 assertions).

`LexemeLearningSelector` now lives in Content, where its only caller and the
catalog lexeme models live; catalog/knowledge API tests pass (22 tests, 88
assertions).

`LexemeService` now delegates learned/skip state changes to Learning's
`LexemeLearningStateWriterInterface`; progress, skip and bulk-action tests pass
(18 tests, 62 assertions). Content owns candidate moderation and matching
ports consumed by AI; focused candidate tests pass (14 tests, 43 assertions).

Profile progress/streak reads now use a scalar Learning contract, and
`User::lexemeProgress()` has been removed after migrating its callers and test
fixtures. Focused profile/statistics tests pass (46 tests, 149 assertions).

The SRS review callback now passes a scalar `ReviewOutcome` DTO and computes
failure in SRS before Learning applies points, retries and metrics (14 focused
tests, 47 assertions). `SuggestLexemeLevelJob` now lives in Content and the
old root job alias is gone (21 tests, 41 assertions). All runtime root model,
job and service aliases were removed. The legacy persisted user morph value is
resolved through an explicit morph map to the canonical User model. Grammar
catalog and revision tests pass (9 tests, 47 assertions).

AI lexeme explanation now obtains allowed CEFR levels and parts of speech from
Content's public metadata reader; its model constants remain the source of
truth (16 focused tests, 66 assertions). Six unused Learning-to-Content model
relations were removed after checking dynamic access and eager loading (67
focused tests, 196 assertions).

The three AI embedding jobs read Content-owned source text through
`EmbeddingSourceReaderInterface`; embedding persistence remains in AI. Their
focused suites pass (7 tests, 16 assertions).

AI analysis jobs request transcript lexeme linking through Content's public
`TranscriptLexemeLinkerInterface` (11 focused tests, 48 assertions). Lesson
candidate matching passes rule/user identifiers through the grammar progress
contract (19 tests, 61 assertions). Content now owns reset cleanup and rerun
operations behind `ContentResetOperationsInterface` (4 tests, 16 assertions).
Six unused AI model relations to Content/User were removed after checking
dynamic relation access; 43 focused tests pass (159 assertions).
The AI error sanitizer is now a public AI contract used by Content's lexeme
suggestion job (40 focused consumer tests, 120 assertions).

`User` no longer exposes seven unused cross-module relations to AI/Learning;
42 focused tests pass (128 assertions). Content's lexeme selector now accepts
learner goal and level values, not a User model (22 tests, 88 assertions).
The unused `Lexeme::progress()` relation was removed; catalog schema tests
still verify the Learning progress row by identifier (3 tests, 34 assertions).
RAG indexing reads published Content snapshots through `RagSourceReaderInterface`
with ID filtering and payload assertions (2 tests, 40 assertions). Candidate
analysis writes and coverage queries use `CandidateAnalysisStoreInterface`
owned by Content (36 tests, 117 assertions); active `AiAnalysisRun` relations
remain pending migration.

Lexeme candidate field validation now uses Content's public metadata options
(49 focused AI tests, 149 assertions). Grammar exercise generation reads a
Content-owned source snapshot and delegates draft validation/persistence to
Content (6 tests, 18 assertions). The learner progress creation hook resolves
canonical lexeme IDs through Content's public reader (18 tests, 73 assertions);
active ORM relations remain. The AI catalog search tool uses a Content-owned
search contract (1 focused test, 2 assertions).
Content readiness queries now take a learner ID instead of importing the User
model; readiness and catalog flows pass (27 tests, 99 assertions).
The AI student review schedule tool reads due counts and upcoming cards through
SRS's public `ReviewScheduleReaderInterface` (6 tests, 13 assertions).
Recent failed reviews for the AI student tool now come from SRS's
`ReviewMistakesReaderInterface` and its grade policy (10 memory-tool tests,
19 assertions). The older grade constant remains for weak-topic consumers
until their query is migrated.

AI-run creation and job dispatch are owned by AI behind
`AiAnalysisRunDispatcher`; Content retains transcript, active-run and quota
guards (21 focused tests, 43 assertions). Content gets submitter analysis
preferences through User's public reader (18 tests, 32 assertions). Quiz
defaults now read SRS review words through `ReviewScheduleReaderInterface`
(13 tests, 29 assertions). Chat conversation creation passes a user ID rather
than a User model (9 tests, 29 assertions). Two grammar explanation tools use
Content's published-rule snapshot contract (6 focused tests, 23 assertions);
four broader tool tests that index Elasticsearch still time out at 30 seconds.
AI's content-creation tool now asks Content to validate available types/levels
and create a draft, receiving scalar data for its result (2 tests, 8
assertions).

Content transcript manual-add calls AI through `ManualLexemeCandidateCapability`,
while candidate data and manual occurrence ownership stay in Content (6 tests,
31 assertions). Student vocabulary search uses Content's published catalog
reader (4 focused tests, 5 assertions). Graph test runs create throwaway
Content through a public Content factory; the transaction still rolls back
the run and candidates (5 graph tests). Inline content analysis checks source
availability through Content's public reader before creating its AI run (2
tool tests; the graph/tool set passed 7 tests, 20 assertions).
The agent's analysis-candidate listing now reads Content-owned candidate data
through `CandidateAnalysisStoreInterface` and checks content existence through
the public source reader. The AI-owned run query keeps the latest-ID selection;
analysis and agent-tool suites pass (45 tests, 145 assertions).
The unused `Lesson::user()` ORM relation is removed; lesson matching reads its
owner ID directly (14 lesson tests, 42 assertions).
Unused User relations on `ContentExamAttempt` and `GrammarExamAttempt` are
removed; readiness, grammar confidence and catalog tests pass (27 tests,
98 assertions).
Five unused User relations in Learning flow, point, metric and self-check
models are removed (15 focused tests, 47 assertions). AI's lexeme enrichment
job now passes an identifier into its AI service (17 focused tests, 66
assertions); remaining enrichment model imports need a separate slice.
`LearningFlowResolver` reads learner settings through User's public
`LearningFlowLearnerReaderInterface` (5 tests, 19 assertions).
The Learning Flow controller now reads and writes preferences through User's
public preference store (same 5 flow tests). The unused cross-module
`ContentLexeme::embeddings()` relation is removed (20 lexeme tests,
47 assertions). Analysis candidate listing is also tested with two runs to
verify selection of the latest run.
`LearningProgress` no longer has an unused User relation (3 focused tests,
11 assertions). `LearningRetry` retains the consumed ContentLexeme relation
but drops unused User, Content and SRS review relations (11 focused tests,
29 assertions).
`SrsCard` no longer exposes unused User and Content relations; SRS API and
student review-tool flows pass (21 tests, 52 assertions).
AI lexeme enrichment now reads prompt context and applies selected
associations through Content's `LexemeEnrichmentCatalogInterface`; AI keeps
proposal generation and job orchestration (27 focused tests, 102 assertions).
Content's lexeme controller calls AI through a public enrichment capability
(3 tests, 10 assertions). Grammar confidence calculation now takes a user ID
and preserves pre-exam behavior (9 tests, 23 assertions). Learning removed
unused relations from ExerciseAttempt, UserGrammarRule and
UserLexemeProgress while retaining their active links (14 tests,
54 assertions).
Content's exam controllers generate grammar warmups and readiness cards via
AI's public `ContentExamGenerationCapability` using IDs (19 tests,
53 assertions). Canonical lexeme creation queues AI enrichment through the
separate `LexemeEnrichmentDispatcher` contract (16 tests, 33 assertions).
The My Word and Learned Lexeme resources delegate translation, examples,
associations and contexts to Content's presentation reader (15 API tests,
61 assertions). Its current interface accepts an already loaded Lexeme as an
object; the list services must move to scalar/DTO results to remove that
remaining model leak, even though the import checker no longer flags it.
Self-check content lookup now uses Content's repository (10 tests,
28 assertions), but that repository still returns a Content ORM model and is
not a finished module boundary. Sentence practice authorization is performed
through Content's public view policy contract, retaining the 404 response for
inaccessible content (12 tests, 32 assertions); its AI service still has
direct Content model imports for later migration.
Learning flow metrics now record scalar user, profile and occurrence IDs rather
than accepting User and ContentLexeme models; self-check, exercise, flow and SRS
tests pass (27 tests, 86 assertions).
SRS's fallback lookup for a reviewed card's ContentLexeme now calls Content's
public reference reader instead of using a fully qualified private model
name hidden from the import checker (14 SRS/queue tests, 50 assertions).
Canonical lexeme embeddings no longer expose a cross-module Lexeme relation;
matching obtains language-filtered lexeme IDs from Content's public candidate
store (22 matching and recommendation tests, 57 assertions). This materializes
the ID list in memory, so large dictionaries should eventually use a bounded
or denormalized language filter.
The learned-lexeme list now accepts a user ID (8 API tests, 34 assertions).
Lexeme confidence reads and writes take scalar occurrence/user IDs rather
than ContentLexeme and User models (15 self-check/exercise tests,
45 assertions).
Progress statistics now receive a Learning-owned `StatsLearner` value with
user ID, timezone and daily goal instead of a User model (5 API/weak-spot
tests, 46 assertions).
The My Words list service now takes a user ID, preserving status and content
filtering (4 API tests, 20 assertions).
Adaptive activity selection now receives the lexeme's level as a scalar and
no longer takes ContentLexeme or User models (15 flow/self-check tests,
47 assertions).
Learning-start/stop events are now public Content contracts. The start event
carries `content_id` so SRS can create its card without querying a private
Content model; start/stop/bulk and review flows pass (24 tests,
78 assertions). The event catalog reflects the current producer and payload.
AI content analysis now obtains CEFR and part-of-speech options through
Content's public metadata value rather than importing Content/Lexeme models
for constants (36 analysis tests, 117 assertions).
The grammar body structure prompt is a public Content value shared by the
grammar editor and AI content analysis (40 focused tests, 128 assertions).
Excluded grammar-rule titles for AI analysis now come from Content's public
`GrammarRuleTitleReaderInterface` (36 analysis tests, 117 assertions).
AI content analysis consumes Content tokenization through a public tokenizer
interface (38 tokenizer/analysis tests, 119 assertions).
AI analysis, candidate matching and auto-apply now read Content source text,
language and level through a public immutable snapshot rather than traversing
`AiAnalysisRun::content` (55 focused tests, 169 assertions). The obsolete
cross-module ORM relation was subsequently removed; candidate application and
admin action coverage passes (40 tests, 124 assertions).
Content now compares completed analysis runs through the public
`AiAnalysisRunStatus` contract rather than importing the private AI model;
coverage and catalog tests pass (21 tests, 81 assertions).
Grammar progress operations now pass scalar user IDs through the public
contract rather than importing the User model in Content (15 catalog and
progress tests). Content catalog presentation receives the immutable
`ContentLearnerContext` value instead of a User ORM model; catalog, lexeme and
recommendation coverage passes (41 tests, 123 assertions). Content's three
staff relations resolve the configured Laravel auth model, preserving
Filament author/transcript-accepter behavior without a private module import
(12 tests, 30 assertions). Grammar progress persistence is now owned by
Learning behind `GrammarProgressStoreInterface`; API, pre-exam and lesson
matching coverage passes (23 tests, 74 assertions).
Learning progress recommendations resolve titles through Content's public
`ContentTitleReaderInterface`; the unused cross-module ORM relation was
removed (3 stats tests, 38 assertions; combined regression: 35 tests,
153 assertions).
Learning retry scheduling now accepts scalar learner and occurrence IDs;
its consumed cross-module ORM relation and direct Content/User model imports
were removed (15 self-check/exercise tests, 45 assertions).
Grammar progress pagination is enriched by the Content-owned application
service after Learning returns progress rows, so `UserGrammarRule` no longer
exposes a Content ORM relation (15 API tests, 45 assertions).
Point awards now receive learner/occurrence IDs and content metadata as
scalars, and flow resolution supports a user-ID entrypoint. This removes the
remaining Content/User model imports from `PointsAwardService` (18 focused
tests, 56 assertions).
Review outcomes read occurrence/content metadata through Content's public
`SrsReviewReferenceReaderInterface` and resolve the learning flow by user ID;
`ReviewOutcomeHandler` no longer imports Content or User models (15 focused
tests, 50 assertions).
Recommendations receive an AI-owned `RecommendationLearner` value and obtain
overdue content IDs through SRS's public schedule reader, removing direct User
and SRS model imports (15 recommendation/API tests, 37 assertions).
Learned-lexeme filtering and presentation now run behind Content's public
`LearnedLexemeCatalogInterface`; Learning keeps only progress rows and the
`UserLexemeProgress` model no longer exposes Content ORM relations (18 API
tests, 66 assertions).
Training queues now combine SRS card snapshots with Content-owned lexeme
presentations through public contracts. `TrainingSessionService` receives
only scalar learner data and no longer imports Content, SRS or User models
(5 API tests, 24 assertions).
`ExerciseAttempt` resolves its identity relation through Laravel's configured
auth provider instead of importing the private User model (5 API tests,
17 assertions).
Exercise attempt creation and processing now exchange an immutable Content
context and delegate review scheduling to an SRS-owned command. Learning no
longer imports Content or SRS domain models for this flow (5 API tests,
17 assertions).
Self-check reads presentation data through Content's public catalog, receives
learner preferences through User's public reader and delegates card updates
to the SRS review scheduler. Its controller and application service now pass
scalar identities across module boundaries (15 focused tests, 46 assertions).
My Words delegates catalog pagination to Content's public read port while
Learning retains the endpoint orchestration (4 API tests, 20 assertions).
Recommendation candidate queries are owned by Content and exposed through a
public catalog port (15 recommendation/API tests, 37 assertions).
Sentence practice receives scalar catalog snapshots from Content, learner
signals from Learning and review state behind the Content port (12 focused
tests, 32 assertions). Shared AI capabilities now live under `App\\Contracts\\Ai`;
consumer-owned Learning/SRS ports break all remaining module cycles.

## Requirement evidence map

| Spec | Implementation evidence | Verification |
| --- | --- | --- |
| `ai-execution` | typed capability DTOs, provider retry policy, `AiErrorMessage::safe()` | AI provider, job, controller and admin focused suites |
| `business-events` | outbox tables, dispatcher, atomic consumer deduplication | outbox, process-content and migration checks |
| `frontend-domains` | domain API folders, shared UI barrel, `useDraftState` and `UiDialog` | boundary checker, typecheck, build, Playwright smoke |
| `naming-conventions` | PHP/TS naming checker and documented exceptions | naming self-test |
| module ownership | ownership map and modular ADR | PHP boundary checker (remaining legacy violations listed above) |
