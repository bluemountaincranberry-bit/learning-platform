# YouTube Submission Ingestion

## Scope

- Sources: user-submitted YouTube links.
- Goal: run the same ingestion pipeline (`FetchTranscriptJob` → `ProcessContentJob`) any time a submission moves through `pending`, `processing`, `ready`, or `failed`.
- Documented items: status machine, key events/jobs, transcript provider config, retry safety, admin fallback paths, and developer aux flows (local stub, HTTP fake).

## Status Model

| Internal status | Meaning |
| --------------- | ------- |
| `pending`       | Submission accepted and queued; transcript fetch should start. |
| `processing`    | Jobs are currently normalizing text and extracting lexemes. |
| `ready`         | Content is ready for catalog/learning and is publicly visible. |
| `failed`        | Background ingestion hit a technical error (missing transcript, provider failure). `processing_failure_reason` stores diagnostics. |
| `rejected`      | Moderation decision separate from technical failure. |

Transitions follow `docs/architecture/content-ingestion-status-model.md` and are enforced by `Content::canTransitionTo`.

## Event/Job Flow

1. `ContentService::submitYoutube` creates a `Content` row with `origin = user-submitted` and dispatches `ContentSubmitted`.
2. `RequestContentProcessingOnSubmissionListener` converts that into `ContentProcessingRequested`, which the dispatcher handles by calling `ContentProcessingOrchestrator::request`.
3. The orchestrator either:
   - queues `FetchTranscriptJob` when `type === 'youtube'` and `source_text` is empty,
   - queues `ProcessContentJob` as soon as text exists,
   - or rejects the request if the status cannot move to `pending`.
4. `FetchTranscriptJob` uses `YoutubeTranscriptFetcherInterface` (configurable drivers defined in `config/transcripts.php`) to populate `source_text`, clears `processing_failure_reason`, and dispatches `ProcessContentJob` exactly once per new transcript.
5. `ProcessContentJob` normalizes the text and clears stale occurrences, but
   does not create learner lexemes. `ContentTokenizer` is used later by AI
   analysis for coverage/uncovered-word reporting; learner lexemes come only
   from validated AI candidates.

## Provider Configuration

- Default driver: `fallback`, configured via `YOUTUBE_TRANSCRIPT_DRIVER` in `.env.example`. It tries the Playwright browser worker, the Node `youtube-transcript` worker, the Python `youtube-transcript-api` worker, direct YouTube Web, and finally Supadata (`SUPADATA_*`) if captions are unavailable or blocked.
- The `browser` Docker service runs Chromium through Playwright and returns native caption segments to the Laravel worker. The learner's browser is not involved in ingestion.
- The browser worker optionally loads a Playwright storage-state file from `runtime/youtube/youtube-storage-state.json`. This file contains cookies and must never be committed; copy/export it locally, then restart the `browser` service. `/health` reports whether the file was loaded, without exposing its contents.
- The `youtube_web` driver only reads existing YouTube caption tracks; it does not generate audio transcripts. It uses an undocumented web-client response and may be affected by YouTube rate limits or response changes.
- The Node and Python library workers are local, keyless adapters around unofficial YouTube web-client APIs. They do not send YouTube cookies to Laravel, but can still be blocked by YouTube/IP limits; the fallback chain records the last provider error.
- Local/test fallback: `stub` driver returning placeholder transcript.
- `ContentTokenizer::MAX_TOKENS` enforces at most 5 000 tokens; duplicates are removed while keeping the first occurrence.
- Failure reasons bubble into `processing_failure_reason`; admin UI surfaces them next to `moderation_comment`.

## Retry and Admin Safety

- Jobs lock the `Content` row before writing to avoid duplicate lexeme batches (`lockForUpdate` inside `FetchTranscriptJob`/`ProcessContentJob`).
- Admin retry actions route through `ContentProcessingOrchestrator`, so every manual retry respects the same transition guards.
- `FetchTranscriptJobTest`, `ProcessContentIntegrationTest`, and `Api/ContentSubmissionFlowTest` cover happy, failure, and retry paths.

## Developer Notes

- Run the new API flow test with `php artisan test tests/Feature/Api/ContentSubmissionFlowTest.php`.
- HTTP interactions are faked in tests via `Illuminate\Support\Facades\Http`, so you can mimic provider failures without real network traffic.
- To switch transcript providers, bind `YoutubeTranscriptFetcherInterface` in `AppServiceProvider` to any implementation registered in `config/transcripts.php`.
## Chrome extension import

For a local/personal workflow, `browser-extension/` provides a manual import
path. The extension reads captions from the currently open YouTube tab and
sends only transcript segments to `POST /api/content/import-youtube-transcript`.
YouTube cookies stay in Chrome. The extension needs a revocable Learning App
API token and does not replace the asynchronous processing pipeline.
The extension requests the backend host permission at runtime for the exact
Learning App URL entered by the user. It first tries JSON/XML caption data and
then opens YouTube's localized transcript panel when the caption response is
empty. It supports YouTube's current `transcript-segment-view-model` elements
and the older transcript element for compatibility. Re-importing the same URL
for the same user is rejected as a duplicate. If the browser has caption
metadata but exposes neither a transcript panel nor a timedtext response, the
extension falls back to `POST /api/content/submit-youtube` so the configured
server providers can continue the ingestion attempt.
