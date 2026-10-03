# Backup and restore runbook

## Scope

PostgreSQL is the durable source of truth. Redis, Elasticsearch and Kafka
volumes are runtime/derived infrastructure and must be handled according to
their own retention policies.

## Backup

Run a custom-format dump from the database service and store it in encrypted,
access-controlled backup storage:

```bash
docker compose --env-file config/docker.env exec -T db \
  pg_dump -U "$DB_USERNAME" -d "$DB_DATABASE" -Fc > backup.dump
```

The production wrapper must provide credentials without putting them in shell
history or committed files. Verify the dump checksum and upload metadata with
the backup artifact.

## Restore drill

Never restore over the live database during a drill. Restore into a new,
isolated database, run migrations/read-only smoke checks, compare sentinel
row counts, and remove only the temporary database after verification:

```bash
docker compose --env-file config/docker.env exec -T db createdb -U "$DB_USERNAME" restore_drill
docker compose --env-file config/docker.env exec -T db \
  pg_restore -U "$DB_USERNAME" -d restore_drill --no-owner --no-privileges < backup.dump
docker compose --env-file config/docker.env exec -T db \
  psql -U "$DB_USERNAME" -d restore_drill -c 'select count(*) from migrations;'
docker compose --env-file config/docker.env exec -T db dropdb -U "$DB_USERNAME" restore_drill
```

## Evidence

On 2026-09-14 a local drill created a 28.7 MB custom-format dump, restored it
into an isolated temporary database, and compared sentinel counts with the
source database:

| Sentinel | Source | Restored |
| --- | ---: | ---: |
| `users` | 14 | 14 |
| `contents` | 5 | 5 |
| `migrations` | 105 | 105 |

The temporary database and dump were removed after verification. This proves
the local procedure, not production backup availability.

## Production targets to set

Before launch, the operator must set and monitor explicit values for:

- RPO: target maximum acceptable data loss;
- RTO: target maximum restoration time;
- retention duration and backup encryption/access policy;
- restore drill cadence and named owner.

No production RPO/RTO is claimed until the hosting provider, backup storage
and restore drill are selected and verified.
