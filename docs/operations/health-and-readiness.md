# Health and readiness

`GET /health` is the lightweight readiness endpoint for the web and reverse
proxy layer. It is intentionally unauthenticated so a load balancer or
container supervisor can call it.

The endpoint checks the durable PostgreSQL connection:

- `200` with `{"status":"ok"}` means the application can reach its database;
- `503` with `{"status":"not_ready"}` means traffic should not be routed to
  the instance yet.

Redis, Horizon and external AI providers are operational dependencies with
their own monitoring signals. They are not required for the process to answer
the health request, because a temporary queue/provider outage should not make
the web process appear dead. Horizon must still be monitored separately and
failed jobs must be reviewed before production launch.

Local check:

```bash
curl -i http://localhost:8088/health
```

The readiness response contains no secrets or connection details.
