# Task Breakdown: AI Candidate Extraction and Review Workspace

## Epic Summary

Build the staging layer, extraction, matching, review UI, and conversational refinement described in `docs/product/epic/ai-candidate-extraction-review-epic.md`. This covers Stage 3 and Stage 4 from `docs/product/admin-pipeline-plan.md`, plus chat-based candidate refinement.

## Recommended Implementation Order

1. Candidate/staging schema. **Done.**
2. `AiJsonClient` contract and provider wiring. **Done.**
3. Extraction service (words/phrases + translation + grammar). **Done.**
4. Filament read-only candidate review tables. **Done.**
5. Embedding-based matching. **Done.**
6. Approve/reject/edit/bulk actions. **Done.**
7. Apply-to-canonical action. **Done.**
8. Scoped AI chat refinement. Not started.
9. Tests. Covered incrementally for Tasks 1-7 as they landed; still needed for 8.
10. Documentation. Not started (this file/epic doc are the plan, not the post-implementation doc pass).

Tasks 1-4 (the first delivery slice) are implemented on `main`:

- Migrations: `ai_analysis_runs`, `content_lexeme_candidates`, `content_grammar_candidates`, `canonical_lexeme_embeddings`, `grammar_rule_embeddings`.
- Models: `AiAnalysisRun`, `ContentLexemeCandidate`, `ContentGrammarCandidate`, `CanonicalLexemeEmbedding`, `GrammarRuleEmbedding`, plus `Content::analysisRuns()/latestAnalysisRun()/lexemeCandidates()/grammarCandidates()`.
- `AiJsonClient` contract, implemented in both `OpenAiClient` and `OllamaClient`, bound via the same `config('ai.provider')` switch as `AiClientInterface` (`AiServiceProvider::resolveConfiguredClient()`). Also fixed the pre-existing `EmbeddingsClientInterface` binding, which pretended to switch on provider but always returned `OpenAiEmbeddingsClient` — the dead branch was removed and the comment corrected.
- `AiContentAnalysisService` + `RunAiContentAnalysisJob`, config `ai.analysis.translation_language` (default `ru`) and `ai.analysis.max_transcript_chars` (default 8000, logs a warning on truncation rather than dropping silently).
- Filament: `AnalyzeWithAiAction` on `ViewContent`, latest-run status on `ContentInfolist`, read-only `LexemeCandidatesRelationManager` / `GrammarCandidatesRelationManager` on `ContentResource`.
- Tests: `tests/Feature/Ai/AiJsonClientTest.php`, `AiServiceProviderBindingTest.php`, `AiContentAnalysisServiceTest.php`, `RunAiContentAnalysisJobTest.php`, `tests/Feature/AdminAiCandidatesViewTest.php`.

Task 5 (embedding-based matching) is also implemented on `main`:

- `CandidateMatchingService` (`app/Modules/Ai/Application`): exact `normalized_lemma` match first (score `1.0`, no AI call), embedding cosine-similarity fallback otherwise. Below-threshold matches store `match_score` but leave `matched_lexeme_id`/`matched_grammar_rule_id` null.
- `canonical_lexeme_embeddings`/`grammar_rule_embeddings` backfill: `ComputeCanonicalLexemeEmbeddingsJob`/`Command` and `ComputeGrammarRuleEmbeddingsJob`/`Command` (`ai:embed-canonical-lexemes`, `ai:embed-grammar-rules`), mirroring the existing `ai:embed-lexemes` pattern exactly.
- `config('ai.analysis.match_threshold')` (`AI_ANALYSIS_MATCH_THRESHOLD`, default `0.85`).
- `RunAiContentAnalysisJob` calls `CandidateMatchingService::matchRun()` after a successful `analyze()`, wrapped in its own try/catch — a matching failure logs a warning and the run still completes, it does not flip to `failed`.
- Filament: both relation managers now show a matched-lexeme/matched-rule column and `match_score`.
- Tests: `tests/Feature/Ai/CandidateMatchingServiceTest.php`, `ComputeCanonicalLexemeEmbeddingsJobTest.php`, `ComputeGrammarRuleEmbeddingsJobTest.php`, plus two new cases in `RunAiContentAnalysisJobTest.php` covering the matching call and its failure isolation.
- Known gap carried forward to Task 7: lexemes/rules created via Apply won't have embeddings until someone runs the backfill commands — Apply should ideally auto-embed on creation.

