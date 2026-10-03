# Task: Show translation and example next to a word (Vue SPA)

## Context

The backend already extracts words/phrases via AI, finds a translation and an
example sentence, and after admin review (Accept → Apply in the Filament
admin) writes them into canonical tables:

- `lexemes` (`App\Models\Lexeme`) — canonical word/phrase. Fields: slug,
  language, lemma, normalized_lemma, part_of_speech, status, level, notes.
  No translation on the lexeme itself — that's intentional.
- `lexeme_examples` (`App\Models\LexemeExample`) — belongs to a lexeme.
  Fields: language, example, translation, is_primary, sort_order, plus a
  nullable `content_id` (FK to `contents`, `nullOnDelete`).
  `content_id = null` → a globally curated example (admin-authored).
  `content_id = N` → sourced from content N specifically (written by
  `App\Modules\Ai\Application\AiCandidateApplyService` after Accept + Apply).
- Relation: `Lexeme::examples(): HasMany`, ordered by `sort_order`.

## Problem

None of this reaches the SPA today. Confirmed in the backend:

- `App\Modules\Content\Application\ContentService::getLexemesWithLearnedFlags()`
  (backs `GET /api/content/{id}/lexemes`) returns only
  `id, type, text, sort_order, learned`.
- `App\Modules\Learning\Application\LearnedLexemesService`
  (backs `GET /api/me/learned-lexemes`) — same gap.
- `src/resources/js/spa/types/lexeme/LexemeWithLearned.ts` has no
  translation field.
- `src/resources/js/spa/pages/StudyPage.vue` and `MyWordsPage.vue` render
  only `lexeme.text` and `lexeme.type`.

Important: words that only went through the plain regex tokenizer
(`App\Services\ContentTokenizer`, no AI involved) will have no translation
at all — that's expected, not a bug. Only words that went through AI
candidates + Apply have one. The frontend must treat a missing translation
as "nothing to show," not an error state.

Not to be confused with the existing "Explain" button
(`App\Modules\Ai\Application\AiExplainLexemeService`) — that's a separate,
on-demand AI call. The translation described here is precomputed data,
already stored, and should render immediately with the word list — no
extra request per word.

## What to build

### 1. Backend — expose translation/example in the API

- Extend `ContentService::getLexemesWithLearnedFlags()` and
  `LearnedLexemesService` to include `translation` and `example` in the
  response.
- A word (`ContentLexeme`) reaches its canonical `Lexeme` via
  `content_lexeme_links` (`ContentLexeme::canonicalLexeme(): HasOneThrough`).
  From there, resolve `Lexeme::examples()` and pick, in order:
  1. content-scoped primary: `content_id = <current content>` AND
     `is_primary = true`;
  2. global primary: `content_id IS NULL` AND `is_primary = true`;
  3. otherwise `null` (no translation — acceptable).
- Eager-load (`with()`), do not resolve per-row in a loop — pages already
  render hundreds of lexemes.
- Update types: `LexemeWithLearned.ts`, `LearnedLexemesResponse.ts` — add
  `translation?: string | null`, `example?: string | null`.

### 2. Frontend — render it next to the word

- `StudyPage.vue`: under `lexeme.text` in both "To learn" and "Learned"
  lists, show `lexeme.translation` when present.
- `MyWordsPage.vue`: same, in the user's word list.
- Match existing page conventions (`UiCard`, `UiBadge`, the Tailwind
  classes already used nearby) — no new component system.
- `example` is secondary — smaller text or a tooltip under the
  translation, not the main focus.

### 3. Tests

- Backend: feature test for `getLexemesWithLearnedFlags` — translation is
  returned, content-scoped example wins over global, and the endpoint does
  not break when a lexeme has no examples at all (`translation: null`).
- Frontend: update/add component tests if the project has them for these
  pages; otherwise manual verification in the browser is enough.

## Verification

- `docker exec blue-app-1 php artisan tinker` can find a word that already
  has a translation (anything pushed through Accept + Apply in the admin);
  open that word's content in the SPA (not the admin) and confirm the
  translation renders.
- `php artisan test` must not introduce new failures. Two pre-existing,
  unrelated failures (`ContentSubmissionFlowTest`, `GlobalLearnedIntegrationTest`)
  already exist on `main` — leave them alone, they're not part of this task.
