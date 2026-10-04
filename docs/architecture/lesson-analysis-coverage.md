# Lesson analysis: coverage of long material (VIK-70)

## Why only ~6 words came out of a PDF

Three independent causes, in the order the text travels:

1. **The notes were cut.** `ExtractPdfTextTool` caps its result at
   `ai.analysis.max_transcript_chars` (8000) to keep the model context small,
   and the lesson agent folded that *same capped result* into
   `Lesson::source_text`. `wordlist-unit-1d.pdf` is ~16 000 characters, so
   the second half (items ~30–54) never reached the notes. (This is the cap
   formerly tracked as VIK-46.)
2. **One model call for the whole text.** `LessonAnalysisService` sent all
   notes in a single request, with none of the chunking that
   `AiContentAnalysisService` has for video transcripts. A long list in one
   completion gets summarised to a short "notable" selection.
3. **The prompt asked for "notable" words.** It never said to list every
   item of a handout, so even short material was curated rather than listed.

(The earlier "Adobe UCS ×6" garbage was the PDF reader itself, fixed in VIK-42.)

## Approach

- **Notes keep everything, the model sees a bounded excerpt.**
  `LessonPdfNotesFolder` re-reads the attachment without the cap and appends
  the full text to the notes; `extract_pdf_text` still returns ≤ 8000 chars to
  the chat model.
- **Analysis in parts.** `LessonAnalysisService` splits the notes with
  `TextChunker` (whole words, prefers line breaks) into parts of
  `ai.analysis.lesson_chunk_chars` (default 4000, env
  `AI_ANALYSIS_LESSON_CHUNK_CHARS`), makes one traced call per part and merges
  the results; the existing dedupe by text/title and embedding matching
  against earlier candidates still apply. The model context per call stays
  bounded regardless of document length.
- **Exhaustive prompt.** The system prompt now asks for one entry per listed
  item, including the last ones, instead of a curated handful.
- Not done (possible follow-up): an independent second "what did we miss"
  pass. Add it only if the live recall below is not good enough.

## Cost

Calls grow linearly with length: the 16 000-character fixture is ~5 calls of
≤ 4000 characters instead of 1. Each call is traced (`lesson_analysis.completeJson`
with `chunk_index` / `chunk_count`) and goes through the same client and rate
limiting as before. Expected cost ≈ the number of parts × one analysis call.

## Known limits

- The run is one queued job (`RunLessonAnalysisJob`, timeout 600 s,
  `REDIS_QUEUE_RETRY_AFTER` default raised to 660 s so Redis does not
  redeliver it mid-run). Nothing is persisted until every part is done; a
  failing part fails the run and the retry starts again. Per-part
  persistence/resume is a possible follow-up.
- Parts are cut at line breaks/spaces without overlap, so an entry spanning
  two lines can straddle a boundary. Grammar titles are deduped by exact
  lowercase title, so differently worded titles from different parts can
  repeat; the learner confirms candidates anyway.
- A stored override of `lesson_analysis_system_prompt` in the prompt registry
  replaces the built-in prompt and so the "be exhaustive" wording.
- Live recall and real cost have not been measured yet (needs provider access).

## How coverage is measured

`tests/Fixtures/pdf/wordlist-unit-1d.expected.json` lists the 54 numbered
items of the PDF (distinctive opening words). `LessonAnalysisCoverageTest`:

- **Always runs:** pushes the real extracted fixture text through the real
  service with a stand-in model that returns the items it was shown; fails
  if any item (including the last, "Playing devil's advocate") is not
  delivered to the model. This catches truncation / chunking regressions.
- **On demand** (`AI_COVERAGE_EVAL=1`): runs the real provider and requires
  recall ≥ `AI_COVERAGE_MIN_RECALL` (default 0.9), listing the missing items.

```bash
AI_COVERAGE_EVAL=1 make test ARGS="--filter=LessonAnalysisCoverageTest"
```
