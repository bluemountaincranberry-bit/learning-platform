# Starter TED catalog

Run the repeatable import on a migrated database:

```bash
make artisan ARGS="content:seed-starter --dry-run"
make artisan ARGS="content:seed-starter"
```

Edit **`src/config/starter-content.php`** to add/remove talks or change their
language and learning target level. After editing cached configuration, run
`make artisan ARGS="config:clear"`. Levels are B1/B2 learning targets, not
certified ratings of the speakers. Titles are supplied in the file, so the
command does not need an extra metadata lookup.

The defaults are five short English talks from TED's YouTube channel:
[Matt Cutts](https://www.youtube.com/watch?v=JnfBXjWm7hc),
[Derek Sivers](https://www.youtube.com/watch?v=V74AxCqOTvg),
[Julian Treasure](https://www.youtube.com/watch?v=eIho2S0ZahI),
[Celeste Headlee](https://www.youtube.com/watch?v=R1vskiVDwl4), and
[Tim Urban](https://www.youtube.com/watch?v=arj7oStGLkU).
The existing studied
[TED-Ed procrastination video](https://www.youtube.com/watch?v=FWTNMzK9vG4)
is included as a sixth source. Other local videos were music or a university
lecture, so they are not starter TED content.

## Processing and prerequisites

Use the existing configured YouTube transcript and AI providers, with
`AI_FEATURE_ENABLED=true` and their credentials available to both Artisan
and workers. Importing makes normal provider calls and uses their existing
quotas/billing. No new service, schema migration or user account is created.
Dry-run performs no writes, dispatches no jobs and works with AI disabled.
When new items are missing, a real import refuses to start with AI disabled.

Each missing source is created as `curated`, `pending`, then dispatched via
`ContentProcessingRequested` → transcript fetch → mechanical processing →
automatic AI analysis → automatic application of words and grammar. Curated
catalog content uses the normal curated analysis policy, rather than the
daily quota for user submissions. Existing Horizon workers process the jobs;
otherwise drain the configured queue with:

```bash
make artisan ARGS="queue:work --stop-when-empty"
```

The command's success means processing was requested, not that providers have
completed successfully. Wait for ingestion **and AI** queues to drain. In
Admin, check that at least five items are `ready`, their AI analysis completed,
and both Words and Grammar are populated. On the phone, open Catalog, choose
a TED source and open its words/grammar. `ready` by itself only means the
transcript was processed; it does not guarantee successful AI enrichment.

## Repeat runs and failures

Existing YouTube video IDs are skipped in every status, regardless of URL
tracking parameters, short/watch/embed/shorts/live URL form, origin or owner.
The import preserves existing transcripts, words, grammar and learner data.
Repeated URLs inside the configuration are skipped too. An application cache
lock prevents overlapping runs of this command; it does not coordinate with
unrelated manual submissions. All entries are validated before any writes.

A failed source is preserved and skipped on subsequent imports. Inspect the
processing failure in Admin and retry its processing action there; for an
AI failure, use the existing analysis action. Do not delete or reset studied
content to retry a starter import. If synchronous processing or queue dispatch
throws, the command reports the affected content ID, continues with the other
sources and exits unsuccessfully. Asynchronous failures remain visible in
content/analysis status and the normal failed-job/log tooling.

## Verification

```bash
make wt-test ARGS="tests/Feature/Content/SeedStarterContentCommandTest.php"
make wt-build
```

Tests use isolated SQLite and substitute only external transcript/AI/embedding
providers. They cover the complete ingestion and auto-application flow, repeat
runs, existing source preservation, duplicate URL forms, invalid configuration,
AI-disabled preview/import, overlapping import protection and transcript failure.
Real provider availability and AI output remain external dependencies.
