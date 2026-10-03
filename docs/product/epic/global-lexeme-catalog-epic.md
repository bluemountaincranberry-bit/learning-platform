# Epic: Global Lexeme Catalog and Learning State

## Goal

Build a scalable lexeme architecture where:

- content creates lexeme candidates as occurrences;
- canonical lexemes are stored globally and reused across many contents;
- user learning state is tracked on canonical lexemes, not on a single content occurrence;
- the system can later support notes, comments, CEFR levels, review status, and AI-assisted candidate approval.

This epic is about turning the current extraction-only model into a proper learning catalog that can grow without schema churn.

## Why Now

The current pipeline can tokenize content and create content-scoped lexeme rows, but the learning product needs a stronger model:

- the same word or phrase can appear in many videos and texts;
- a user should learn the concept once and reuse that state everywhere;
- phrase-level items need to coexist with single words;
- later features like comments, manual review, and multi-agent approval need a stable canonical entity.

Without a global catalog, every new content source becomes its own isolated vocabulary list, which limits recommendations, progress tracking, and AI workflows.

## Stakeholders

- Learner: sees a stable vocabulary experience across the whole product.
- Admin/editor: can review and publish extracted items.
- AI pipeline: can map new content to canonical learning items.
- Developer: gets a schema that is easier to extend than a content-only token table.

## In Scope

- Define canonical global lexeme storage.
- Keep content occurrences as a separate extraction layer.
- Map content occurrences to canonical lexemes.
- Move user learning progress to canonical lexemes.
- Preserve existing content ingestion and pipeline behavior during migration.
- Add a place for future user notes/comments on lexemes.
- Add migration/backfill strategy for existing data.
- Add tests for mapping, progress, and migration safety.

## Out Of Scope

- Full AI review UI for candidate approval.
- Rich lexeme editing experience in SPA.
- Spaced repetition algorithm redesign.
- Advanced note-taking UX.
- Reworking all content ingestion logic in one step.
- Replacing the current content pipeline.

## Existing Context

Relevant module boundaries:

- `Content` owns ingestion, status, and extraction pipeline.
- `Learning` owns study flow and progress read-models.
- `SRS` owns review scheduling and due items.
- `AI` may later enrich lexeme candidates and explanations.

Current schema concepts:

- `content_lexemes` stores extracted items from one content record.
- `lexemes` stores canonical learning entities.
- `content_lexeme_links` already exists as a mapping layer, but its role should be clarified and possibly narrowed to occurrence-to-canonical mapping.
- `user_lexeme_progress` currently points to `content_lexeme_id`, which is too narrow for a global learning catalog.

Relevant code areas:

- `src/app/Models/ContentLexeme.php`
- `src/app/Models/Lexeme.php`
- `src/app/Models/UserLexemeProgress.php`
- `src/app/Models/ContentLexemeLink.php`
- `src/app/Services/ContentTokenizer.php`
- `src/app/Modules/Content/Application/LexemeService.php`
- `src/app/Modules/Learning/*`

## Product Behavior

### Content Ingestion

When content is processed, the system creates lexeme occurrences for that content:

- each occurrence keeps its source content;
- each occurrence stores text, type, order, and optional offsets later;
- occurrences can be linked to a canonical global lexeme.

### Global Lexeme Catalog

The system stores canonical lexemes that are shared across content:

- one canonical item per normalized lexeme identity;
- supported types: word and phrase;
- optional language and CEFR metadata;
- review status for editorial or AI approval;
- future note/comment support per user.

### Learning State

User progress is tracked on canonical lexemes:

- learned state belongs to the lexeme concept, not to one content occurrence;
- learned state should survive when the same lexeme appears in a new video;
- study flow can query learned state once and reuse it everywhere.

### Future Notes

Users can later attach personal notes or comments to a lexeme:

- the note belongs to the user and the canonical lexeme;
- notes are separate from learned state;
- notes do not need to be implemented in the first release, but the schema should leave room for them.

## Technical Direction

Prefer a layered model:

1. `content_lexemes` or a renamed occurrence table stores what was found in a specific content item.
2. `lexemes` stores the canonical learning item.
3. `content_lexeme_links` maps occurrence to canonical lexeme.
4. `user_lexeme_progress` points to canonical `lexeme_id`.
5. Optional future tables can hold user notes, pronunciation notes, and review annotations.

This keeps the ingestion pipeline simple and avoids forcing every content-specific token into the global learning catalog.

Suggested canonical fields:

- `lexemes.normalized_text`
- `lexemes.display_text`
- `lexemes.type`
- `lexemes.language`
- `lexemes.cefr_level`
- `lexemes.status`

Suggested learning fields:

- `user_lexeme_progress.lexeme_id`
- `user_lexeme_progress.learned_at`
- future `user_lexeme_notes.note`

## Dependencies

- Existing content pipeline and tokenizer.
- Existing `lexemes` table and content-linking tables.
- Existing learning and SRS code that currently references `content_lexeme_id`.
- Existing admin permissions for content and grammar catalog.

## Done When

- Content occurrences and canonical lexemes are modeled as separate concepts.
- A content item can create new lexeme occurrences without duplicating canonical learning state.
- User progress is stored against canonical lexemes.
- Existing content processing still works.
- Existing learn/progress screens still function or are migrated safely.
- Tests cover canonical mapping, progress persistence, and migration/backfill behavior.

## Suggested First Delivery Slice

Start with data model stabilization:

1. Introduce canonical lexeme identity fields if they are missing.
2. Clarify the occurrence-to-canonical mapping table.
3. Move user progress to canonical lexemes behind a safe migration path.
4. Add a thin user note table only if it does not slow the core migration.

## Suggested First Tasks

- Define the canonical lexeme schema and unique identity rules.
- Redesign the occurrence-to-canonical mapping layer.
- Migrate user progress from `content_lexeme_id` to `lexeme_id`.
- Add backfill commands for existing learned items.
- Add tests for progress lookup, duplicate suppression, and migration safety.
- Add a minimal user note schema for future use.
