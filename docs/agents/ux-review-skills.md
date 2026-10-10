# UX review skills

`$ux-walkthrough` is the project entry point for evaluating an existing Learning App flow as a first-time user. It combines a bounded product review, mobile-first browser inspection, design critique, a11y/responsive checks, and evidence-based reporting. It only implements fixes when asked and follows `linear-work` for ticket work.

## Installed references

| Skill | Source / license | Codex installation and dependencies | Use |
|---|---|---|---|
| `ui-ux-pro-max` | [nextlevelbuilder/ui-ux-pro-max-skill](https://github.com/nextlevelbuilder/ui-ux-pro-max-skill), MIT | `npx --yes ui-ux-pro-max-cli init --ai codex`; Node/npm for setup, local searchable data afterward. | Design patterns, UX rules, palettes, and stack guidance; consult for a specific decision. |
| `fixing-accessibility` | [ibelick/ui-skills](https://github.com/ibelick/ui-skills), MIT | `npx --yes skills add https://github.com/ibelick/ui-skills --skill fixing-accessibility --agent codex --copy -y`; no additional runtime. | Targeted accessibility review and implementation guidance. |
| `browserbase-ui-test` | [browserbase/skills](https://github.com/browserbase/skills), skill declares MIT; upstream root has no LICENSE | `npx --yes skills add browserbase/skills --skill ui-test --agent codex --copy -y`; local browser requires `browse`, remote tests also need credentials. | Adversarial real-browser tests with delegated groups and bounded step budgets. `browse` is present in this environment. |
| `ux-audit-evidence` | [paulunemoon/ux-audit-skill](https://github.com/paulunemoon/ux-audit-skill), MIT, version 1.4.0 | `npx --yes skills add https://github.com/paulunemoon/ux-audit-skill --skill ux-audit --agent codex --copy -y`; no additional runtime. | Evidence, severity, confidence, and actionable recommendations for existing-product audits. |

These supplement the already available `impeccable`, `webapp-testing`, `web-design-guidelines`, and `frontend-design` skills. `ux-walkthrough` assigns each a distinct role so the same flow is not audited repeatedly.

## Not installed

The Flagrare UX audit candidate was reviewed but not retained. Its repository does not declare a license, and its workflow requires global Chrome DevTools MCP installation and managing a local Chrome debugging session. The project workflow captures the useful first-time-user walkthrough and coverage ideas without copying or requiring those parts. No Flagrare source files are included.

## Maintenance

The third-party skills are copied into `.agents/skills/`; their upstream URLs and installation notes are recorded in each `UPSTREAM.md`. Update or remove them deliberately and review upstream changes before replacing local copies. The Browserbase skill's frontmatter says MIT; its upstream repo has no root LICENSE, so keep that licensing caveat visible.
