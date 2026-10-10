# Shared word-list design

**Status:** Product-owner recommendation, assumed 2026-10-10. This records the target behavior; implementation is not part of this note.

## Research findings

The app already has the beginnings of a shared design:

- `WordRow.vue` owns the compact word header, pronunciation, selection checkbox, and expandable details.
- `WordListItem.vue` wraps it for content and lesson candidates, with queue, known, hide, explain, and example actions.
- `MyWordsPage.vue` uses `WordRow` directly and currently places status, explain, queue, and known controls in the expanded actions.
- Content details and Study use `WordListItem`; lessons use the same wrapper but add a per-word practice link and permanent delete controls.
- Catalog's material cards are a different entity and stay cards. The word lists within content detail pages belong to the shared word-list design.

The same word can occur in multiple sources and have learner-owned queue and learned state. Removing a word from a lesson is therefore distinct from removing it from practice or deleting the learner's lexeme. The existing permanent lesson-word operation deletes the lesson candidate and its source link while preserving the personal lexeme and repetition history.

## Recommended shared row

Use one compact, full-width row across catalog/content words, lessons, Study, and My words. Keep the same order and hierarchy everywhere, with optional host-provided actions and details:

1. Always-visible selection checkbox when the list supports selection.
2. Pronunciation control.
3. Word and translation; tap to expand examples, level, source context and secondary information.
4. One queue toggle: **Add to practice** when not queued, **Remove from practice** when queued.
5. A concise state label where it helps scanning, without duplicating the queue toggle.

Reuse the current shared row base rather than building parallel screen-specific rows. Let each host supply genuinely contextual details and controls. On touch screens, use a 32px visible icon/control inside a 44px target. Keep the visual density close to the existing lesson row, with the lighter/smaller controls requested by Vika.

### State and color

- Add: primary plus, clear accessible label.
- Remove: quiet neutral minus; removing from a queue is reversible and should not look destructive.
- Known: success color/status, set through a bulk action in lists that support it.
- Delete: destructive styling only in the explicit lesson deletion flow.
- Selection: existing primary accent and visible selected state.

The queue toggle changes queue membership only. Stopping practice preserves review history and the source word, matching the existing learning decision.

## List actions

The per-word row should not launch practice. Keep practice launchers at list level: the lesson's **Practice N** action starts the queued words from that lesson; **Practice selected** remains available in My words' bulk toolbar. This avoids a second practice icon on every row while retaining a direct group entry point.

When one or more rows are selected, show a compact selection toolbar with only operations supported by that list and the current selection:

- Add selected words to practice, when any selected word is not queued.
- Remove selected words from practice, when any selected word is queued.
- Mark selected words as known, where the list supports that learner state.
- Practice selected, for My words.
- Delete selected lesson candidates, on lesson lists only.

Show the total selected count once in the selection summary. Keep action buttons compact, in one row, and free of repeated counts; disable an action when no selected item is eligible. Action labels describe the eligible subset (for example, “Add unqueued selected words to practice”), while the confirmation step for irreversible deletion names the total and scope.

AI explanation, more examples, hiding, editing and other item-specific operations are not naturally bulk operations. Keep them in expanded details or the host's contextual edit flow, not in the default row action strip.

## Lesson deletion

Bulk deletion must have the same scope as the existing single-item permanent deletion: permanently remove the selected lesson candidates and their lesson-source links only. Preserve each personal lexeme and all repetition history. Before deletion, show a confirmation with the number of lesson words and explicitly say they will be permanently removed from this lesson. This matches the existing irreversible single-item flow and avoids confusing deletion with removing words from practice.

## Implementation boundary

This is a product/UI design decision, not a request to change the implementation now. The current code diverges from the established Oct 9 decision in a few spots: lesson rows have a separate per-word practice link; My words has extra per-word controls; lesson bulk selection lacks delete. A future implementation should reconcile these through the shared row and host-level action contracts, without changing personal-word or SRS ownership semantics.
