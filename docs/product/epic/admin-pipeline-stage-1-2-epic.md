# Epic: Admin Pipeline Visibility and Transcript Workspace

## Goal

Give admin users a clear operational interface for the first two stages of the content pipeline: seeing where each submitted content item is in processing, understanding failures, retrying safe pipeline steps, and managing the transcript before AI analysis starts.

This epic turns the current hidden job/status flow into an admin-operable workflow without adding AI lexeme extraction or grammar review yet.

## Why Now

The learning product depends on content ingestion quality. Before building AI extraction agents, admins need to reliably answer:

- what content is waiting, running, failed, or ready;
- why a YouTube submission failed;
- whether a transcript exists and is acceptable;
- how to retry transcript/process jobs safely;
- how to provide a manual transcript when subtitles are unavailable.

Without this, later AI-agent stages will be hard to debug and hard to trust.

## Stakeholders

- Admin/operator: manages submitted content and pipeline recovery.
- Learner: indirectly benefits from reliable ready content.
- Developer: gets observable pipeline state and safer retry/debug loops.

## In Scope

- Admin content queue improvements for pipeline status visibility.
- Content detail pipeline panel.
- Failure reason display.
- Safe retry actions for existing transcript/process pipeline.
- Transcript view/edit workspace in Filament.
- Manual transcript fallback.
- Transcript acceptance state.
- Stale downstream analysis marker when transcript changes.
- Tests for admin visibility, retry permissions, transcript editing, and status transitions.

## Out Of Scope

- AI lexeme extraction.
- AI grammar extraction.
- Candidate review tables.
- Publish gate based on approved AI candidates.
- Audio transcription through Whisper/OpenAI.
- Replacing the existing queue/event architecture.
- User-facing submission status improvements beyond what already exists.

## Existing Context

Relevant docs:

- `docs/product/admin-pipeline-plan.md`
- `docs/architecture/content-ingestion-status-model.md`
- `docs/content/youtube-ingestion.md`
- `docs/architecture/modules-and-events.md`

Relevant code areas:

- `src/app/Filament/Resources/Contents/*`
- `src/app/Modules/Content/Application/ContentProcessingOrchestrator.php`
- `src/app/Jobs/FetchTranscriptJob.php`
- `src/app/Jobs/ProcessContentJob.php`
- `src/app/Models/Content.php`

## Product Behavior

### Admin Queue

Admin can open `Contents` and quickly identify:

- content status;
- current pipeline step;
- transcript availability;
- latest failure reason;
- whether retry is available;
- whether manual transcript is needed.

### Pipeline Detail

Admin can open one content item and see a compact pipeline panel:

- submission accepted;
- transcript fetch;
- transcript accepted;
- text processing/tokenization;
- ready/failed state.

Each step should show state and timing when available.

### Transcript Workspace

Admin can:

- view current `source_text`;
- paste or edit transcript;
- mark transcript accepted;
- retry processing after transcript is accepted;
- see a warning if changing transcript invalidates downstream extracted data.

## Technical Direction

Keep `Content.status` as the high-level product status. Add operational pipeline metadata either as focused fields or as a small pipeline state table. Prefer the smallest model that supports Stage 1-2 without blocking Stage 3.

Suggested minimum fields if using `contents`:

- `pipeline_step`;
- `pipeline_state`;
- `transcript_status`;
- `transcript_accepted_at`;
- `transcript_accepted_by`;
- `analysis_stale_at`;

If this starts to feel too wide, introduce `content_pipeline_steps` earlier. Do not mix moderation comments with technical failure reasons.

## Dependencies

- Existing content status machine.
- Existing YouTube transcript fetch job.
- Existing process content job.
- Filament content resource.
- Admin permissions: `manage-content` and `moderate-content`.

## Done When

- Admin can see pipeline state from the content list.
- Admin can open a content item and see pipeline detail.
- Admin can see clear technical failure reasons.
- Admin can retry safe failed pipeline steps from Filament.
- Admin can view/edit/paste transcript.
- Admin can mark transcript as accepted.
- Editing transcript marks downstream analysis/tokenization as stale or requiring rerun.
- Tests cover happy path, failure visibility, retry action, transcript edit, transcript acceptance, and permissions.

## Suggested First Delivery Slice

Start with read-only visibility:

1. Add pipeline metadata/read model.
2. Show pipeline columns in content table.
3. Show a pipeline panel in content detail.
4. Surface existing failure reason clearly.

Only after visibility is useful, add retry actions and transcript editing.

## Suggested First Tasks

- Add pipeline metadata model/fields for Stage 1-2.
- Backfill pipeline display from existing content statuses and failure fields.
- Add Filament content table columns and filters for pipeline status.
- Add content detail pipeline panel.
- Add safe retry actions that call `ContentProcessingOrchestrator`.
- Add transcript workspace fields/actions in Filament.
- Add transcript acceptance and stale-analysis behavior.
- Add focused feature tests.
