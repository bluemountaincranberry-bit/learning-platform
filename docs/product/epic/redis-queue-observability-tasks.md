# Task Breakdown: Redis Queue Runtime and Observability

## Epic Summary

This epic makes async processing run automatically and observable in local development. It adds Redis, switches queues to Redis, runs Horizon as a long-lived service, and documents/validates Horizon, Pulse, and Telescope.

## Recommended Implementation Order

1. Add Redis service and environment defaults.
2. Switch Laravel queue runtime to Redis.
3. Add Horizon worker service.
4. Verify queue processing.
5. Verify Horizon/Pulse/Telescope access.
6. Add docs and troubleshooting.

## Tasks

### 1. Add Redis To Docker Compose

**Purpose:** Provide the queue/cache backend required by Horizon.

**Scope:**

- Add a `redis` service to `docker-compose.yml`.
- Use a stable Redis image.
- Add a named volume if persistence is useful for local development.
- Expose port only if useful locally; otherwise keep it internal.
- Add dependency links from app/Horizon services where needed.

**Dependencies:** Existing Docker Compose stack.

**Type:** Infrastructure.

**Acceptance criteria:**

- `docker compose up` starts Redis.
- App container can connect to Redis by service name.
- Redis does not break existing db/web/node services.

### 2. Update Environment Defaults For Redis Runtime

**Purpose:** Make local app config use Redis for async infrastructure.

**Scope:**

- Update `src/.env.example`.
- Decide whether to update local `src/.env` as part of implementation or document manual update.
- Set:
  - `QUEUE_CONNECTION=redis`;
  - `REDIS_HOST=redis`;
  - `REDIS_PORT=6379`.
- Consider whether `CACHE_STORE` and `SESSION_DRIVER` should remain database or move to Redis.

**Recommendation:**

- Use Redis for queues immediately.
- Keep cache/session as-is unless there is a clear reason to move them in the same task.

**Dependencies:** Task 1.

**Type:** Configuration.

**Acceptance criteria:**

- `php artisan about --only=drivers` shows queue as Redis.
- Laravel can enqueue jobs without Redis connection errors.
- Existing database migrations/tests are not affected by queue default change.

### 3. Add Horizon Service To Docker Compose

**Purpose:** Process queued jobs automatically when the stack is running.

**Scope:**

- Add a `horizon` service using the same PHP image/build as `app`.
- Mount `./src:/var/www/html`.
- Use command: `php artisan horizon`.
- Depend on `app`, `db`, and `redis`.
- Ensure service runs as the app user where possible.
- Ensure logs are visible through `docker compose logs horizon`.

**Dependencies:** Tasks 1-2.

**Type:** Infrastructure / queue runtime.

**Acceptance criteria:**

- `docker compose up` starts Horizon.
- Horizon processes default queue jobs.
- Horizon restarts cleanly after code/config changes or container restart.

### 4. Verify Horizon Configuration

**Purpose:** Ensure Horizon is actually aligned with the queues used by the application.

**Scope:**

- Review `src/config/horizon.php`.
- Confirm supervisor connection is `redis`.
- Confirm queue name matches dispatched jobs, usually `default`.
- Confirm local `maxProcesses` is reasonable.
- Confirm retry/timeout values are safe for transcript and future AI jobs.

**Dependencies:** Task 3.

**Type:** Backend configuration.

**Acceptance criteria:**

- Horizon dashboard shows active supervisor.
- Jobs dispatched to the default queue are picked up.
- Failed jobs appear in Horizon when a job fails.

### 5. Verify Content Pipeline Jobs Process Automatically

**Purpose:** Prove the admin/content pipeline no longer needs manual `queue:work`.

**Scope:**

- Dispatch a safe test job or use existing content processing flow.
- Verify `FetchTranscriptJob` / `ProcessContentJob` behavior with stub/fake provider where possible.
- Confirm jobs leave the queue.
- Confirm failed jobs are visible if a controlled failure is triggered.

**Dependencies:** Tasks 1-4.

**Type:** Verification / integration.

