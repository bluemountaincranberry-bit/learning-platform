# Task Breakdown: Admin Pipeline Visibility and Transcript Workspace

## Epic Summary

Build the first admin-operable layer for content ingestion. The work covers Stage 1 and Stage 2 from `docs/product/admin-pipeline-plan.md`: pipeline visibility and transcript workspace. It intentionally stops before AI candidate extraction and review.

## Recommended Implementation Order

1. Pipeline metadata/read model.
2. Filament read-only visibility.
3. Failure display and filters.
4. Retry actions.
5. Transcript workspace.
6. Transcript acceptance and stale-analysis behavior.
7. Verification and documentation pass.

## Tasks

### 1. Add Pipeline Metadata For Content

**Purpose:** Give the admin UI stable fields to display current pipeline state without inferring everything from `Content.status`.

**Scope:**

- Add a migration for Stage 1-2 metadata.
- Add model casts/fillable handling as needed.
- Represent transcript state separately from high-level content status.
- Preserve existing status machine semantics.

**Suggested fields:**

- `pipeline_step`;
- `pipeline_state`;
- `transcript_status`;
- `transcript_accepted_at`;
- `transcript_accepted_by`;
- `analysis_stale_at`.

**Dependencies:** Existing `contents` table and `Content` model.

**Type:** Backend / database.

**Acceptance criteria:**

- Existing content records still load.
- Existing content status transitions still work.
- New fields can represent pending, processing, failed, accepted transcript, and stale analysis states.

### 2. Update Pipeline Jobs To Write Metadata

**Purpose:** Keep pipeline metadata accurate during transcript fetch and content processing.

**Scope:**

- Update transcript fetch start/success/failure paths.
- Update process content start/success/failure paths.
- Store current step and state.
- Keep `processing_failure_reason` as the technical diagnostic.

**Dependencies:** Task 1.

**Type:** Backend / jobs.

**Acceptance criteria:**

- Transcript fetch marks step/state while running.
- Transcript success updates transcript status.
- Processing success marks pipeline completed or ready for next stage.
- Failures surface in metadata and existing failure reason.

### 3. Add Content Table Pipeline Columns And Filters

**Purpose:** Let admin scan the queue and find items needing attention.

**Scope:**

- Update `src/app/Filament/Resources/Contents/Tables/ContentsTable.php`.
- Add columns for content status, pipeline step/state, transcript status, failure reason summary.
- Add filters for status, pipeline state, transcript status, failed items, manual transcript needed.
- Keep the table dense and operational.

**Dependencies:** Task 1.

**Type:** Admin UI / Filament.

**Acceptance criteria:**

- Admin can identify failed and transcript-needed items from the list.
- Admin can filter to failed pipeline items.
- Admin can filter to items without accepted transcript.

### 4. Add Content Detail Pipeline Panel

**Purpose:** Show a readable pipeline timeline/panel on the content detail page.

**Scope:**

- Update `ContentInfolist` or add a dedicated Filament section.
- Show high-level steps:
  - submitted;
  - transcript fetch;
  - transcript accepted;
  - text processing;
  - ready/failed.
- Show failure reason and timestamps where available.

**Dependencies:** Task 1.

**Type:** Admin UI / Filament.

**Acceptance criteria:**

- Admin sees the current pipeline state on content view.
- Failure reason is visible without opening logs.
- Panel does not expose low-level job names as the main UX.

### 5. Add Safe Retry Actions

**Purpose:** Let admin retry failed processing without bypassing orchestration rules.

**Scope:**

- Add Filament action(s) on content list/detail.
- Retry should call `ContentProcessingOrchestrator`, not dispatch random jobs directly.
- Show action only when status/metadata allows retry.
- Confirm before retry.
- Clear or preserve failure reason according to existing pipeline behavior.

**Dependencies:** Tasks 1-4.

**Type:** Backend / Admin UI.

**Acceptance criteria:**

- Admin can retry a failed content item.
- Retry respects existing status transition guards.
- Unauthorized roles cannot retry.
- Retry action gives success/failure feedback.

### 6. Add Transcript Workspace To Content Detail/Edit

