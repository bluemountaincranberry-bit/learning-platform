# Task Breakdown: Global Lexeme Catalog and Learning State

## Epic Summary

This epic separates content-level extraction from the global learning catalog. It makes lexemes reusable across content, moves user progress to canonical lexemes, and prepares the schema for future notes and review workflows.

## Recommended Implementation Order

1. Define canonical lexeme schema.
2. Clarify occurrence storage and mapping.
3. Move user progress to canonical lexemes.
4. Backfill existing data.
5. Add future note support if it does not block the migration.
6. Add tests and docs.

## Tasks

### 1. Define Canonical Lexeme Identity

**Purpose:** Make the global lexeme catalog stable and reusable.

**Scope:**

- Decide the canonical fields for a lexeme.
- Add or confirm normalized text storage.
- Support word and phrase types.
- Define uniqueness rules by language, normalized text, and type.
- Keep status separated from content extraction.

**Type:** Database / domain model.

**Acceptance criteria:**

- A lexeme can be identified globally without depending on a single content record.
- Duplicate canonical lexemes are prevented by schema or service rules.
- Word and phrase items are both supported.

### 2. Clarify Content Occurrence Storage

**Purpose:** Keep content extraction as a separate layer from the reusable catalog.

**Scope:**

- Confirm whether `content_lexemes` remains the occurrence table or should be renamed later.
- Add fields if needed for normalized text, offsets, or review status.
- Preserve ordering and source content reference.

**Type:** Database / content pipeline.

**Acceptance criteria:**

- Each extraction from content can be stored as a distinct occurrence.
- A content occurrence can be linked to a canonical lexeme later.
- The ingestion pipeline still works without manual mapping.

### 3. Redesign Occurrence-to-Lexeme Mapping

**Purpose:** Represent how a found token or phrase resolves to a canonical learning item.

**Scope:**

- Clarify the role of `content_lexeme_links`.
- Make the mapping centered on the content occurrence.
- Store mapping status and notes.
- Keep room for future review/approval metadata.

**Type:** Database / admin workflow.

**Acceptance criteria:**

- One content occurrence can map to one canonical lexeme.
- The mapping can be approved, suggested, or rejected.
- The model is suitable for AI-assisted review later.

### 4. Move User Progress To Canonical Lexemes

**Purpose:** Make learning state reusable across all content sources.

**Scope:**

- Add `lexeme_id` to `user_lexeme_progress`.
- Migrate reads/writes from `content_lexeme_id` to `lexeme_id`.
- Keep a transition path for existing records.
- Update progress services and queries.

**Type:** Learning / database migration.

**Acceptance criteria:**

- Learned state is stored on canonical lexemes.
- The same lexeme learned in one content is recognized everywhere.
- Existing learned data survives migration.

### 5. Add Migration And Backfill Strategy

**Purpose:** Avoid data loss while switching the progress model.

**Scope:**

- Backfill `lexeme_id` for existing progress rows.
- Keep old references available during transition if needed.
- Add safe idempotent migration commands.
- Document rollback assumptions.

**Type:** Migration / operations.

**Acceptance criteria:**

- Existing progress rows are preserved.
- Migration can run repeatedly without corruption.
- The system remains usable during the transition window.

### 6. Add Minimal User Notes Schema

**Purpose:** Reserve a clean place for user comments on lexemes.

**Scope:**

- Add a separate table for user lexeme notes if needed.
- Link notes to `user_id` and canonical `lexeme_id`.
- Keep it independent from learned state.

**Type:** Database / future capability.

**Acceptance criteria:**

- Notes do not interfere with progress.
- Notes can be added later without changing the progress schema again.

### 7. Update Services And Queries

**Purpose:** Make the application use the new lexeme model consistently.

**Scope:**

- Update learning services to read canonical lexeme progress.
- Update recommendation/self-check flows.
- Update content mapping queries.
- Keep the ingestion pipeline intact.

**Type:** Backend / application logic.

**Acceptance criteria:**

- No feature still depends on the old progress shape.
- Learning and recommendation flows work against canonical lexemes.

### 8. Add Tests For Canonical Flow

**Purpose:** Protect the migration and the new model boundaries.

**Scope:**

- Test canonical lexeme uniqueness.
- Test occurrence-to-lexeme mapping.
- Test progress storage on `lexeme_id`.
- Test migration/backfill safety.

**Type:** Testing.

**Acceptance criteria:**

- Tests prove the new catalog survives duplicate content.
- Tests prove user progress is reusable across content.

### 9. Update Architecture Docs

**Purpose:** Keep the repo as the source of technical truth.

**Scope:**

- Document the canonical lexeme model.
- Document the difference between occurrence and learning item.
- Document migration assumptions for progress.

**Type:** Docs.

**Acceptance criteria:**

- A developer can explain the schema from the docs alone.
- The docs match the actual migration path.

## Sequential vs Parallel Work

### Sequential

- Task 1 should come first.
- Task 4 depends on Task 1 and Task 5.
- Task 7 depends on the new model being stable.
- Task 8 is best after the schema is settled.

### Parallelizable

- Task 2 and Task 3 can run in parallel after Task 1.
- Task 6 can be added later without blocking the main migration.
- Task 9 can be prepared alongside implementation once the model is agreed.
