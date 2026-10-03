# Domain Docs

How the engineering skills read this repo's domain documentation.

## Before exploring, read

- `GLOSSARY.md` at the repo root (created lazily by `/domain-modeling`; proceed silently if missing).
- ADRs in `docs/architecture/adr/` that touch the area you work in.
- Product intent: the `product-owner` skill (`.agents/skills/product-owner/VISION.md`, `DECISIONS.md`).

Layout: single-context. New ADRs go to `docs/architecture/adr/` using the next `ADR-NNN-<slug>.md` number.

## Use the glossary's vocabulary

Name domain concepts in tickets, tests and code with the `GLOSSARY.md` term. A missing term is a gap for `/domain-modeling`.

## Flag ADR conflicts

If your output contradicts an ADR, say so explicitly:

> _Contradicts ADR-007 (modular monolith rewrite), but worth reopening because…_
