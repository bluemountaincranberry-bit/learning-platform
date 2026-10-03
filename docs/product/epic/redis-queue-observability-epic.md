# Epic: Redis Queue Runtime and Observability

## Goal

Make background processing run automatically and make queue/application status observable through Horizon, Pulse, Telescope, and admin-facing health signals.

This epic supports the admin pipeline work by ensuring that content ingestion jobs do not just get dispatched, but are actually processed by a running worker stack and can be monitored during local development.

## Why Now

The content pipeline already dispatches queued jobs, but the local Docker stack currently has no Redis service and no long-running queue/Horizon worker service. That means YouTube ingestion and future AI-agent jobs can be queued without being processed unless a developer manually runs `queue:work`.

Before expanding the pipeline with transcript and AI stages, we need a predictable runtime:

- Redis-backed queues;
- Horizon running automatically;
- visible pending/completed/failed jobs;
- Pulse available for app health;
- Telescope available for local debugging;
- clear links and operational docs.

## Stakeholders

- Admin/operator: needs to know whether pipeline jobs are running or stuck.
- Developer: needs reliable local async behavior and debugging tools.
- Learner: indirectly benefits because submitted content progresses without manual commands.

## In Scope

- Add Redis to Docker Compose.
- Switch local queue/cache/session configuration where appropriate.
- Run Horizon as a dedicated Docker Compose service.
- Ensure queued jobs process automatically after `docker compose up`.
- Make Horizon reachable and useful for queue/job monitoring.
- Make Pulse reachable for runtime health.
- Make Telescope reachable for local request/job/debug inspection.
- Add basic health/readiness checks or docs for all observability tools.
- Add developer documentation for URLs and common commands.

## Out Of Scope

- Production deployment hardening.
- External managed Redis.
- Sentry or external APM.
- Kafka observability.
- Full admin pipeline status UI.
- AI extraction agents.
- Replacing Laravel Horizon with another queue system.

## Existing Context

Current state:

- `QUEUE_CONNECTION=database` in `src/.env`.
- `config/horizon.php` is already configured for Redis supervisors.
- Horizon, Pulse, and Telescope packages/routes exist.
- `docker-compose.yml` has no Redis service.
- `docker-compose.yml` has no worker or Horizon service.
- `jobs` and `failed_jobs` tables exist for database queue.

Relevant files:

- `docker-compose.yml`
- `src/.env.example`
- `src/config/queue.php`
- `src/config/database.php`
- `src/config/horizon.php`
- `src/config/pulse.php`
- `src/config/telescope.php`
- `src/app/Providers/HorizonServiceProvider.php`
- `src/app/Providers/TelescopeServiceProvider.php`
- `src/app/Providers/AppServiceProvider.php`

## Target Behavior

After running Docker Compose, the developer should have:

- app/web/db/node still running as before;
- Redis running;
- Horizon running as a worker/supervisor;
- queued jobs processed automatically;
- Horizon available at `/horizon`;
- Pulse available at `/pulse`;
- Telescope available at `/telescope`.

Admin/developer should be able to:

- submit or approve content;
- see jobs move through Horizon;
- see failed jobs in Horizon/Telescope;
- inspect app performance/health in Pulse;
- restart Horizon safely when needed.

## Access Rules

Keep access aligned with current gates:

- Horizon: admin users only outside local.
- Telescope: admin users only outside local.
- Pulse: admin users only.

In local development, confirm whether tools are accessible after login as `admin@example.com`.

## Done When

- Redis service is part of Docker Compose.
- App config uses Redis queue locally.
- Horizon service starts automatically with Docker Compose.
- Dispatching a test job results in automatic processing.
- Horizon shows pending/completed/failed queue jobs.
- Pulse loads and records useful runtime information.
- Telescope loads and records local requests/jobs/errors.
- Documentation lists URLs, credentials, commands, and troubleshooting steps.
- Focused verification commands are documented and pass locally.

## Suggested First Delivery Slice

Start with Redis and Horizon:

1. Add Redis service.
2. Switch queue connection to Redis.
3. Add Horizon service.
4. Prove content jobs process automatically.

Then wire and document Pulse/Telescope usability.

## Suggested First Tasks

- Add Redis service to Docker Compose.
- Update environment defaults for Redis-backed queue/cache/session.
- Add a dedicated Horizon service.
- Verify Horizon access and job processing.
- Verify Pulse and Telescope access.
- Add docs for observability URLs and queue troubleshooting.
