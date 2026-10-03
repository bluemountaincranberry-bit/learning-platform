# ADR-010: Word-keyed repetition and personal lexemes

Status: Proposed for Vika's review (VIK-5). Implementation: VIK-11; lesson selection: VIK-25.

Choose one SRS card per learner and canonical lexeme. A source supplies context,
never word identity. Extend the existing lexeme catalog with private, owner-scoped
lexemes for words absent from the shared catalog; use the same learning and SRS
flows for shared and personal words. This closes “save → repeat → remember” for
videos, lessons, and manual words without maintaining two schedulers.

## Evidence and boundaries

The [repository audit](../word-key-research.md) lists schema and every current
legacy-key producer/consumer. Current SRS uniqueness is `(user_id, item_key)`,
with mandatory `content_id`; the key contains neither language nor canonical ID.
Confidence is occurrence-scoped. Learned-state and skips already use canonical
`lexeme_id`; do not treat them as an entirely new model or rerun old destructive
backfills. Source: the audit and linked migrations/services.

Content owns canonical lexical identity, visibility and content occurrences.
Learning owns personal selection, provenance and confidence/learned state.
SRS owns cards, schedules and review history. AI proposes lexical matches; basic
creation and review must work without AI. Use explicit application contracts
between modules, consistent with [ADR-007](ADR-007-modular-monolith-rewrite.md).
Durable mappings and history remain in Postgres, consistent with
[ADR-005](ADR-005-redis-fast-state-not-source-of-truth.md).

## Identity and deduplication

- Store a non-null `lexeme_id` FK on each card; unique `(user_id, lexeme_id)`.
  Pass this ID through practice, review outcomes and AI read tools. Remove
  identity-bearing `type:text` strings after cutover. Card IDs remain review IDs.
- Identity follows the existing language + normalized lemma model. Normalize
  language casing, trim lemma and lowercase consistently with the catalog.
  Preserve punctuation and accents; do not slugify or infer a lemma using an
  embedding. Different languages never merge. Words and phrases use this model;
  exercise type and sense are context, not additional cards.
- Extend `lexemes` with nullable `owner_user_id`: null means shared; otherwise
  visible only to that owner. Replace global language/lemma uniqueness with
  separate shared and per-owner uniqueness (Postgres partial unique indexes).
  Shared rows retain their IDs; keep globally unique slugs by including private
  scope in new slugs, while identity remains the ID. All lookup/search/detail/AI/admin entrypoints
  must enforce visibility; personal entries are excluded from shared enrichment
  and publication. Never rely solely on frontend filtering.
- Resolve an exact shared `(language, normalized_lemma)` first, otherwise an
  exact private match for that learner, otherwise create a private lexeme.
  Concurrent creates use database uniqueness and retry the lookup. Neither a
  lesson nor manual input automatically publishes a private word.
- If a shared match appears later, reconcile only that learner's private word
  and shared reference transactionally on selection/lookup, using the migration
  merge policy below. Repoint their sources/state/cards and retain an alias
  from the private ID; never modify another learner's private data or reset a
  schedule. Copy private notes/translations to private source annotations rather
  than overwriting shared metadata. Keep the old private row as an alias.
- “ran” links to “run” only when an existing source supplies that canonical
  association or an explicit correction confirms it. Unresolved manual surface
  forms remain separate until corrected. Homographs share one lemma card for
  now; no automatic cross-language or fuzzy merge. This is a known limitation.

## Sources and personal learning state

Learning stores multiple encounters per `(user_id, lexeme_id)`. Each encounter
has a stable ID, an optional content occurrence or lesson word reference, and
source text/example plus a display-label snapshot. A manual encounter has no
content/lesson FK. Use a fixed kind (content, lesson, manual) and concrete nullable references,
with constraints forbidding references of a different kind; a deleted source
may leave only its kind and snapshot. Avoid a general polymorphic source system.
Repeated confirmation of the same source is idempotent. Retain all distinct
sources so My words can show “where I met it” and filter by content/lesson.

Card `content_id`, if retained for preferred context, becomes nullable and uses
SET NULL on deletion. Reviews retain their actual context, optional occurrence
and segment, and text snapshots; deleting a source must not delete cards,
confidence or review history. Scheduling requires no source. Validate each review context against the canonical lexeme, learner access
and selected source rather than a single card content; cross-lexeme contexts
are rejected. Context-dependent
exercises are offered only when suitable source material exists; source-free
words still support basic recognition/recall using saved translation/example.

Confidence becomes unique `(user_id, lexeme_id)` with the same five dimensions.
Learned-state and skips keep their existing canonical identity; their context
reference becomes optional. Selection for learning is distinct from a learned
marker: adding a source must not reset a learned word or its schedule. Stopping
learning deactivates the card, preserving reviews and lesson words; restarting
reactivates it. Due queries exclude inactive cards. User UI can call this removal
from repetition, without physically deleting learning history.

## Alternatives rejected

