# Production sign-off checklist

This checklist is the final handoff artifact for the first production
deployment. Values that depend on the selected hosting provider must be filled
by the operator before launch; this repository intentionally does not invent
backup guarantees.

## Required evidence

| Area | Required evidence | Owner | Status |
|---|---|---|---|
| Database backups | Scheduled backup job, encrypted destination, retention policy and a successful restore artifact | Operations | Open |
| RPO | Approved maximum data loss in minutes, plus backup cadence proving it | Operations / product | Open |
| RTO | Approved maximum recovery time, plus timed restore drill proving it | Operations | Open |
| Secrets | Production secret manager, rotation owner and access review | Operations / security | Open |
| Migrations | Forward migration rehearsal and rollback/restore procedure | Backend | Open |
| Queues | Worker count, retry policy, dead-letter handling and alert route | Backend / operations | Open |
| Monitoring | Health check, error alerts, AI latency/cost alerts and log retention | Operations | Open |

## Acceptance criteria

Sign-off is complete only when each row has a named owner, a dated evidence
link or artifact, and an explicit pass/fail decision. The production RPO/RTO
values must be recorded in the deployment runbook and reviewed after every
backup or restore architecture change.

The local PostgreSQL drill in
[`backup-restore-runbook.md`](backup-restore-runbook.md) proves the procedure
is executable. It is not evidence that production backups are scheduled or
that a production RPO/RTO has been achieved.