**Purpose:** Let admin inspect and manually correct or paste transcript text.

**Scope:**

- Add a transcript section in Filament.
- Show current `source_text`.
- Allow manual edit/paste for authorized admins.
- Add guidance when transcript is missing.
- Keep transcript editing separate from moderation comments.

**Dependencies:** Task 1.

**Type:** Admin UI / Filament.

**Acceptance criteria:**

- Admin can view transcript.
- Admin can paste transcript when missing.
- Admin can edit transcript.
- Form validation prevents empty acceptance.

### 7. Add Transcript Acceptance Action

**Purpose:** Make transcript readiness explicit before downstream analysis stages.

**Scope:**

- Add "Accept transcript" action.
- Store accepted timestamp and admin user.
- Require non-empty transcript.
- Allow unaccept/reopen only if useful; otherwise defer.

**Dependencies:** Task 6.

**Type:** Backend / Admin UI.

**Acceptance criteria:**

- Admin can mark transcript accepted.
- Accepted transcript state appears in list and detail views.
- Empty transcript cannot be accepted.
- Action is permission-gated.

### 8. Mark Downstream Analysis Stale On Transcript Change

**Purpose:** Prevent old tokenization/analysis from silently representing changed transcript text.

**Scope:**

- Detect transcript changes in admin save flow.
- Set `analysis_stale_at` or equivalent.
- Decide whether existing lexeme links remain visible but stale, or require rerun before ready.
- Add clear admin warning when content has stale analysis.

**Dependencies:** Tasks 1 and 6.

**Type:** Backend / Admin UI.

**Acceptance criteria:**

- Editing transcript marks analysis stale.
- Admin sees stale warning.
- Rerun processing can clear stale state.

### 9. Add Focused Tests For Stage 1-2

**Purpose:** Protect the operational pipeline behavior before AI extraction work builds on it.

**Scope:**

- Add/extend feature tests for Filament/admin actions where practical.
- Add job/service tests for metadata updates.
- Add permission tests for retry and transcript actions.
- Add model/status tests for transcript acceptance and stale behavior.

**Dependencies:** Tasks 1-8.

**Type:** Testing.

**Acceptance criteria:**

- Tests cover pipeline metadata updates.
- Tests cover retry through orchestrator.
- Tests cover transcript edit and accept.
- Tests cover unauthorized access.

### 10. Update Documentation

**Purpose:** Keep product and architecture docs aligned with the implemented Stage 1-2 behavior.

**Scope:**

- Update `docs/product/admin-pipeline-plan.md` if the final field/status names differ.
- Update `docs/content/youtube-ingestion.md` with admin retry/transcript workspace behavior.
- Add developer notes for running focused tests.

**Dependencies:** Tasks 1-9.

**Type:** Docs.

**Acceptance criteria:**

- Docs match implementation.
- Stage 3 assumptions are still valid after Stage 1-2.

## Sequential vs Parallel Work

### Sequential

- Task 1 must happen before most other implementation work.
- Task 2 depends on Task 1.
- Task 5 should wait until basic visibility exists.
- Task 8 depends on transcript editing.
- Task 9 is best finalized after behavior stabilizes.

### Parallelizable

- Task 3 and Task 4 can be developed in parallel after Task 1.
- Task 6 can start after Task 1 while table/detail visibility is being refined.
- Task 10 can be drafted while implementation is ongoing, then finalized after tests.

## Risks And Notes

- Adding too many fields to `contents` can make the model noisy. If Stage 1-2 expands, prefer a dedicated pipeline state table.
- Retry actions must route through existing orchestration to avoid duplicate jobs or invalid transitions.
- Transcript edits can conflict with existing lexeme data. Stage 1-2 should mark stale state clearly even if full candidate invalidation arrives in Stage 3.
- Filament UI should stay operational and dense; avoid making this a marketing-style page.

## First Delivery Slice

Deliver Tasks 1-4 first. That gives immediate admin value without changing write behavior:

- pipeline metadata exists;
- jobs write basic state;
- content list shows status;
- content detail explains current pipeline state.

Then add retry and transcript editing as the second slice.
