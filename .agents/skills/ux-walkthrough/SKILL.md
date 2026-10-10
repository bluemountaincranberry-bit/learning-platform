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

- Start the app using the repository's documented commands and use the existing browser/Playwright workflow from `webapp-testing` when available.
- Cover the requested happy path and the meaningful empty, loading, validation, error, recovery, and return paths visible in scope. Follow the interface rather than relying only on source-code assumptions.
- Check mobile first at 390×844 and 360px width, then desktop at 1280px or wider. Record viewport, route, state, and reproducible actions for every finding. Capture screenshots where the browser tooling supports it.
- Check keyboard access and visible focus on the primary path. Note console errors and horizontal overflow when the tooling exposes them.
- Use Browserbase's `browserbase-ui-test` only when the `browse` CLI is already available or the user explicitly requests its setup. It requires delegated test groups and a bounded browse-step budget; follow its planning and reporting rules. Otherwise use `webapp-testing` and report the actual coverage. Never install global browser tooling or alter the user's browser session silently.

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
