# Mobile layout regression check

`docker/playwright/e2e/mobile-width.mjs` checks the rendered learner pages at
360 and 390px. It logs in with the seeded admin account, checks nine routes,
then replaces titles in browser API responses with a long title containing
an unbroken word. Populated recommendation and lesson-list fixtures ensure
those cards are exercised without writing learner data. Personal lists use the account's current
data; this is a layout check, not a permissions or exercise-flow test.

With the compose stack running:

```bash
docker compose --env-file config/docker.env run --rm --no-deps \
  -v "$PWD/docker/playwright/e2e:/app/e2e:ro" \
  browser node e2e/mobile-width.mjs
```

`E2E_BASE_URL`, `E2E_EMAIL`, and `E2E_PASSWORD` override the target and account.
For a worktree, start a Vite server mounting that worktree and pass its
container-reachable origin as `E2E_VITE_ORIGIN`; the test rewrites the shell's
5177 asset requests to that server. The Laravel API remains on `E2E_BASE_URL`.
Do not run against a production account.

Cards used as grid/flex children must be allowed to shrink (`min-w-0`). Apply
the same rule to nested grid links. Long titles must wrap (`break-words`) or
truncate within a constrained container, so they cannot widen the page.
Do not hide page overflow to make the width assertion pass.
