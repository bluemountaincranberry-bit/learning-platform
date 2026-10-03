# Queue And Observability

## Local Runtime

The local Docker stack uses Redis for Laravel queues and Horizon as the long-running queue worker.

Services:

- `app`: PHP-FPM application container.
- `horizon`: runs `php artisan horizon` and processes queued jobs.
- `redis`: Redis backend for queues and Horizon metadata.
- `web`: nginx entrypoint.
- `db`: PostgreSQL.

## Configuration

Required local environment values:

```dotenv
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PORT=6379
```

Cache and sessions currently stay on the database driver. Redis is used first for queue runtime because Horizon requires Redis-backed queues.

## URLs

Use the normal application host and port:

- App: `http://localhost:8088`
- Admin: `http://localhost:8088/admin`
- Horizon: `http://localhost:8088/horizon`
- Pulse: `http://localhost:8088/pulse`
- Telescope: `http://localhost:8088/telescope`

Admin login:

```text
admin@example.com
password
```

## Common Commands

Start or rebuild the stack:

```bash
docker compose up -d --build
```

Check services:

```bash
docker compose ps
```

Watch Horizon logs:

```bash
docker compose logs -f horizon
```

Check Laravel drivers:

```bash
docker compose exec app php artisan about --only=drivers
```

Restart Horizon after config changes:

```bash
docker compose exec horizon php artisan horizon:terminate
```

## Verification Checklist

After a fresh start:

1. `docker compose ps` shows `redis` and `horizon` running.
2. `docker compose exec app php artisan about --only=drivers` shows `Queue` as `redis`.
3. `http://localhost:8088/horizon` loads and shows an active supervisor.
4. Dispatching a queued job is processed without manually running `queue:work`.
5. `http://localhost:8088/telescope` shows recent requests and queue activity.
6. `http://localhost:8088/pulse` loads for an admin user.

## Troubleshooting

If jobs stay pending:

- confirm `QUEUE_CONNECTION=redis` in `src/.env`;
- confirm `REDIS_HOST=redis`;
- run `docker compose logs horizon`;
- run `docker compose exec app php artisan config:clear`;
- restart Horizon with `docker compose exec horizon php artisan horizon:terminate`.

If Horizon cannot connect to Redis:

- confirm the `redis` service is running;
- confirm the PHP image has the `redis` extension enabled;
- rebuild the PHP image with `docker compose build app`.
