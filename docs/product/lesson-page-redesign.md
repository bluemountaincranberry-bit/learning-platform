# VIK-12: Lesson page — header fields, editable notes, archive

**Date:** 2026-10-05
**Status:** Design pass (mockup + note)
**Scope:** Entire lesson page redesign — header, notes editor, words/grammar/corrections blocks, attachments, optional AI chat tab.

---

## 1. Problem recap

- A lesson today is an AI chat: no date, topic, or teacher; notes only visible as chat bubbles; nothing editable/deletable.
- The phone input is ~107 px wide and partly under the bottom nav.
- The chat is the main UI surface, but VIK-43 already provides shared components (`WordRow`, `GrammarCard`, `ChatMessage`) that should be the primary view.
- PO proxy feedback (2026-10-03): the current chat looks bad, words and links inside it do not work. AI chat becomes an **optional tab**, not the main screen.

---

## 2. Data model (migration)

Add columns to `lessons` table:

```php
$table->date('lesson_date')->nullable();      // date of the lesson
$table->string('teacher')->nullable();        // rename tutor -> teacher/group
$table->string('topic')->nullable();          // lesson topic
$table->string('language', 8)->default('en')->change(); // already exists
$table->json('tags')->nullable();             // array of tag strings
$table->text('notes')->nullable();            // main editable notes (Markdown)
$table->text('homework')->nullable();         // homework field
// status already: active | archived
```

Remove `source_text` from being the "notes" — it stays as the accumulated transcript for analysis (chat + extracted PDF). The new `notes` field is the **human-editable** summary/notes.

---

## 3. API surface

### `POST /api/lessons` (create)
```json
{
  "title": "optional",
  "lesson_date": "2026-10-05",
  "teacher": "optional",
  "topic": "optional",
  "language": "en",
  "tags": ["business", "idioms"],
  "notes": "optional markdown",
  "homework": "optional"
}
```

### `GET /api/lessons` (list)
Returns paginated lessons with:
```json
{
  "id": 1,
  "title": "Lesson 5",
  "lesson_date": "2026-10-05",
  "teacher": "Anna",
  "topic": "Business idioms",
  "status": "active",
  "updated_at": "...",
  "lexeme_count": 12,
  "grammar_count": 3
}
```

### `GET /api/lessons/{id}` (show)
Adds the new fields to the response.

### `PUT /api/lessons/{id}` (update)
All fields editable. `notes` and `homework` accept Markdown.

### `DELETE /api/lessons/{id}` (archive)
Sets `status = archived`. Soft delete not needed — `status` is the machine.

### `POST /api/lessons/{id}/restore` (optional, if we want unarchive)
Sets `status = active`.

---

## 4. SPA: Page layout (390 px mockup)

The lesson page becomes a **single scrolling page** with tabs/sections:

```
┌─────────────────────────────────────┐ 390 px
│ ◀  Business idioms         10:42    │ header (sticky on scroll? no)
│ [Save] [Archive lesson]             │
│ Title          Date                 │
│ [Business idioms] [2026-10-05]      │
│ Teacher        Topic                │
│ [Anna]         [Business idioms]     │
│ Language       Tags                 │
│ [en]           [work, speaking]      │
│ ─────────────────────────────────── │
│ [Notes] [Words] [Grammar]           │ segment tabs (wrap on phone)
│ [Corrections] [Chat]                │
│ ─────────────────────────────────── │
│                                     │
│  ┌───────────────────────────────┐  │
│  │ Notes                     ✎   │  │ Notes tab: auto-growing textarea
│  │ ┌───────────────────────────┐ │  │ with toolbar (B, I, H1, list)
│  │ │We covered the idioms...  │ │  │ Safe-area bottom padding
│  │ │                            │ │  │ when keyboard open.
│  │ └───────────────────────────┘ │  │
│  └───────────────────────────────┘  │
│                                     │
│  Homework                           │
│  ┌───────────────────────────────┐  │
│  │ Write 3 sentences...         │  │
│  └───────────────────────────────┘  │
│                                     │
└─────────────────────────────────────┘
```

