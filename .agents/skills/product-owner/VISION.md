# Learning App — Vika's vision

Written 2026-10-03 from Vika's own description. Owner: Vika.

## What the app is

An AI-powered app for learning **any language through real content**.
It starts with **English**, but language must never be hard-coded: every
content item, lesson, word and rule carries its language, and adding a second
language should be configuration plus content, not a rewrite.

## Who it is for

- **Now: one user — Vika.** No other users, no backward compatibility, no
  migration of other people's data. Break and reshape APIs and schema freely.
- **Later: production-ready for other learners.** Build with real enterprise
  practices so that step is possible, but never slow down today's work for
  hypothetical users.

## Core loop (most valuable part)

1. **Get material in**
   - **YouTube video** → full transcript → what is worth learning (words,
     phrases, grammar). This is the first source and must work well.
   - **Group lessons** (Vika takes group English lessons): she has notes,
     **images (photos of boards, notebooks, handouts) and PDFs**. She uploads
     everything about one lesson in one place.
   - Later: **movie subtitles**, then **books**.
2. **AI picks what matters.** From any source, AI proposes the most valuable
   words, phrases and grammar. Vika chooses what to keep; the app saves it.
3. **Save and come back.** Everything from a lesson or video stays readable as
   a clear page: what we covered, words, grammar, examples. Easy to reread,
   especially on the phone.
4. **Repeat and remember.** Saved words and grammar go into repetition.
   Forgotten items come back. Vika can pick words from **different sources**
   (videos and lessons together) and choose what to relearn.

If a feature does not help this loop, it is lower priority.

## Product principles

- **Mobile first.** Every learner screen must look good and work one-handed on
  a phone. Desktop is second.
- **Easy by default, powerful in settings.** Many options are fine, but the
  default path needs no configuration and every screen has one obvious next
  action. Advanced options live in Settings, not on the main path.
- **AI proposes, Vika chooses** for her personal lists (what to learn, what to
  keep from a lesson). Background catalog enrichment may be automatic
  (see DECISIONS.md).
- **Basic actions work without AI.** Writing and reading a lesson, reviewing
  words must not break when an AI provider is down or disabled.
- **Simple over clever.** The smallest change that makes the loop better wins.
- **One interface language** on the learner side; no RU/EN mix.

## Starter content

The app should not start empty. Seed it with English **TED talks from YouTube**
(including ones Vika already studied) through the normal ingestion pipeline, as
a repeatable command or seeder — not a schema migration.

## Engineering and learning goals

Vika is a PHP developer using this project to grow, especially in AI. The app
is also her training ground:

- a real **agentic AI platform** (tool calling, agents, prompts, evaluation,
  tracing, cost control);
- the practices **enterprise applications** use: modular monolith, events,
  queues, observability, CI, ADRs, testing, security;
- Laravel + Vue done well.

Prefer solutions that teach a strong, explainable industry practice — when
they also serve the product. Technology for its own sake is not a goal.

## How work happens

- Agents research, write tickets in Linear and implement **mostly without
  Vika**. She reviews and corrects.
- Tickets are short, clear and written for an agent to execute
  (see `docs/agents/issue-tracker.md`).
- Product questions go to the `product-owner` skill first.