Task 6 (approve/reject/edit/bulk actions) is also implemented on `main`:

- Both relation managers are no longer read-only. Row actions: `accept`, `reject` (visible only while `pending`/`edited`), and `ignoreMatch` (clears `matched_lexeme_id`/`matched_grammar_rule_id`, sets status `edited` — the "create new anyway" override for an unwanted auto-match, visible only when a match exists).
- Inline-editable columns: `translation`/`type` on lexeme candidates (`TextInputColumn`/`SelectColumn`), `title`/`summary` on grammar candidates (`TextInputColumn`, `title` has a `required` rule since the DB column is `NOT NULL`). Any edit sets status to `edited` unconditionally, per spec, regardless of prior status.
- Bulk actions (checkbox-selection scoped, so inherently safe against other runs): `acceptSelected`, `rejectSelected` (`Filament\Actions\BulkAction`).
- Header action `acceptAboveThreshold`: prompts for a confidence threshold and accepts all `pending` candidates **scoped to `Content::latestAnalysisRun` only**, not the full (multi-run) relationship — deliberate, so accepting a new run's candidates can never silently sweep up stale `pending` candidates left over from an earlier run on the same content. Covered by a dedicated test with two runs.
- Tests: `tests/Feature/AdminAiCandidatesActionsTest.php` (accept/reject/ignoreMatch/inline-edit/bulk/threshold-scoped-to-latest-run, for both lexeme and grammar candidates).

Task 7 (Apply-to-canonical) is also implemented on `main`:

- `AiCandidateApplyService::apply(AiAnalysisRun $run)`: promotes `accepted` candidates only; flips each to `applied` as it's processed, so a re-run naturally skips already-applied rows instead of duplicating (idempotency via status, not a separate "already applied" check).
- Lexeme candidates: if `matched_lexeme_id` is set, links directly via `ContentLexemeLink` (no new `ContentLexeme` occurrence) — deliberately bypasses `ContentLexeme`'s own auto-sync hook (`CanonicalLexemeSyncService`), which only does exact-string matching and would otherwise miss the Task 5 embedding match and create a duplicate canonical lexeme. If unmatched, creates a normal `ContentLexeme` occurrence and lets the existing auto-sync hook create/reuse the canonical `Lexeme` as it already does for regular tokenized words.
- Grammar candidates: if `matched_grammar_rule_id` is set, links directly via `ContentRuleLink`. If unmatched, creates a new `GrammarRule` through the existing `GrammarCatalogServiceInterface::createRule()` (reuses its slug generation) with `status = draft`. AI-proposed grammar has no topic of its own, so new rules land in a shared `AI Suggested` topic (`ai-suggested-{language}`, found-or-created once per language) until an admin reassigns a real topic — a deliberate scope decision to avoid extending the Task 3 AI schema with topic classification just for this.
- Filament: `ApplyAiCandidatesAction` on `ViewContent`, visible only when the latest run has at least one `accepted` candidate; shows counts applied or a clear "nothing to apply" notice.
- Tests: `tests/Feature/Ai/AiCandidateApplyServiceTest.php` (new-lexeme, matched-lexeme-no-duplicate, new-grammar-rule-with-shared-topic, topic-reused-not-duplicated-across-runs, matched-rule-no-duplicate, pending/rejected-ignored, re-apply-idempotent), `tests/Feature/AdminApplyAiCandidatesActionTest.php` (end-to-end via the Filament action, and visibility when nothing is accepted).
- Known gap carried forward: newly created canonical lexemes/rules still have no embeddings until the Task 5 backfill commands are run again — future work, not blocking.

**Correction after initial Task 7 landed:** the first version of `applyLexemeCandidate()`/`applyGrammarCandidate()` only wrote `text`/`type` (or a bare link) into canonical tables — `translation` and `example` from the candidate were silently dropped, never reaching anywhere durable. This defeated the original motivation for the whole epic (showing a translation next to a word). Fixed by:

