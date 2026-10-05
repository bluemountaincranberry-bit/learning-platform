# SPA design tokens

Colors and radii are centralized in **`resources/css/app.css`** as CSS variables (`:root`) and exposed via **Tailwind theme** in `tailwind.config.js`.

## CSS variables (`:root`)

| Variable | Usage |
|----------|--------|
| `--spa-primary`, `--spa-primary-hover` | Primary actions, links |
| `--spa-danger`, `--spa-danger-bg` | Errors, destructive |
| `--spa-success`, `--spa-success-bg`, `--spa-success-fg` | Success state, learned |
| `--spa-warning`, `--spa-warning-bg`, `--spa-warning-border`, `--spa-warning-fg` | Warnings, recommendations |
| `--spa-muted`, `--spa-muted-light` | Secondary text |
| `--spa-surface`, `--spa-surface-alt` | Backgrounds (cards, tables) |
| `--spa-border`, `--spa-border-strong` | Borders |
| `--spa-fg`, `--spa-fg-secondary` | Text (primary / secondary) |
| `--spa-radius`, `--spa-radius-lg` | Border radius |
| `--spa-mobile-nav-height` | Sticky learner actions clear the fixed mobile navigation |

## Tailwind classes (semantic)

Use these in Vue templates instead of raw Tailwind colors:

- **Primary:** `bg-primary`, `hover:bg-primary-hover`, `text-primary`
- **Danger:** `text-danger`, `bg-danger-bg`
- **Success:** `text-success`, `bg-success`, `bg-success-bg`, `text-success-fg`
- **Warning:** `text-warning`, `bg-warning-bg`, `border-warning-border`, `text-warning-fg`
- **Muted:** `text-muted`, `text-muted-light`
- **Surface:** `bg-surface`, `bg-surface-alt`
- **Border:** `border-border`, `border-border-strong`
- **Foreground:** `text-fg`, `text-fg-secondary`
- **Radius:** `rounded-spa`, `rounded-spa-lg`

## Changing the theme

1. Edit `resources/css/app.css` — change the hex values in `:root`.
2. For dark mode later: add a `.dark` (or `[data-theme="dark"]`) block and override the same variables.

## Learning UI kit (VIK-43)

`shared/ui` owns presentation only. Pages keep API calls, access gates, source IDs,
and pending/error state. No component changes learned state implicitly.

### WordRow and WordCard

`WordRow` is the compact 52px list row. It owns disclosure and emits
`toggleSelect`; selecting does not expand it. `WordCard` supplies expanded audio,
full examples and translations, a canonical dictionary link, source and action
slots. Content and Study use the existing `WordListItem` API adapter; My words
and lesson candidates use the same row directly.

```vue
<WordRow :text="word.lexeme" :translation="word.translation" :level="word.level"
    :lexeme-id="word.lexeme_id" :language="word.language" :examples="word.examples"
    selectable :selected="selected" @toggle-select="toggleSelected(word)">
    <template #actions>
        <UiButton size="touch" :disabled="pending" @click="learn(word)">Learn</UiButton>
    </template>
    <template #source><RouterLink :to="sourceRoute">Source lesson</RouterLink></template>
</WordRow>

<WordCard :text="word.lexeme" :lexeme-id="word.lexeme_id" :language="word.language"
    :examples="word.examples">
    <template #actions><UiButton size="touch" @click="learn(word)">Learn</UiButton></template>
</WordCard>
```

`lexemeId` is the canonical dictionary ID, never `content_lexeme_id` or a
candidate ID. Pass null for unresolved candidates; no dictionary link is invented.
Mutations still use the occurrence ID required by the current API. Hide actions
that the page cannot perform (e.g. pending lesson candidates). `row-actions` is
for short status badges and icon actions; use `actions` for labelled buttons.
Source links and associations go in the expanded slots. Lesson candidates carry
their analysis run language for word and example pronunciation; no browser-language
guess is used. Interactive controls have 44px hit areas, including the compact
32px speech icon and example translation disclosure.

### GrammarCard

Title, optional level, a one-line summary, status and independent primary/secondary
actions. The title uses an actual RouterLink; action buttons never nest in it.
Catalog, My grammar and lesson grammar share this presentation. `statusTone`
is independent of the status label; pass `success` for learned rules.

```vue
<GrammarCard :title="rule.title" :rule-id="rule.id" :level="rule.level"
    :summary="rule.summary" status="Learning">
    <template #actions>
        <UiButton size="touch" :disabled="pending" @click="markLearned(rule)">Mark as learned</UiButton>
    </template>
</GrammarCard>
```

Unmatched lesson candidates pass null `ruleId` and have no invented primary action.
The existing lesson flow remains until VIK-12 replaces its main chat surface.

### ChatMessage

Tutor chat (including Discuss with AI entry points) and lesson chat use the same
sanitized markdown, progress, attachment and error presentation. API-owned retry
stays in the page: retry a failed submission with its original text/context/file;
refresh after a successful submission whose reply could not be loaded.

```vue
<ChatMessage role="assistant"
    content="**Try** [run](/word/42) with [Present simple](/grammar/7)."
    :attachments="[{ name: 'Notes.pdf', status: 'Attached' }]"
    :loading="waiting" :error="chatError" :retryable="canRetry" @retry="retryChat" />
```

Only explicit `/word/<positive ID>` and `/grammar/<positive ID>` links become
44px tappable chips. Use canonical IDs supplied by the source; never infer an ID
from a word or rule title. Ordinary text without a supplied link stays text.
External links remain ordinary links. Markdown is parsed by marked and sanitized
by DOMPurify before injection; long URLs wrap and wide code/tables scroll inside
the bubble. Attachment status comes from the page: `Attached` means the server
accepted the attachment, not that extraction succeeded. The API currently supplies
no per-file extraction status. QuizCard and tutor role restrictions remain intact.

### Verification

From `src`: `npm run test:unit -- resources/js/spa/shared/ui/__tests__`.
Vitest uses jsdom for real DOMPurify sanitization; Happy DOM is unsuitable for
these security assertions. Test seams were confirmed by Vika for VIK-43.
Phone acceptance requires 360px and 390px browser checks with expanded examples,
long titles, attachment names and markdown; a build or DOM emulation is insufficient.