**Acceptance criteria:**

- A queued job is processed by Horizon without running manual commands.
- Queue is empty after successful processing.
- Failure is visible in Horizon or failed job storage.

### 6. Confirm Horizon Access

**Purpose:** Make the queue dashboard usable for admin/developer.

**Scope:**

- Verify `/horizon` route loads at local app URL.
- Verify access with `admin@example.com`.
- Verify non-admin access is blocked where gates apply.
- Document login requirement.

**Dependencies:** Task 3.

**Type:** Observability / access.

**Acceptance criteria:**

- Admin can open Horizon.
- Horizon shows supervisors and jobs.
- Access rules match current gate expectations.

### 7. Confirm Pulse Access And Recording

**Purpose:** Make runtime health/performance visible.

**Scope:**

- Verify `/pulse` loads.
- Confirm Pulse gate allows admin.
- Confirm Pulse records useful local data after requests/jobs.
- Check whether any Pulse recorders require extra scheduled commands.

**Dependencies:** Running app stack.

**Type:** Observability.

**Acceptance criteria:**

- Admin can open Pulse.
- Pulse displays current application data.
- Documentation explains if any additional command is needed.

### 8. Confirm Telescope Access And Job Debugging

**Purpose:** Make local debugging available for requests, jobs, exceptions, and failed jobs.

**Scope:**

- Verify `/telescope` loads.
- Confirm Telescope captures requests and job events locally.
- Confirm sensitive details are hidden outside local environment.
- Document how to inspect failed requests/jobs.

**Dependencies:** Running app stack and at least one request/job event.

**Type:** Observability.

**Acceptance criteria:**

- Admin/developer can open Telescope locally.
- Telescope shows recent requests.
- Telescope shows queue/job entries after a job is dispatched.

### 9. Add Queue And Observability Documentation

**Purpose:** Make local operation repeatable.

**Scope:**

- Add or update documentation with:
  - service list;
  - URLs;
  - default admin credentials;
  - queue driver;
  - how to restart Horizon;
  - how to inspect logs;
  - how to clear failed jobs;
  - troubleshooting Redis connection failures.

**Suggested file:**

- `docs/architecture/queue-and-observability.md`

**Dependencies:** Tasks 1-8.

**Type:** Docs.

**Acceptance criteria:**

- A developer can start the stack and know where to inspect queues/status.
- Docs match actual service names and URLs.

### 10. Add Focused Verification Checklist

**Purpose:** Give future work a fast way to confirm async infrastructure is healthy.

**Scope:**

- Add a short checklist to docs.
- Include commands:
  - `docker compose ps`;
  - `docker compose logs horizon`;
  - `php artisan about --only=drivers`;
  - route URLs for Horizon/Pulse/Telescope.
- Include a safe way to dispatch or observe a test job.

**Dependencies:** Task 9.

**Type:** Docs / verification.

**Acceptance criteria:**

- Checklist can be followed after a fresh `docker compose up`.
- It confirms Redis, Horizon, and observability tools.

## Sequential vs Parallel Work

### Sequential

- Task 1 must happen before Redis queue verification.
- Task 2 must happen before Horizon can process the app queue.
- Task 3 must happen before automatic processing is available.
- Task 5 depends on Tasks 1-4.

### Parallelizable

- Task 6, Task 7, and Task 8 can be verified in parallel after the stack is running.
- Task 9 and Task 10 can be drafted while implementation is in progress, then corrected after verification.

## Risks And Notes

- Horizon expects Redis; using database queues with Horizon is the wrong long-term shape.
- If local `.env` is not updated, the app may still dispatch to database queues while Horizon watches Redis.
- Redis service naming must match `REDIS_HOST`.
- Pulse may need additional runtime configuration depending on what data we expect to see.
- Telescope is useful in local, but should remain permission-gated outside local.

## First Delivery Slice

Deliver Tasks 1-5 first. That gives the project the core behavior: jobs run automatically and Horizon can observe them. Then complete Pulse/Telescope verification and docs.
