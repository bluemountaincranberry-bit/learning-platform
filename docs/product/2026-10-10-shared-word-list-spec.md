# Shared word-list design and behavior

## Problem Statement

Word lists appear in content details (the catalog's word lists), Study, lesson details, and My words. They share the same basic learner task—scan a word, hear it, inspect context, select it, and decide whether it belongs in practice—but their row actions and controls differ. Lesson rows have an additional per-word practice launcher, My words exposes several independent controls per word, and bulk actions vary by host. This makes familiar actions harder to find and makes the lesson row feel crowded.

The user wants one consistent, compact design for word rows throughout the app, while allowing each screen to provide actions that make sense for its context. The content catalog's material cards are a different entity and are not part of the shared word-row design.

## Solution

Use one compact, mobile-first word row across catalog/content word lists, Study, lessons, and My words. Keep the row's structure and action placement consistent, and let each host provide contextual details and bulk actions.

Each selectable row shows a selection checkbox, pronunciation control, word and translation, and one practice-queue toggle. The toggle adds a word to practice when it is not queued and removes it when it is queued. It does not represent the separate learned marker. Remove per-word practice launchers; keep practice entry points at list level, including **Practice N** for queued lesson words and **Practice selected** in My words.

Show a compact bulk-action toolbar when at least one item is selected. Offer only operations supported by that host and selection: add/remove from practice, mark known where supported, practice selected in My words, and permanently delete selected lesson candidates. Explain, examples, hide/show, and edit remain word-specific actions in expanded details or the host's contextual editing flow.

Use lesson-row visual density as the baseline, with smaller visual controls and consistent colors. Keep controls accessible on touch screens. Preserve the distinction between a lesson candidate/source, the learner's personal lexeme and source history, practice queue membership, learned marker, and repetition history.

## User Stories

1. As a learner, I want word rows to have the same structure in content lists, Study, lessons, and My words, so that I can scan and act on words without relearning each screen.
2. As a learner, I want the word and its translation to remain easy to scan in a compact row, so that I can review many words without excessive scrolling.
3. As a learner, I want pronunciation available directly on every word row, so that I can hear a word without opening its details.
4. As a learner, I want to expand a row for examples, level, source context, and secondary information, so that details are available without making every row tall.
5. As a learner, I want selecting a row not to expand it, so that bulk selection does not disrupt scanning.
6. As a learner, I want a visible checkbox on each row in lists that support selection, so that I can choose several words deliberately.
7. As a learner, I want a single, clearly labeled control to add an unqueued word to practice, so that I can take the next learning step without choosing among competing row actions.
8. As a learner, I want that same control to remove a queued word from practice, so that queue membership can be changed without a separate control.
9. As a learner, I want removing a word from practice to preserve the word, its sources, learned marker, and repetition history, so that stopping practice does not erase my learning data.
10. As a learner, I want queue membership and the learned marker to remain separate concepts, so that adding a known word to practice or marking an unqueued word known does not silently change the other state.
11. As a learner, I want the row to show a concise status when it helps distinguish queued, known, or source-specific states, so that I can understand the word's state without duplicate or contradictory controls.
12. As a learner, I want the Add control to use the primary color, Remove to use a quiet neutral style, Known to use the success color, and Delete to use destructive styling, so that visual emphasis matches the consequence.
13. As a learner using a phone, I want compact 32px visual controls within touch targets of at least 44px, so that the list stays light while controls remain easy to tap.
14. As a learner, I want the selection toolbar to appear only after selecting a word, so that the normal list stays uncluttered.
15. As a learner, I want bulk add/remove controls to state how many eligible words they affect, so that the action's scope is clear.
16. As a learner, I want the selected total shown once and unavailable bulk actions disabled, so that I can understand the toolbar without repeated counts on every button.
17. As a learner, I want select-all to apply only to the currently filtered/visible word list, so that I can safely act on a narrowed set.
18. As a learner, I want selection to remain understandable when filters or status segments change, so that an action does not unexpectedly affect hidden words.
19. As a learner, I want to mark selected words as known where that list supports the learned marker, so that I can update several words without opening each one.
20. As a learner in My words, I want to start practice for selected words from the bulk toolbar, so that I can practice a chosen subset in one step.
21. As a learner in a lesson, I want a list-level **Practice N** action for words from that lesson that are in my practice queue, so that I can practice the lesson set without a practice icon on every row.
22. As a learner, I want the lesson's **Practice N** count and destination to reflect queued words from that lesson, so that I know what will start.
23. As a learner, I want adding a lesson candidate to practice to establish/reuse my personal lexeme and lesson source association as needed, so that it becomes available in My words without duplicating a canonical word.
24. As a learner, I want deleting a lesson word to be distinct from removing it from practice, so that I can correct a lesson's source material without confusing it with my personal learning queue.
25. As a learner, I want to select and permanently delete multiple lesson candidates, so that I can efficiently remove incorrect or duplicate words from a lesson.
26. As a learner, I want bulk lesson deletion to require confirmation that names the number of words and says they will be permanently removed from this lesson, so that I understand the irreversible source-level effect.
27. As a learner, I want lesson deletion to preserve any corresponding personal lexeme, its other source associations, learned marker, and repetition history, so that removing a source candidate never erases my personal learning state.
28. As a learner, I want bulk deletion to affect only the selected lesson candidates, so that unrelated words and other lesson sections remain unchanged.
29. As a learner, I want AI explanations, more examples, hide/show, and editing to remain available as word-specific actions, so that bulk controls stay focused on operations that can safely apply to many words.
30. As a learner, I want material cards in the catalog to retain their content-card design, so that a shared word-list component does not make videos or other sources look like words.
31. As a learner, I want shared rows to expose clear accessible labels and selected state, so that assistive technology can distinguish the word, its actions, and its selection.
32. As a learner, I want pending operations to prevent duplicate submissions and communicate progress, so that repeated taps do not create inconsistent queue or deletion state.
33. As a learner, I want errors from a bulk operation to leave the selection and list state understandable, so that I can retry or correct the selection without losing context.

## Implementation Decisions

- Reuse the existing shared compact row as the presentation seam. Keep the shared row responsible for word header, pronunciation, checkbox, disclosure, and expanded card; host screens continue to own source identity, API calls, authorization, pending/error state, and host-specific actions.
- Reconcile the row action contract across content/Study, lesson, and My words: one per-word queue toggle only. The toggle's accessible label and state communicate Add to practice or Remove from practice. It does not set or clear the learned marker.
- The host action for a lesson candidate must preserve current selection semantics: resolve or create/reuse the learner's personal lexeme, retain the lesson encounter/source, and activate practice only when the learner chooses Add to practice. Repeating the action is idempotent. Do not publish personal words to the shared catalog.
- Keep per-word practice navigation out of the row. Keep list-level Practice N on lesson lists, scoped to queued lesson words, and Practice selected in My words.
- Selection toolbar behavior is contextual. It supports eligible bulk queue add/remove; bulk mark-known where supported; My words practice-selected; and lesson-only permanent deletion. Keep explain, examples, hide/show, and edit as item-level actions.
- Selection and select-all are scoped to the current visible page/filter. Clear selection when the filter or page changes so hidden rows cannot be acted on unexpectedly. Show the selected total once in the toolbar summary. Keep bulk actions compact in one row without repeating counts on buttons; disable actions with no eligible selected words and use labels that make eligible subsets clear.
- The shared visual treatment follows the current lesson-row density. Use 32px visual controls in 44px touch targets. Add uses primary styling, Remove uses quiet neutral styling, known status/action uses success styling, and permanent delete uses destructive styling only in its confirmed flow. Maintain visible focus, selected state, and accessible labels.
- Permanent lesson deletion is scoped to lesson candidates and their lesson-source links. It must not delete the canonical/personal lexeme, other encounters, learned marker, repetition card, or review history. The existing single-item permanent-delete behavior is the semantic baseline; the bulk operation extends that operation to a set of selected candidates.
- Bulk deletion validates that every requested candidate belongs to the addressed lesson and learner. Perform the batch atomically: reject the request without partial deletion if the selection is invalid or any deletion cannot be completed. Return enough information for the UI to reconcile the list and selection. The confirmation is client-side and includes the item count and lesson scope.
- Preserve existing filters, status segments, search, examples, source links, and context-specific detail fields unless they conflict with the shared row hierarchy. The catalog's material cards are out of this component boundary.
- Use the learner-facing API/application boundary as the highest integration seam for destructive bulk deletion and learner-state mutations. Keep component tests focused on observable row/toolbar behavior, and test persistence/data ownership at the API boundary. Follow ADR-009 contract-first and integration-first testing and ADR-010's canonical lexeme/source/repetition ownership rules.

## Testing Decisions

- Good tests assert observable behavior and durable domain outcomes, not component internals, CSS class arrangements, or the choice of a particular helper/composable. A visual sizing check may assert the documented accessible target behavior where the component test environment can observe it.
- At the shared-row seam, verify checkbox selection does not expand the row, pronunciation remains available, details expand, and exactly one practice-queue toggle is rendered with the correct state and accessible label. Verify the queue toggle does not emit a learned-state action.
- At the shared toolbar seam, verify the toolbar appears for a non-empty selection, actions are scoped to the current filtered view, the selected total is shown once, unsupported actions are disabled, labels describe eligible subsets, and action buttons stay in one row without repeating counts. Verify My words exposes Practice selected and lesson lists do not expose per-word practice launchers.
- At the lesson page integration seam, verify Add-to-practice creates/reuses the personal lexeme/source association and activates the queue; Remove-from-practice preserves the lesson candidate and learner history; Practice N targets only queued words from that lesson; and selected lesson deletion requires confirmation and removes only those candidates.
- At the learner-facing API/application integration seam, verify batch deletion is authorized and lesson-scoped, atomic on invalid/mixed IDs, idempotency/duplicate handling is defined, and it preserves personal lexemes, other source associations, learned state, repetition cards, and review history. Also verify single-item permanent deletion remains consistent with the bulk semantics.
- Exercise loading, empty, pending, success, and failure states for the selection toolbar and destructive confirmation. On failure, verify no optimistic state falsely reports success and the user can understand/retry the operation.
- Prior art: `WordRow.spec.ts` covers selection, disclosure, pronunciation/details links, unresolved lesson words, status, and touch-target geometry. `WordListToolbar.spec.ts` covers filtered selection and bulk actions. `LessonItemsTest.php` covers lesson word lifecycle, ownership, idempotent association to My words, and source preservation. `MyWordsApiTest.php` covers learner-owned word and practice behavior. Existing lesson-page interaction coverage in the ChatPages UI suite exercises the row's add/practice path and lesson practice launcher.
- Run the focused SPA unit/component and PHP feature/integration suites for touched seams, then the repository's relevant build/check commands according to its standard workflow. Add no tests for private implementation details.

## Out of Scope

- Redesigning catalog material cards, lesson cards, grammar lists, or correction lists.
- Changing how words are extracted, normalized, matched, translated, or published into the shared catalog.
- Changing SRS scheduling, confidence calculations, learned-marker semantics, or the policy that stopping practice preserves history.
- Adding new per-word practice modes or changing the contents of lesson-level Practice N / My words Practice selected beyond their stated scopes.
- Making AI explanation, editing, hiding, or example retrieval bulk operations.
- Redesigning filters, status segments, sorting, or search beyond keeping selection and action scope explicit and consistent with the existing current-view behavior.
- Deleting a learner's lexeme, source history, repetition card, or review history through lesson deletion.
- Changing grammar and correction deletion semantics.

## Further Notes

- Product-owner recommendation is recorded as assumed pending review in the product decisions log and the companion design note. The spec makes the recommendation concrete for implementation; any later product change should update those records.
- The checkout already contains uncommitted changes for single-item permanent deletion of a lesson word in the lesson service/controller/routes/API/page and the shared word-list item. Do not overwrite or revert those changes while implementing this spec. The bulk-delete work should extend and accompany that existing single-item operation.
- The established Oct 9 product decision described a checkmark as marking a word known and adding it to My words. The newer recommendation intentionally separates the practice-queue toggle from the learned marker and assigns bulk mark-known separately. Implementation should reconcile any remaining mismatch in row controls while preserving the domain distinction in ADR-010.
- The design note states that lesson Add to practice establishes the personal lexeme/source association as needed. The current lesson action confirms this is a two-step server interaction: it calls add-to-My-words when needed, then starts learning through the personal-word API. Implementation should preserve one learner-facing action and idempotent results, while keeping lesson source membership distinct from queue state; it may consolidate the backend boundary if that reduces partial-failure states.
- This task is specification-only. It does not publish an issue to Linear and does not authorize implementation in this agent task.