### Tabs (segmented control, full width, sticky at top when scrolled? — no, keep simple scroll):
1. **Notes** — main editor (textarea + homework field)
2. **Words** — `WordRow` list (from lesson lexemes, editable per VIK-17 later)
3. **Grammar** — `GrammarCard` list (from lesson grammar, editable per VIK-17 later)
4. **Corrections** — future VIK-17; placeholder empty state
5. **Chat** — current AI chat, optional, read-only here (send from Notes or Chat)

The **current chat UI moves to the Chat tab**. The Notes tab is the default/landing tab.

---

## 5. Notes editor (phone)

- Full-width textarea, `min-height: 120px`, auto-grows (no internal scroll).
- Toolbar above keyboard (or inline top): **Bold, Italic, Heading, Bullet list, Numbered list**.
- **Safe-area-aware**: `padding-bottom: env(safe-area-inset-bottom) + 16px` so the textarea is never covered by the home indicator/bottom nav when focused.
- Keyboard type: `enterkeyhint="done"`, `inputmode="text"`.
- Save: notes auto-save on blur; the header's explicit "Save" button persists all editable fields together.
- Markdown rendering: preview mode toggle (edit/preview) — v2, keep simple edit-only for v1.

---

## 6. Lessons list (LessonsPage.vue)

Each row:
```
┌─────────────────────────────────────┐
│ 5 Oct 2026   Business idioms        │
│ Anna                              12│
│ ─────────────────────────────────── │
│ 5 Oct 2026   Grammar review         │
│ Tutor: Mike                         8│
└─────────────────────────────────────┘
```
- Filter: **All / Active / Archived** (segmented control at top).
- Empty state for archived: "No archived lessons."

---

## 7. Ownership & permissions

- All endpoints already guarded by `assertOwnsLesson` (404 if not owner).
- VIK-12 adds no new permissions — same ownership model.

---

## 8. Migration & data preservation

- Existing lessons: `lesson_date` = `created_at` date, `teacher` = old `tutor`, `notes` = empty, `homework` = empty, `tags` = `[]`.
- `source_text` **stays** (AI analysis input), new `notes` is **human-editable** layer.
- No data loss. `tutor` column stays for backward compat (alias in model getter) or is renamed in migration — agent decides.

---

## 9. Components to reuse (VIK-43)

- `WordRow` — words tab
- `GrammarCard` — grammar tab
- `ChatMessage` — chat tab (read-only rendering)
- `UiCard`, `UiSectionHeader`, `UiInput`, `UiButton`, `UiEmptyState`

---

## 10. Acceptance criteria trace

| AC | Covered by |
|---|---|
| Create lesson, fill header & notes, save, reopen — data is there | API round-trip feature test + lesson header editor |
| Edit and archive work; archived hidden by default | PUT/archive/restore feature and UI tests + active-by-default list filter |
| 360 px notes editor full-width, never covered by bottom nav | Safe-area padding + auto-grow textarea |
| AI chat no longer main input; optional tab | 4-tab layout, Notes default |

---

## 11. Open questions (none — PO proxy answers inline)

| Question | Decision |
|---|---|
| Archive = soft delete or status? | `status: archived` (already exists) |
| Unarchive needed? | Yes — `POST /restore` endpoint |
| `tutor` → `teacher` rename or alias? | **Rename in migration**, keep getter for compat |
| Markdown in notes? | Yes, v1 stores raw Markdown; preview toggle v2 |
| Tags: free text or controlled vocab? | Free text array (student's own tags) |

---

## 12. Implementation order (vertical slices)

1. **Migration** — add columns, backfill existing rows.
2. **API** — extend `LessonController` (store, index, show, update, destroy, restore).
3. **SPA: Lessons list** — add date/topic/teacher columns, archive filter.
4. **SPA: Lesson detail** — replace chat-centric layout with 4-tab layout; Notes tab with auto-grow textarea + homework.
5. **SPA: Lesson create/edit form** — modal or page (modal for create, inline edit on detail).
6. **Tests** — feature tests for API + SPA component tests for Notes editor (390px, safe-area).
