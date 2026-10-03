# Epic: AI Candidate Extraction and Review Workspace

## Goal

Give admins a Filament workspace where AI analysis of a content item's transcript produces a structured, editable list of proposed learning units — words/phrases with translation, and grammar constructions matched against the existing catalog or proposed as new — reviewable and refinable (including through a scoped AI chat) before anything is written into the canonical `lexemes`/`grammar_rules` tables.

This is Stage 3 ("AI Candidate Extraction") and Stage 4 ("Admin Review") from `docs/product/admin-pipeline-plan.md`, made concrete, plus one addition beyond that plan: conversational refinement of candidates instead of only form-based edit/approve/reject.

## Why Now

`docs/product/admin-pipeline-stage-1-2-epic.md` explicitly scoped out AI lexeme/grammar extraction and candidate review tables, deferring them to a later epic. Pipeline visibility and the transcript workspace are the prerequisite; this epic is that next step. Without it:

- extracted lexemes are single words only (`ContentTokenizer` is regex-based, no phrases/idioms/grammar);
- there is no translation anywhere in the data model;
- the grammar catalog (`grammar_topics`, `grammar_rules`, ...) exists but nothing populates it from content — it is admin-authored only;
- admins have no way to see or correct AI output before it affects learners.

## Stakeholders

- Admin/editor: runs analysis, reviews and corrects candidates, approves what gets published.
- Learner: benefits from richer vocabulary (phrases, idioms) with translation, and from grammar linked to real content.
- AI pipeline: gains a defined contract for structured extraction instead of free-text-only calls.
- Developer: gets an auditable staging layer instead of AI writing directly into canonical tables.

## In Scope

- `AiJsonClient` contract for structured (JSON) AI responses, alongside the existing free-text `AiClientInterface`.
- Draft/staging schema for AI-proposed lexemes and grammar constructions, scoped to an analysis run.
- Extraction service that returns words/phrases (with type and translation) and grammar constructions from a content's transcript.
- Matching of candidates against existing canonical `lexemes` and `grammar_rules`, using embeddings where useful (`lexeme_embeddings` already exists and is unused).
- Filament review UI on content detail: candidate tables for lexemes and grammar, inline edit, approve/reject, bulk actions.
- "Apply" action that promotes approved candidates into canonical tables (`content_lexemes` → `lexemes`, `grammar_rules`, `content_rule_links`) via existing services (`CanonicalLexemeSyncService`, `GrammarCatalogServiceInterface`).
- Scoped AI chat tied to one analysis run, able to mutate pending candidates (edit, merge/split, regenerate, explain) through tool-calling, not free-form text.
- Tests for extraction parsing, matching, review actions, apply, and chat tool-calling.

## Out Of Scope

