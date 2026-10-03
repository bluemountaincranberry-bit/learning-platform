# Timed transcript segments

Content ingestion keeps `contents.source_text` as a compatibility/read-model
field and stores timed dialogue in `transcript_segments`.

Each segment has `start_ms`, `end_ms`, `sequence`, `text`, `language` and a
provider/source marker. `transcript_segment_lexemes` maps every matching
`content_lexeme` occurrence to a segment and stores character offsets. This
allows the learner UI to synchronize the transcript with YouTube playback and
open a word from its exact source context.

The `FetchTranscriptJob` accepts a `TranscriptDocument` from the YouTube
provider, replaces the segment set idempotently, and keeps the existing
`source_text` processing path. After AI candidates are validated and applied,
the analysis job links the resulting learner lexemes to their occurrences.

The learner API is:

```text
GET /api/content/{content}/transcript
GET /api/content/{content}/transcript?from_ms=120000&to_ms=180000
```

Providers that return only plain text create one fallback segment from `0ms`.
This keeps old content processable while making timestamps available whenever
the source provides them.
