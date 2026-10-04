# Mobile shell navigation

VIK-19 replaces the scrolling phone bar with five fixed columns:
Today (Dashboard), Lessons, Words (My words), Practice and More.
More opens the shared sheet with Catalog, Grammar, My grammar, Progress,
AI chat and Settings. The existing AI chat role restriction still applies.
Desktop sidebar labels and destinations are unchanged.

Lessons and word detail pages highlight their primary tab. Catalog details,
study, categories and source submission highlight More; grammar details do
as well. The corresponding destination inside the sheet is highlighted.
The existing focused practice/exam layouts remain free of shell navigation.

The sheet closes on selection, route change, Escape, close button or backdrop.
The shared dialog supplies focus handling; the bar and sheet links have visible
keyboard focus and at least 44px tap targets. Phone safe-area padding prevents
the home indicator from covering navigation.

## Focused regression check

`docker/playwright/e2e/bottom-navigation.mjs` loads the real SPA and router
from a Vite server serving the ticket worktree. Set `E2E_VITE_ORIGIN` to that
server's container-accessible URL and run the script in the Playwright image:

```bash
E2E_VITE_ORIGIN=http://your-worktree-vite:5199 node e2e/bottom-navigation.mjs
```

The Vite server must use Vue, the normal `@` alias to `resources/js`, and the
existing PostCSS config. Scan `resources/js/spa/main.ts` for dependency
optimization before running to avoid dev-server reloads during navigation.
No Laravel server or database is needed: auth/profile use browser API fixtures;
other APIs return 503 to check that unavailable page data does not block nav.
The script checks all ten previous destinations in one or two taps, active
nested routes, 360/390px geometry, sheet dismissal/focus, staff chat visibility
and the desktop sidebar. Optional `E2E_ARTIFACT_DIR` saves screenshots.