| Option | Reason |
|---|---|
| Canonical card, source as optional context | Chosen: one schedule, language-safe identity, fits existing lexemes and learned-state. |
| Separate personal-card table | Duplicates scheduling, confidence and review APIs; matching a later video needs a cross-table merge. |
| Publish every lesson/manual word to shared catalog | Violates the recorded personal-word decision; private notes and unreviewed entries would leak into shared content. |
| All words private, even known catalog words | Duplicates existing metadata and makes video/lesson dedup harder. |
| Key by language + type + text | Repairs a collision but keeps display text as identity; lemma corrections and inflections still split state. |
| Sense-specific cards now | Adds selection/disambiguation to the core flow without a current product requirement. |

## Migration plan for VIK-11

This ticket changes documents only. The following is a staged implementation
plan, not authorization to delete or rewrite Vika's live learning data.

1. **Inventory and recovery.** Back up Vika's database and test restoration.
   Capture counts, primary keys and full rows for cards, reviews, confidence,
   learned-state, skips, context checks, legacy learning answers and exercise attempts; include source
   tables. Produce a dry-run mapping/report before any writes. Inspect deployed
   schema: repository migration history alone does not prove local data shape.
2. **Resolve legacy identity.** Map each old card through its `content_id` and
   key split at the first colon, exact type/text occurrence, then that occurrence's
   canonical `lexeme_id` and content language. Verify referenced review contexts
   agree. Never match a bare text globally. Multiple candidates, conflicting
   review lexemes, missing occurrences and unknown languages stop cutover with
   the affected IDs in the report. Old language collisions may have hidden
   associations: include all same-key occurrences and learning references in
   the collision audit. Keep unresolved rows intact; resolve explicitly before
   enforcing non-null identity. Do not silently drop or guess their data.
3. **Add schema and provenance.** Add nullable identity columns and source
   tables, private visibility constraints and nullable source FKs. Backfill
   encounters from occurrence-scoped learning flags, cards, confidences,
   progress and reviews; preserve their source snapshots. Preserve source flags
   until each old selection has a new canonical selection and encounter.
4. **Coalesce schedules.** Group cards by learner + resolved lexeme. Keep the
   smallest card ID as survivor, the earliest non-null due date (all null → due
   now), and copy schedule/state/interval/ease together from the most recently
   updated card (timestamp tie → largest ID). Prefer `relearning` if any merged
   card is relearning; do not derive mastery from duplicate-card count. Store
   complete old card snapshots and old→new mapping in a durable migration
   audit. Repoint every review to the survivor before retiring duplicate cards;
   preserve review IDs, timestamps, grades, intervals, hint/error/answer metadata
   and source context. Duplicate-looking reviews remain distinct.
5. **Coalesce confidence/progress.** Resolve confidence through each occurrence.
   Choose all five dimensions from the most recently updated confidence row
   (tie → largest row ID). There are no per-dimension observation timestamps;
   zero can mean unobserved or a score reduced to zero, so do not select only
   non-zero values or infer which dimension is newer. Keep all old rows in
   the audit, including conflicting values; do not sum scores or replay history
   with today's scoring formula. Existing learned markers remain learned;
   preserve the latest `learned_at` and all old timestamps in the audit. If
   alias reconciliation creates duplicate learned/skip rows, coalesce each set
   independently without interpreting timestamps as a new learner action.
   Preserve legacy learning answers verbatim with an audit mapping of any
   resolvable key; no current writer was found, so do not discard or repurpose
   this history. Context checks already use canonical identity: preserve those
   references and remap only explicit aliases. Attempts retain occurrence-level
   context, with canonical references derived where needed; retain their history
   unchanged.
6. **Cut over under a write pause.** Pause learning writes and relevant workers,
   run backfill/merge transactionally, switch all audited writers/readers/events
   and frontend payloads to canonical IDs, rebuild projections/caches and restart
   workers. Apply final FK/non-null/unique constraints only after validation.
   Remove legacy identity columns/code after cutover, retain audit mappings and
   snapshots for recovery. No runtime compatibility layer is required.
7. **Reconcile and roll back safely.** Verify review/attempt/context-check IDs
   and rows are preserved, every old card/score/marker maps to a survivor or
   archived snapshot, every source is retained, and logical learned/skip sets
   match. Active card/confidence counts may shrink through intentional merges:
   reconcile against mapped distinct identities, not raw count equality. Report
   old count, merged count, preserved history count and unresolved count (must
   be zero at cutover). A rerun must be a no-op. Validate on a restored copy
   first. A failed cutover restores the snapshot before resuming writes;
   after new writes resume, use a forward repair that preserves those writes.

Required checks in VIK-11: same lemma across two videos and a lesson → one card
and all sources; same spelling in two languages → separate cards; private-word
visibility denied to another user; no-content exercise → confidence update →
SRS schedule → one history row; stop/restart retains reviews; source deletion
retains learner state; private-to-shared reconciliation; duplicate merge with
contradictory schedules/confidences; unresolved old key blocks cutover without
mutation; restored-data reconciliation and idempotent rerun.

## Review and remaining limits

Product assumptions are recorded in
[DECISIONS.md](../../../.agents/skills/product-owner/DECISIONS.md) under VIK-5.
This ADR does not decide grammar SRS (VIK-10), a new scheduling algorithm,
or lesson ownership (VIK-7). Live-data counts and ambiguous legacy keys remain
unverified until VIK-11's dry run. Vika must review and merge this ADR before
VIK-11 becomes eligible; a prepared ADR does not satisfy the “ADR merged”
acceptance criterion.