- Migration `2026_07_22_130000_add_content_id_to_examples_tables.php`: added nullable `content_id` (FK to `contents`, `nullOnDelete`, indexed) to both `lexeme_examples` and `grammar_rule_examples`. Null means a globally curated example (admin-authored via the existing `GrammarCatalogService::updateLexeme()`/`updateRule()` forms, which do a destructive full-replace `sync` on their own `examples` array — untouched by this change since it's a separate array key); a set value means the example was sourced from that specific content.
- `AiCandidateApplyService` now attaches a `LexemeExample`/`GrammarRuleExample` row (with `content_id = run->content_id`) whenever a candidate carries a `translation` and/or `example`, for both the "new canonical entity" and "linked to an existing match" cases — translation was previously only a risk for new entities, but was silently dropped for matched ones too. `is_primary` is set for the first example a lexeme/rule gets, regardless of whether that's a global or content-scoped one; no per-content "primary" concept was introduced since no consuming UI reads this yet.
- Also cast `is_primary` to `boolean` on both `LexemeExample`/`GrammarRuleExample` models — it was returning as an integer from the DB, caught by a new test assertion, unrelated pre-existing gap.
- New tests: translation+example attached for a new lexeme, translation attached for a *matched* lexeme (this case had zero coverage before), no example row created when a candidate has neither field, example attached for a new grammar rule, and a global (no `content_id`) example coexisting with a content-scoped one from Apply.

Full suite green (217 passed) except the same two pre-existing unrelated failures (`ContentSubmissionFlowTest`, `GlobalLearnedIntegrationTest`), confirmed failing on `main` before any of this AI-candidates work, re-verified via `git stash` each time.

Not yet built: the chat refinement layer (Task 8) — the only remaining task in this epic.

Not yet built: the Apply-to-canonical step (Task 7) and the chat refinement layer (Task 8).

## Tasks

### 1. Add Candidate/Staging Schema

**Purpose:** Give AI output a place to live that is separate from canonical `lexemes`/`grammar_rules`, so review and refinement never risk corrupting published data.

**Scope:**

- Migration for `ai_analysis_runs` (`content_id` FK `cascadeOnDelete`, `status`, `model`, `provider`, `started_at` nullable, `completed_at` nullable, `failure_reason` nullable, timestamps; index `(content_id, status)`).
- Migration for `content_lexeme_candidates` (`ai_analysis_run_id` FK `cascadeOnDelete` — full name, not `run_id`, so Laravel's default `constrained()` table-name guess resolves correctly — `text`, `normalized_text` indexed, `type` string(16), `translation` nullable, `example` nullable, `confidence` `decimal(4,3)` nullable, `matched_lexeme_id` nullable FK to `lexemes` **`nullOnDelete`**, `match_score` `decimal(4,3)` nullable, `status` default `pending`, timestamps; index `(ai_analysis_run_id, status)`).
- Migration for `content_grammar_candidates` (`ai_analysis_run_id` FK `cascadeOnDelete`, `title`, `summary` nullable, `example` nullable, `confidence` `decimal(4,3)` nullable, `matched_grammar_rule_id` nullable FK to `grammar_rules` **`nullOnDelete`**, `match_score` `decimal(4,3)` nullable, `status` default `pending`, timestamps; index `(ai_analysis_run_id, status)`).
- Migration for `canonical_lexeme_embeddings` (`lexeme_id` FK `cascadeOnDelete`, `embedding` json, `model_version` string(64), unique `(lexeme_id, model_version)`). **Do not** reuse the existing `lexeme_embeddings` table — it is keyed to `content_lexeme_id` (a content occurrence, populated only on demand by `ComputeEmbeddingsJob`), not to canonical `lexeme_id`, and cannot serve candidate-vs-canonical matching without an indirect, unreliable join through `content_lexeme_links`.
- Migration for `grammar_rule_embeddings` (`grammar_rule_id` FK `cascadeOnDelete`, `embedding` json, `model_version` string(64), unique per model_version) — mirrors existing `lexeme_embeddings`' JSON-storage convention (comment in that migration already flags pgvector as a possible later addition).
- Eloquent models: `AiAnalysisRun`, `ContentLexemeCandidate`, `ContentGrammarCandidate`, `CanonicalLexemeEmbedding`, `GrammarRuleEmbedding`, with relationships to `Content`, `Lexeme`, `GrammarRule`.

**Dependencies:** None (foundation task).

**Type:** Backend / database.

**Acceptance criteria:**

- Migrations run cleanly on top of existing schema.
- Candidate rows can be created, queried by run, and updated independently of canonical tables.
- Deleting a run does not cascade-delete canonical data (only candidates).
- Deleting a `Lexeme`/`GrammarRule` that a candidate happens to be matched against nulls the candidate's `matched_*_id` rather than deleting the candidate (verifies `nullOnDelete` is actually wired, not just documented).

### 2. Add `AiJsonClient` Contract And Provider Wiring

**Purpose:** Give the AI module a structured-response contract, since extraction needs a list of objects, not free text.

**Scope:**

- Define `AiJsonClient` interface (e.g. `completeJson(string $system, string $user, array $schema): array`) in `src/app/Modules/Ai/Contracts`.
- Implement in **both** `OpenAiClient` and `OllamaClient` (`src/app/Modules/Ai/Infrastructure`) — mirror how `AiClientInterface::complete()` is already implemented in both, so the provider switch stays real instead of OpenAI-only.
- Bind in `AiServiceProvider` via the same `config('ai.provider')` switch already used for `AiClientInterface` (`src/app/Modules/Ai/AiServiceProvider.php:19-34`) — do not introduce a second, separate provider-selection mechanism.
- Add a stub implementation for tests, matching the existing `StubChatAiService` pattern.
- Do **not** copy the `EmbeddingsClientInterface` binding's mistake (`AiServiceProvider.php:38-54`), where both branches of the provider switch construct the same OpenAI class — that switch is currently dead code. If touching that binding while in this file, fixing it is a natural side-fix but is not required for this task's acceptance criteria.

**Dependencies:** None.

**Type:** Backend / AI module.

**Acceptance criteria:**

- `AiJsonClient::completeJson()` returns a decoded array for valid provider JSON output, for both the OpenAI and Ollama implementations.
- Malformed provider output raises a typed exception, not a silent empty array.
- Stub implementation is usable in tests without network calls.
- A test asserts that changing `config('ai.provider')` changes which concrete class is resolved for `AiJsonClient`, same as the existing coverage (if any) for `AiClientInterface`.

### 3. Add Extraction Service

**Purpose:** Turn a content's transcript into candidate rows: words/phrases with translation, and grammar constructions.

**Scope:**

- New `AiContentAnalysisService` (or similar) in `src/app/Modules/Ai/Application` or `Content/Application`.
- Single combined prompt/schema requesting: list of lexeme units (text, type, translation, example) and list of grammar constructions (title, summary, example) from a transcript excerpt.
- Writes results as `content_lexeme_candidates`/`content_grammar_candidates` rows tied to a new `ai_analysis_runs` row.
- Handles transcript length limits (chunk or truncate; do not silently drop content without marking the run).
- Job wrapper (e.g. `RunAiContentAnalysisJob`) so extraction runs off the request cycle.

**Dependencies:** Tasks 1, 2.

**Type:** Backend / AI module.

**Acceptance criteria:**

- Running analysis on a content item with a transcript produces candidate rows of both kinds.
- Run status transitions `running` → `completed`/`failed` correctly.
- Failure (AI error, invalid JSON) sets `failure_reason` and does not leave partial/inconsistent candidate rows.
- Re-running analysis on the same content starts a new run rather than mutating a previous one.

### 4. Add Filament Read-Only Candidate Review Tables

**Purpose:** Make AI output visible to admins in the structured format requested — this is the first slice that delivers real value on its own.

**Scope:**

- Add "Analyze with AI" action on `ContentResource` (list and/or detail), following the `LexemesRelationManager::suggestCefrLevel` pattern for AI-triggered Filament actions.
- Add a content detail section/page showing the latest run's status.
- Add two relation-manager-style tables: lexeme candidates and grammar candidates, columns as specified in the epic (text/type/translation/match/confidence/status for lexemes; title/example/match/status for grammar).
- Read-only at this stage — no edit/approve yet.

**Dependencies:** Tasks 1, 3.

**Type:** Admin UI / Filament.

**Acceptance criteria:**

- Admin can trigger analysis from content detail and see run status update.
- Admin can see all candidates for the latest run in two clearly separated tables.
- Tables are usable with realistic volumes (pagination, no N+1 queries).

### 5. Add Embedding-Based Matching

**Purpose:** Avoid proposing duplicate lexemes/grammar rules that already exist in the canonical catalog.

**Scope:**

- New `CandidateMatchingService` using existing `EmbeddingsClientInterface`.
- For each lexeme candidate: exact `normalized_lemma` check first (fast path), then embedding similarity against `lexeme_embeddings`.
- For each grammar candidate: embedding similarity against `grammar_rule_embeddings` (populate this table for existing rules as part of this task — backfill command).
- Write `matched_lexeme_id`/`matched_grammar_rule_id` + `match_score` on the candidate row.
- Configurable similarity threshold (config value, not hardcoded).

**Dependencies:** Tasks 1, 3.

**Type:** Backend / AI module.

**Acceptance criteria:**

- Candidates matching an existing canonical entry above threshold show the match and score in the review table.
- Candidates with no match above threshold are clearly marked "new" but remain editable to link manually.
- Backfill command populates `grammar_rule_embeddings` for existing `grammar_rules` without needing a new content run.

### 6. Add Approve/Reject/Edit/Bulk Actions

**Purpose:** Let admin correct AI output and decide what should count toward the canonical catalog.

**Scope:**

- Inline-editable columns (translation, type/title, summary) on both candidate tables.
- Row actions: accept, reject, "use matched entry instead", "create new anyway".
- Bulk actions: accept all above a confidence threshold, reject all below a threshold.
- Status column reflects `pending`/`accepted`/`rejected`/`edited`.

**Dependencies:** Task 4, 5.

**Type:** Admin UI / Filament.

**Acceptance criteria:**

- Editing a field persists immediately and marks status `edited`.
- Bulk accept/reject works across a filtered/paginated set without accidentally including other runs' candidates.
- Rejected candidates never appear in the Apply step.

### 7. Add Apply-To-Canonical Action

**Purpose:** Promote reviewed candidates into the real catalog through existing, trusted services.

**Scope:**

- "Apply approved candidates" action on the run/content detail.
- For lexeme candidates: create `content_lexemes` + route through existing `CanonicalLexemeSyncService` (or matched lexeme directly if `matched_lexeme_id` set and accepted as "use existing").
- For grammar candidates: create/update via `GrammarCatalogServiceInterface`, then `content_rule_links`.
- Idempotency: candidates already applied are marked (e.g. `status = applied`) and skipped on re-apply.

**Dependencies:** Tasks 1, 6.

**Type:** Backend / Admin UI.

**Acceptance criteria:**

- Applying accepted candidates creates expected canonical rows and links.
- Re-running Apply on the same run does not create duplicates.
- Applying with zero accepted candidates is a no-op with clear feedback, not an error.

### 8. Add Scoped AI Chat Refinement

**Purpose:** Let admin ask for changes in natural language instead of only clicking through forms, without giving the model unscoped access to app data.

**Scope:**

- Reuse `AiConversation` storage, scoped to `ai_analysis_run_id`.
- Define a fixed tool set: edit candidate, merge candidates, split candidate, regenerate candidate (single item, with extra instruction), explain match, delete candidate.
- New service (e.g. `AiAnalysisChatService`) that builds context from the run's current candidates, sends tool definitions to the AI provider, and executes only the returned tool calls against `content_lexeme_candidates`/`content_grammar_candidates`.
- Chat UI panel on the same content detail page as the candidate tables; changes reflect immediately in those tables.
- Hard boundary: tool execution code must reject any target ID that does not belong to the current run.

**Dependencies:** Tasks 1, 2, 4, 6.

**Type:** Backend / AI module / Admin UI.

**Acceptance criteria:**

- Admin can type a request (e.g. "merge these two phrasal verbs") and see the candidate tables update accordingly.
- Model cannot mutate candidates from a different run or any canonical table — covered by a test that attempts exactly this.
- Chat history is visible per run for later reference.
- If the AI provider is unavailable, the chat panel degrades gracefully (existing `AiConfig::isEnabled()` pattern) and manual review actions still work.

### 9. Add Tests

**Purpose:** Protect the staging/apply boundary and the chat tool scope, since those are the parts most likely to silently corrupt canonical data if broken.

**Scope:**

- Unit tests for extraction JSON parsing (valid, malformed, partial).
- Unit tests for matching thresholds.
- Feature tests for Filament review actions (accept/reject/edit/bulk).
- Feature tests for Apply idempotency.
- Feature tests for chat tool scope (cross-run access attempt must fail).
- Permission tests (`manage-content` gate) on all new actions/routes.

**Dependencies:** Tasks 1-8.

**Type:** Testing.

**Acceptance criteria:**

- Full suite passes.
- Cross-run and cross-permission attempts are explicitly covered and fail as expected.

### 10. Update Documentation

**Purpose:** Keep `admin-pipeline-plan.md` and related docs aligned with what actually got built.

**Scope:**

- Update `docs/product/admin-pipeline-plan.md` Stage 3/4 sections if final field/table names differ from the suggestion.
- Add a short admin-facing note on how to run analysis, review, and apply.
- Note the chat tool boundary explicitly for future contributors extending the tool set.

**Dependencies:** Tasks 1-9.

**Type:** Docs.

**Acceptance criteria:**

- Docs match implementation.
- Stage 5 (publish gate) assumptions in `admin-pipeline-plan.md` are still valid after this epic.

## Sequential vs Parallel Work

### Sequential

- Task 1 blocks everything.
- Task 2 blocks Task 3 and Task 8.
- Task 3 blocks Task 4.
- Task 6 depends on Task 4 and 5 both being usable.
- Task 7 depends on Task 6.
- Task 8 should come after Task 6 is stable — refining candidates by chat is only worth building once manual review works.
- Task 9 is best finalized once behavior stabilizes, though tests for Tasks 1-3 can be written alongside them.

### Parallelizable

- Task 5 (matching) can be built in parallel with Task 4 (read-only tables) once Task 3 exists — they touch different code paths.
- Task 10 can be drafted early and finalized after Task 9.

## Risks And Notes

- Combining word/phrase/grammar extraction into one AI call keeps the first slice simple, but may need splitting into separate calls later if quality suffers on longer transcripts — the epic already scopes multi-agent splitting out for now.
- Embedding-based matching quality depends on embedding model consistency; changing `model_version` invalidates prior comparisons, same caveat as the existing `lexeme_embeddings` table already carries.
- The chat tool layer is the highest-risk piece from a safety standpoint (model-initiated writes) — enforce the run-scope check in application code, not just in the prompt.
- Apply must stay the only path from candidates to canonical tables; do not let any admin or chat action write to `lexemes`/`grammar_rules` directly.

## First Delivery Slice

Tasks 1-4: schema, `AiJsonClient`, extraction service, read-only Filament tables. This alone lets admin see AI-proposed words/phrases with translation and grammar matches in a structured view — the core of the original ask — without yet risking canonical data.

Second slice: Tasks 5-7 (matching, review actions, apply). Third slice: Task 8 (chat refinement), since it is the most novel and highest-risk part and is only useful once there is real candidate data to refine.

## Post-Task-7 Enhancements (Implemented)

Landed after real usage of Tasks 1-7 surfaced gaps — same files as Tasks 3/6/7, not a new numbered task:

- **Word-level CEFR + frequency**: `content_lexeme_candidates.level`/`frequency` — level is AI-tagged (validated against `Content::CEFR_LEVELS`), frequency is a deterministic word-boundary occurrence count over the transcript (`AiContentAnalysisService::countOccurrences()`, not an AI guess). On Apply: `frequency` populates the long-dead `content_lexemes.frequency` column; `level` backfills `Lexeme.level` only if it was null (never overwrites curated data).
- **Example translation**: `content_lexeme_candidates`/`content_grammar_candidates.example_translation`, persisted into `LexemeExample.translation`/`GrammarRuleExample.translation` — the columns' original intent, which `AiCandidateApplyService` had been mislabeling since Task 7 by writing the *word's* translation there instead.
- **Word gloss got its own home**: new `lexeme_translations` table (mirrors `lexeme_examples`' shape: nullable `content_id`, `is_primary`, `sort_order`), keyed by target `language` so a lexeme can have glosses in more than one target language. Fixes the same "dropped for matched candidates" bug already fixed once for examples in Task 7 — now applies here too.
- **`translation_language` is no longer hardcoded**: `AiAnalysisRunConfig` gained a `translationLanguage` field (per-run override, same pattern as `target_level`); `users.translation_language` is the admin's profile default (distinct from the pre-existing `ui_language`, which means "language of content this user studies," not "language to translate into" — confirmed via `RecommendationService`'s usage before reusing it). `lexeme_examples`/`grammar_rule_examples` gained `translation_language` so multiple target-language translations of the same example won't collide.
- Filament: `AnalyzeWithAiAction` gained a "Translate into" field (defaults from profile → global config); `exclude_words` gained an "Exclude words already in the catalog" checkbox that queries `Lexeme` for the content's language instead of requiring manual typing; both relation managers show the new columns.
- Tests extended across `AiAnalysisRunConfigTest`, `AiContentAnalysisServiceTest`, `AiCandidateApplyServiceTest`, `AdminAnalyzeWithAiActionTest`. Full suite: 259 passed, same two pre-existing unrelated failures. Verified end-to-end against the real OpenAI API (level/frequency/example translation on candidates, then Accept+Apply confirming gloss vs. example-translation land in the right, separate places).

**Learner-facing native language + language-aware translation display** (landed alongside the parallel FE work from `docs/product/fe-translation-display-task.md`, which had wired the SPA's translation display to `LexemeExample.translation` before the gloss/example-translation split above existed — corrected here):

- `Lexeme::pickPrimaryTranslation(translations, contentId, language)` — same content-scoped-wins-over-global shape as `pickPrimaryExample()`, hard-filtered by target language first; returns null (not a wrong-language fallback) when the viewer's language isn't among the stored ones — explicitly confirmed behavior, not a gap.
- `ContentService::getLexemesWithLearnedFlags()` and `LearnedLexemeResource` now resolve `$user?->translation_language ?? config('ai.analysis.translation_language')` per request and use `pickPrimaryTranslation()` for the `translation` field (was incorrectly reading `LexemeExample.translation` — the example's translation, not the word's).
- `users.translation_language` (added earlier for the admin's `AnalyzeWithAiAction` default) is now also exposed on the learner-facing profile (`ApiProfileUpdateRequest`, `UserResource`, `SettingsPage.vue` "Native language" field) — one column serves both purposes, no new field needed.
- Fixed two tests in `LexemeProgressTest.php` (from the parallel FE work) that asserted the old, incorrect behavior — same class of correction as the `AiCandidateApplyServiceTest.php` fix earlier in this epic.
- New tests: `LexemePickPrimaryTranslationTest.php`, `ContentLexemesTranslationTest.php`, `ApiProfileTranslationLanguageTest.php`, plus additions to `LearnedLexemesApiTest.php`. Full suite: 274 passed, same two pre-existing unrelated failures. Verified via tinker that two users with different `translation_language` values see different, correct translations for the same word.
- Added proper `Select` (short curated language list, `AiAnalysisRunConfig::TRANSLATION_LANGUAGES`) in place of free-text ISO-code inputs on `SettingsPage.vue`, `AnalyzeWithAiAction`, and `UserForm.php` — one shared list, no drift between the three.

**Grammar admin editor: structure, reusable AI-assist, revision history** (first admin UI for grammar at all — none existed before this, Filament had no `GrammarRule`/`GrammarTopic` resource and the `/admin/grammar/*` REST API had zero consumers):

- New Filament resources `GrammarTopicResource` / `GrammarRuleResource` (direct-model, same shape as `ContentResource` — the pre-existing `GrammarCatalogService` REST layer is untouched, still orphaned, not addressed here). `body` uses `MarkdownEditor`; `ExamplesRelationManager` is a thin CRUD over `grammar_rule_examples`.
- **Reusable AI-assist contract**, not grammar-specific: `App\Modules\Ai\Contracts\AiEditablePrompt` + `App\Modules\Ai\Application\AiFieldEditService::propose()` — calls `AiJsonClient`, returns a proposal, **saves nothing**. `GrammarRuleAiContentBuilder` is the first (only) implementation. The "AI: Draft/Improve" Filament action fills the *live edit form* with the proposal (`$livewire->form->fill([...$livewire->form->getState(), ...$updates])`) rather than the database — the admin's own Save button is the only write path, which is also what triggers the revision entry below. No new staging tables needed for this, unlike the batch-candidate epic — a single edit form already *is* the review step.
- Batch pipeline also generates `body` now: `content_grammar_candidates.body`, `AiContentAnalysisService`'s grammar prompt and `GrammarRuleAiContentBuilder::structureInstructions()` share one description of "what a good grammar body looks like" (headings: Rule / Formation / Usage / Common mistakes) so the two AI touchpoints don't drift apart. `AiCandidateApplyService::applyGrammarCandidate()` persists `body` on the create-new path only — matched-existing path never overwrites a curated body (same principle already used for `level`).
- **Revision history**: `composer require spatie/laravel-activitylog` failed — no network access to GitHub/Packagist from the app container in this environment (confirmed via error output), and that package version requires `php: ^8.4` anyway (project is `^8.2`). Built the fallback already scoped as a contingency in the plan: generic polymorphic `entity_revisions` table + `App\Support\HasRevisions` trait (model `created`/`updated` hooks, diffs an explicit `$revisionable` field allowlist, records `causer`). `GrammarRule` is the only model using it so far; the trait is a one-line addition for any future model. `RevisionsRelationManager` (read-only) shows the history on the Edit page.
- Tests: `AiFieldEditServiceTest`, `GrammarRuleAiContentBuilderTest`, `HasRevisionsTest`, `AdminGrammarRuleResourceTest` (includes a test proving the AI action does *not* persist until Save), plus extensions to `AiContentAnalysisServiceTest`/`AiCandidateApplyServiceTest` for `body`. Full suite: 296 passed, same two pre-existing unrelated failures. Verified end-to-end with a real OpenAI call: batch-generated structured body on a real content analysis, then a standalone "improve" call against the resulting saved rule confirmed the proposal came back without touching the DB.

**Learner-facing grammar display** (the admin work above had no learner-facing consumer at all until this — `GrammarPage.vue` was querying `Content` where `type='grammar'`, an unrelated concept that just shares a name; `grammar_rules` was never read by anything except Filament):

- New public API: `GET /grammar-rules` (catalog, filters: language/level/topic_id/q, always forces `status=published` server-side regardless of request), `GET /grammar-rules/{rule}` (404s if not published), `GET /content/{content}/grammar-rules` (rules linked via `content_rule_links`, published only, gated by the same `Gate::allows('view', $content)` as content itself). All reuse the existing `GrammarCatalogService::paginateRules()`/`getRule()` — no query logic duplicated. New `App\Http\Resources\GrammarRuleResource` is a distinct, learner-facing shape (no coverage_state/counts) from the admin-only `AdminGrammarRuleResource`.
- SPA: `GrammarPage.vue` rewritten to call the new `grammarApi` instead of the `Content`-based catalog; new `GrammarRuleDetailPage.vue` (`/grammar/:id`) renders `body`; `ContentDetailsPage.vue` gained a "Grammar" section pulling rules linked to that specific content.
- New shared `MarkdownContent.vue` renders `body`: `marked` (markdown → HTML) + `DOMPurify.sanitize()` before `v-html` — sanitizing is what actually makes this safe, not the choice of markdown alone (ties back to the earlier discussion on why markdown was chosen over raw HTML in the first place). New npm deps: `marked`, `dompurify` (npm registry was reachable, unlike GitHub/Packagist earlier).
- Tests: `GrammarRuleCatalogTest.php`, `ContentGrammarRulesTest.php` (published-only visibility, filters, 404 on non-published, empty-array-not-error for unlinked content). No Vue component test tooling exists in this repo (no vitest/*.spec.ts) — verified via `npm run build` plus a live end-to-end check (real HTTP requests against the dev server) confirming the previously-created "Past Simple Tense" rule (with real AI-generated markdown body) is returned by both new endpoints correctly. Full suite: 304 passed, same two pre-existing unrelated failures.
