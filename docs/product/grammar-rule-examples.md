# Grammar rule examples (VIK-39)

Every grammar rule shows 6–8 example sentences: affirmative, negative,
question and 1–2 typical mistakes, each with a translation and the grammar
form highlighted.

## Where examples come from

- **Admin**: written in Filament (rule → Examples), `origin = admin`.
- **Content**: sentences from a video/lesson (`content_id` set). Shown first
  ("From your content").
- **AI**: `AiGrammarRuleExampleService`, `origin = ai`, added to the catalog
  without review (PO decision: catalog enrichment). Marked "AI" on the page.

## Data

`grammar_rule_examples`: `example` (plain, always correct), `target_spans`
(`[[start, end], …]` in characters / code points), `kind`
(`affirmative | negative | question | mistake`), `mistake` (the wrong
sentence for kind = mistake), `translation` + `translation_language`.

Editing a sentence clears its `target_spans` (shows unmarked, never a wrong
highlight). Admin API sync keeps marking and source of unchanged sentences.

## Generation

- The model writes sentences with the form in `**…**` and mistakes as a
  separate list (`wrong` first, then `correct`) — in one mixed list it
  kept storing the error as the correct sentence.
- Content validates: marked form, ≤ 200 chars, wrong ≠ correct, no
  duplicates (case/punctuation-insensitive) against the rule's examples.
- Batches are rows in `grammar_rule_example_generations`: one active batch
  per rule; a learner gets `ai.examples.daily_batches_per_rule` (3) batches
  per rule per day. Job `GenerateGrammarRuleExamplesJob`, no retries.

## Learner

- `GET /api/grammar-rules/{rule}/examples` → examples + latest batch status.
- `POST …/examples/generate` → "More examples" (`ai.examples.more_count`, 4):
  202 queued · 200 active · 429 limited · 503 AI off. The page polls the
  list until the batch is done.
- `POST …/examples/{example}/hide` hides a bad example for this learner only.

## Operations

- `php artisan grammar:backfill-examples [--sync] [--dry-run] [--rule=ID]`
  tops every non-archived rule below `ai.examples.min_per_rule` (6) up to
  `backfill_target` (8), translated into `ai.examples.translation_language`
  (`ru`). `--sync` retries a short rule once and fails if one stays below.
- `php artisan ai:eval --suite=grammar_examples` — real-provider quality
  check on 6 golden rules; rubric in
  `tests/Fixtures/Evals/grammar_examples_cases.php`.
