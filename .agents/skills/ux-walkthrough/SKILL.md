---
name: ux-walkthrough
description: Run an evidence-based, first-time-user UX walkthrough of an existing Learning App flow, with mobile-first browser coverage, product critique, accessibility and responsive checks, and prioritized fixes when requested.
---

# UX Walkthrough

Use this workflow when Vika asks to review an existing product flow as a user, assess whether it is clear and convenient, or audit and fix its UX. It combines product framing, a real browser walkthrough, design critique, technical checks, and a concise evidence report. The walkthrough is an expert inspection, not user research.

## 1. Set the scope

- Identify the feature or flow, user type, goal, entry point, and whether the request asks for findings only or findings plus fixes.
- Read `AGENTS.md`, `PROJECT_CONTEXT.md`, and relevant product/design docs. Load `product-owner` for product, UX, or priority decisions. Record assumptions and escalate only its stated § Escalate cases.
- If the product outcome or scope is still open, use `$grill-with-docs` first to resolve it and preserve durable decisions. Then resume this workflow from the agreed scope.
- Keep the requested flow bounded. Track adjacent shared components as cross-cutting checks; move unrelated issues to follow-up tickets.

## 2. Inspect the implementation and shared patterns

- Trace the route, states, actions, empty/loading/error/success cases, and components used by the requested flow.
- Identify shared components and search their other call sites before recommending a shared change. State which other surfaces were inspected.
- Read the relevant `DESIGN.md`, `PRODUCT.md`, design tokens, and component conventions if present. In a new session, run Impeccable context once for the target before critique or edits.
- Use `ui-ux-pro-max` as an on-demand design reference for specific disputed patterns or visual decisions. Query the matching topic/stack; do not apply its generic recommendations mechanically.

## 3. Walk through as a first-time user

- First confirm the browser URL serves the checkout under review. The default Docker Compose stack bind-mounts the checkout from which it was started; a main-checkout server does not show uncommitted worktree code. If no server for the target checkout is available, set one up with the repository's documented commands or mark browser coverage as skipped.
- Choose the browser path by purpose:
  - **Exploratory walkthrough:** use an interactive browser tool if its actual runtime is available. `browserbase-ui-test` requires the Browserbase `browse` CLI; check that `browse --help` identifies the Browserbase CLI, since some systems use `browse` for a desktop URL opener. If it is unavailable, use the project's Playwright container below. The generic `webapp-testing` helper is a fallback only when its Python Playwright runtime is installed and the project has no better local runner.
  - **Repeatable SPA checks:** reuse the existing Node Playwright runtime in `docker/playwright` (Playwright 1.55). From a running Compose stack, run a focused mocked-API script, for example `docker compose --env-file config/docker.env exec -T browser node e2e/lesson-items.mjs` or `docker compose --env-file config/docker.env exec -T browser node e2e/mobile-width.mjs`. The image contains scripts under `docker/playwright/e2e/`; `lesson-items.mjs`, `bottom-navigation.mjs`, and `transcript-practice.mjs` stub API traffic for their scenarios, while `mobile-width.mjs` reads the local app and uses API fixtures for long-text checks. Use a server known to serve the same checkout being reviewed.
  - **A flow with no existing script:** send a short, temporary JavaScript scenario to the running browser service with `docker compose --env-file config/docker.env exec -T browser node --input-type=module -`. Import `chromium` from `playwright`, use role/label-based locators, record console/page errors, set the required viewport sizes, and close context/browser in `finally`. Stub write APIs or use disposable data. If the report needs screenshots, save them in the container and copy them with `docker compose --env-file config/docker.env cp browser:/tmp/ux-flow.png .ux-audit/FLOW_SLUG/`. Keep the scenario out of the permanent test suite unless it is a stable regression case.
- Treat `make e2e` as a specific regression suite, not the default audit runner: it currently invokes only `rewrite-smoke.mjs`, which edits and saves AI Builder prompt and graph drafts. Run it only against disposable local data. Prefer the targeted mocked-API Playwright scripts for checks that do not need persistent data.
- Cover the requested happy path and the meaningful empty, loading, validation, error, recovery, and return paths visible in scope. Follow the interface rather than relying only on source-code assumptions.
- Check mobile first at 390×844 and 360px width, then desktop at 1280px or wider. Record viewport, route, state, and reproducible actions for every finding. Capture screenshots where the browser tooling supports it.
- Check keyboard access and visible focus on the primary path. Note console errors and horizontal overflow when the tooling exposes them.
- When using Browserbase's adversarial test workflow, follow its delegated test-group planning and bounded step-budget rules. Report which browser path and scripts ran, the target checkout/URL, and any skipped states. Never install global browser tooling or alter the user's browser session silently.

## 4. Evaluate once, from distinct angles

- Use `$impeccable critique <target>` for product clarity, information hierarchy, cognitive load, navigation, and interaction quality. Preserve its required user approval pause if its instructions request subagents.
- Use `$impeccable audit <target>` for technical accessibility, responsive behavior, and performance checks. Do not repeat the same manual browser exploration for each tool.
- Use `ux-audit-evidence` to ensure each issue is tied to observed evidence, severity, confidence, and a concrete recommendation. A source-only or inferred concern must be labeled as a hypothesis.
- Use `fixing-accessibility` when a confirmed accessibility issue needs a code-level fix.
- Use `frontend-design` when making substantial visual changes. Keep the project's established design direction unless the request explicitly calls for a redesign.
- Treat `web-design-guidelines` as a focused standards cross-check when relevant, not as another full audit pass.

## 5. Report findings before edits

Write the requested review to `.ux-audit/<flow-slug>-<date>.md` unless the user requested chat-only results. Include:

- scope, persona/goal, routes and states covered, viewports, tools, and skipped coverage;
- prioritized findings with a short title, severity (P0–P3), confidence (high/medium/low), observed evidence, user impact, and specific recommendation;
- screenshot paths or precise reproduction steps;
- shared-component call sites inspected and whether a proposed fix should be local or shared;
- strengths worth preserving and open assumptions.

Separate observations from hypotheses. Do not claim that automated inspection represents real user testing. Deliver this report before the first code edit when the user asks for both review and fixes.

## 6. Fix only when requested

- If fixes are requested, follow `linear-work`: work on the ticket's branch, preserve unrelated changes, use `tdd` for implementation, inspect all consumers before changing shared components, and run the relevant checks.
- Fix the highest-impact in-scope findings first. Update the report with resolved status and checks. Create/link follow-up tickets for worthwhile out-of-scope issues.
- If the request is audit-only, stop after delivering the report and recommended next steps.

## Completion

The review is complete when the bounded scope has a recorded coverage map, every finding has evidence or is labeled as a hypothesis, shared-component impact is checked, and the requested report (and fixes, if requested) is delivered. For ticket work, follow the `linear-work` merge gate.