- Full multi-agent split into separate `TranscriptNormalizerAgent`/`LexemeExtractorAgent`/`PhraseExtractorAgent`/etc. processes — start with one combined extraction call; split later only if quality requires it.
- Publish gate enforcement (Stage 5 in `admin-pipeline-plan.md`) — only light integration (surface candidate counts), not blocking logic.
- User-facing translation display (separate, smaller piece — depends on this epic's `translation` field existing but is its own slice).
- Rewriting `ContentTokenizer`'s single-word regex path — it keeps running as-is; this epic adds phrase/grammar extraction on top, it does not replace word tokenization.
- Cost/budget dashboards beyond a minimal run counter.

## Existing Context

Relevant docs:

- `docs/product/admin-pipeline-plan.md` (Stage 3-5 definitions, suggested table names, agent roles)
- `docs/product/ai-chat-tutor-plan.md` (`AiJsonClient`, provider abstraction, chat context patterns)
- `docs/product/epic/global-lexeme-catalog-epic.md` (canonical lexeme model this epic writes into)
- `docs/product/epic/admin-pipeline-stage-1-2-epic.md` (prerequisite pipeline visibility work)

Relevant code:

- `src/app/Modules/Ai/*` — `AiClientInterface`, `OpenAiClient`, `OllamaClient`, `AiExplainLexemeService`, `AiConversationService`, `ChatContextAiService`.
- `src/app/Services/ContentTokenizer.php`, `src/app/Jobs/ProcessContentJob.php` — current word-only extraction.
- `src/app/Modules/Content/Application/CanonicalLexemeSyncService.php` — existing occurrence-to-canonical lexeme linking, reusable for candidate promotion.
- `src/app/Modules/Content/Application/GrammarCatalogServiceInterface.php` and `GrammarCatalogService.php` — existing grammar CRUD, reusable for candidate promotion.
- `src/database/migrations/2026_03_23_180000_create_grammar_catalog_tables.php` — canonical schema (`lexemes`, `grammar_topics`, `grammar_rules`, `lexeme_examples`, `lexeme_embeddings`, `content_rule_links`, `content_lexeme_links`).
- `src/app/Filament/Resources/Contents/*`, especially `RelationManagers/LexemesRelationManager.php` — existing pattern for an AI-triggered Filament action (`suggestCefrLevel`) to follow.

## Product Behavior

### Run AI Analysis

From content detail in Filament, admin clicks "Analyze with AI". This starts one `ai_analysis_runs` record and a background job that calls the extraction service and writes candidate rows. Content list/detail shows run status (`not_started`, `running`, `completed`, `failed`) the same way pipeline steps are shown in the Stage 1-2 work.

### Review Lexeme Candidates

A table on content detail shows, per candidate:

- text;
- type (`word`, `phrase`, `phrasal_verb`, `idiom`, `collocation`);
- translation (editable inline);
- match state: matched to an existing canonical lexeme (with similarity score) or no match found;
- confidence;
- status (`pending`, `accepted`, `rejected`, `edited`).

Admin actions: accept, reject, edit any field, choose "use matched lexeme" vs "create new anyway", bulk-accept high-confidence items.

### Review Grammar Candidates

A separate table shows, per candidate:

- construction name/summary;
- example sentence from the transcript;
- match state: linked to an existing `grammar_rule` (with topic and similarity) or no match;
- status.

Admin actions: accept + link to matched rule, accept + create new rule (pre-filled form, editable before save), reject, edit.

### Matching Against Existing Catalog

Every candidate is matched automatically at extraction time, not only on demand:

- lexemes: embed candidate text, compare against `lexeme_embeddings` (existing, currently unused) plus exact `normalized_lemma` check as a fast path;
- grammar: embed candidate summary, compare against rule embeddings (new table, mirrors `lexeme_embeddings`).

No match above threshold means the candidate defaults to "new" but stays editable — admin can still manually link it to an existing entry the embedding search missed.

### Conversational Refinement

Below or beside the candidate tables, admin can open a chat scoped to the current analysis run: "merge these two phrasal verbs", "this translation is off, redo it", "why did you match this to Present Perfect Continuous?", "re-run extraction paying more attention to idioms". The chat can only act on the current run's pending candidates through defined tools (edit/merge/split/regenerate/explain/delete) — it cannot touch canonical data or other runs. Every chat-driven change is visible immediately in the candidate tables above.

### Apply

An explicit "Apply approved candidates" action promotes `accepted` rows into canonical tables and leaves `rejected`/`pending` rows untouched in the staging tables for audit. Applying is idempotent — re-running does not duplicate already-applied candidates.

## Technical Direction

### Data Model

New tables (names match `admin-pipeline-plan.md`'s own suggestion, FK naming follows existing convention of full descriptive column names — e.g. `grammar_rule_id`, not `rule_id` — so every FK resolves to its table without needing an explicit `constrained('table')` override):

- `ai_analysis_runs`: `content_id` (FK, `cascadeOnDelete`), `status`, `model`, `provider`, `started_at` nullable, `completed_at` nullable, `failure_reason` nullable. Index `(content_id, status)`.
- `content_lexeme_candidates`: `ai_analysis_run_id` (FK, `cascadeOnDelete`), `text`, `normalized_text` (indexed — fast-path exact-match lookup, same role as `lexemes.normalized_lemma`), `type` string(16), `translation` nullable, `example` nullable, `confidence` `decimal(4,3)` nullable, `matched_lexeme_id` (nullable FK to `lexemes`, **`nullOnDelete`**), `match_score` `decimal(4,3)` nullable, `status` default `pending`. Index `(ai_analysis_run_id, status)`.
- `content_grammar_candidates`: `ai_analysis_run_id` (FK, `cascadeOnDelete`), `title`, `summary` nullable, `example` nullable, `confidence` `decimal(4,3)` nullable, `matched_grammar_rule_id` (nullable FK to `grammar_rules`, **`nullOnDelete`**), `match_score` `decimal(4,3)` nullable, `status` default `pending`. Index `(ai_analysis_run_id, status)`.
- `canonical_lexeme_embeddings`: `lexeme_id` (FK to `lexemes`, `cascadeOnDelete`), `embedding` json, `model_version` string(64). Unique `(lexeme_id, model_version)`. **Not** the existing `lexeme_embeddings` table — see note below.
- `grammar_rule_embeddings`: `grammar_rule_id` (FK, `cascadeOnDelete`), `embedding` json, `model_version` string(64). Unique `(grammar_rule_id, model_version)`. Mirrors `lexeme_embeddings`' storage convention (JSON now, pgvector later per that migration's own comment).

**Correction from initial draft:** the existing `lexeme_embeddings` table is keyed by `content_lexeme_id` (a content *occurrence*, populated only on demand via `ComputeEmbeddingsCommand`/`ComputeEmbeddingsJob`), not by canonical `lexeme_id`. It cannot be reused directly for candidate-vs-canonical matching — going through it would mean occurrence → `content_lexeme_links` → `lexeme_id`, which is indirect, may have no embedding for the relevant occurrence, and can return stale/duplicate hits across contents. Matching against the canonical catalog needs its own `canonical_lexeme_embeddings` table keyed by `lexeme_id`, populated lazily by the matching service (or backfilled once for existing published lexemes), not the occurrence-level table.

Candidate tables are the only place AI writes directly. Canonical tables (`lexemes`, `grammar_rules`, `content_lexeme_links`, `content_rule_links`, `content_lexemes`) are only written by the existing services, triggered from the "Apply" action — never directly by the extraction job.

### AI Provider Layer

Add `AiJsonClient` (`completeJson(system, user, schema): array`) alongside the existing `AiClientInterface`, per Stage 1 of `ai-chat-tutor-plan.md`. Follow the exact pattern `AiClientInterface` already uses — this is the one part of the AI module that is genuinely provider-neutral today: `AiServiceProvider` binds the interface via a `config('ai.provider')` switch, and both `OpenAiClient` and `OllamaClient` implement it. `AiJsonClient` must be bound the same way, with **both** existing provider classes (`OpenAiClient`, `OllamaClient`) implementing it — not added to `OpenAiClient` alone. The extraction/matching/chat services built on top must depend only on the interface, never instantiate a provider class directly, so `AI_PROVIDER=openai` today and any other value later is a config change, not a code change.

Provider-neutral embedding routing is now implemented through `AiProviderFactory`: OpenAI and Ollama adapters both satisfy `EmbeddingsClientInterface`, with `AI_EMBEDDINGS_PROVIDER` selecting the adapter. Matching remains provider-independent at the application boundary.

### Matching Strategy

Two-tier, mirroring how `CanonicalLexemeSyncService` already resolves canonical lexemes today:

1. **Exact match first** (cheap, already-proven pattern): compare `content_lexeme_candidates.normalized_text` against `lexemes.normalized_lemma` for the same language, same as `CanonicalLexemeSyncService::sync()` does for regular tokenized words. Catches the common case without any AI/embedding call.
2. **Embedding fallback** for anything without an exact hit — needed because AI-proposed phrases/idioms have more textual variance than single tokenized words. Reuse `EmbeddingsClientInterface` (already implemented via `OpenAiEmbeddingsClient`). One new `CandidateMatchingService` handles both lexeme and grammar matching: embed candidate text/summary, query `canonical_lexeme_embeddings`/`grammar_rule_embeddings` for nearest neighbors above a configurable similarity threshold, write `matched_*_id` + `match_score` on the candidate row at extraction time.

### Chat/Tool-Calling Layer

Reuse the existing `AiConversation` storage and `ChatContextAiService` context-building pattern, but scope conversations to `ai_analysis_run_id` and give the model a fixed tool set (edit candidate, merge candidates, split candidate, regenerate candidate, explain match, delete candidate) instead of open-ended free text. Tool execution is plain application code operating on `content_lexeme_candidates`/`content_grammar_candidates` — the model never gets direct DB access.

## Dependencies

- Existing AI module (`AiClientInterface`, `AiConversationService`, `ChatContextAiService`, `EmbeddingsClientInterface`).
- Existing canonical schema and services (`CanonicalLexemeSyncService`, `GrammarCatalogServiceInterface`).
- Existing Filament `ContentResource` and its action pattern (`LexemesRelationManager::suggestCefrLevel`).
- Admin permission `manage-content` (already used for `/admin/grammar/*` routes).
- Ideally, Stage 1-2 pipeline visibility work (`admin-pipeline-stage-1-2-epic.md`) landed first, so analysis run status fits the same operational-status UX; not a hard blocker if sequencing changes.

## Done When

- Admin can trigger AI analysis on a content item and see run status.
- Admin sees a structured, editable table of lexeme candidates with translation and match state.
- Admin sees a structured, editable table of grammar candidates with match state.
- Matching against existing `lexemes`/`grammar_rules` happens automatically via embeddings, with manual override always possible.
- Admin can approve/reject/edit candidates individually and in bulk.
- Admin can refine candidates through a scoped chat that only mutates the current run's pending data.
- Approved candidates can be applied into canonical tables without duplication on re-apply.
- Tests cover extraction parsing, matching, review actions, apply idempotency, and chat tool scope (chat cannot affect canonical data or other runs).

## Suggested First Delivery Slice

Ship value before building the chat layer:

1. Candidate schema (`ai_analysis_runs`, `content_lexeme_candidates`, `content_grammar_candidates`).
2. `AiJsonClient` + one combined extraction service (words/phrases + translation + grammar in one structured call).
3. Read-only Filament candidate tables on content detail (this alone answers "I want to see the result in some format" from the original ask).

Then, second slice: matching + accept/reject/edit + Apply. Third slice: chat refinement — it is the most novel and highest-risk part, and is only useful once there is something real to refine.

## Suggested First Tasks

See `docs/product/epic/ai-candidate-extraction-review-tasks.md`.
