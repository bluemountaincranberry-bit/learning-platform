---
name: learning-app-improvement-audit
description: Audit the Learning App as a learner, assess learning research, responsive UX and AI tutor quality, and produce an evidence-linked checklist ready for OpenSpec.
---

# Learning App Improvement Audit

Use existing ai-orchestration and current project docs. Default to one bounded journey: B1 English learner, 20 minutes, wants to understand a YouTube video. State assumptions.

Read docs/product/improvement-skills.md for installed skills, tested browser runtime and optional dependency limitations.

## Passes

- Learner: use webapp-testing or available browser controls. Walk video selection → unfamiliar words → exercises → review → progress → tutor. Record actions, screenshots, confusing choices and failures on mobile and desktop. Record test account, viewport and app revision. Code inspection is not a completed UI walkthrough. Use test data; paid ingestion needs authorization.
- Research: use scientific-critical-thinking and available web search. Read primary language-learning research and credible reviews. Save queries, dates, populations, interventions, retention periods, outcomes, limitations, verified links and product implications in docs/product/improvement-audits/learning-evidence.md. Reuse relevant evidence; refresh for new questions or contradictions. Do not infer universal intervals from one study or learning efficacy from simulated learners.
- UX: use web-design-guidelines and impeccable critique/audit for clarity, hierarchy, accessibility, mobile behavior and simplification. Preserve existing identity during refinements.
- AI tutor: use llm-evaluation with existing eval commands and fixtures. Inspect prompts, context and memory. Test in-context explanations, level-appropriate corrections, hints, weak words, missing transcripts and misleading source text. Save versions, cases, outputs and rubrics. Separate deterministic checks, teacher judgment and model judgment; keep evaluation cases independent from prompt tuning. Text similarity is not tutoring quality.

Read specialist instructions before applying them. Upstream literature-review is optional for formal reviews and requires additional search and figure-generation tools. Ordinary product research can use available web search and scientific-critical-thinking.

## Output

Write a dated report under docs/product/improvement-audits/. Each finding needs an ID, evidence, observed/estimated status, learner impact, confidence, module owner, effort, proposed small change and observable acceptance criteria. Include successful features. Deduplicate against existing OpenSpec changes and local tasks.

Use a checklist with states proposed, selected, specified, implemented, verified or deferred. Check off only verified work. State what ran and what remains untested. Recommend the smallest useful next slice.

For selected work use openspec-propose or the Russian local-task template, then openspec-apply-change for authorized implementation. Verify the original journey afterward. An audit alone does not authorize app changes or background schedules.

Delegate bounded context packets when available; otherwise run sequential passes and disclose that no independent agents ran.
